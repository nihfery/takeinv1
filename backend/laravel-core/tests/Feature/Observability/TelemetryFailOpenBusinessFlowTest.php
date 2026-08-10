<?php

namespace Tests\Feature\Observability;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Staff\Infrastructure\Persistence\Models\StaffSchedule;
use App\Support\Observability\FailOpenTelemetry;
use App\Support\Observability\TelemetryExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TelemetryFailOpenBusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'observability.telemetry.enabled' => true,
            'observability.telemetry.timeout_ms' => 50,
            'services.midtrans.server_key' => 'telemetry-fail-open-midtrans-key',
            'services.midtrans.is_production' => false,
        ]);
        Http::preventStrayRequests();
    }

    public function test_unavailable_telemetry_backend_cannot_break_booking_creation(): void
    {
        $exporter = $this->useUnavailableExporter();
        $customer = User::factory()->create(['role' => 'customer']);
        [$branch, $service, $staff] = $this->bookableBranchServiceAndStaff();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.store'), [
                'branch_id' => $branch->id,
                'service_ids' => [$service->id],
                'booking_type' => 'scheduled',
                'staff_id' => $staff->id,
                'booking_date' => now()->addDays(3)->toDateString(),
                'start_time' => '13:00',
                'payment_type' => 'full_payment',
                'payment_channel' => 'qris',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment');

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, $exporter->attempts);
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }

    public function test_unavailable_telemetry_backend_cannot_break_payment_or_correlated_audit(): void
    {
        $exporter = $this->useUnavailableExporter();
        [$customer, $booking, $payment] = $this->createGatewayPayment();
        $orderId = 'JSK-'.$booking->booking_code.'-'.$payment->id;

        Http::fake([
            'https://api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'status_message' => 'Success, GoPay transaction is created',
                'transaction_id' => 'telemetry-payment-transaction',
                'order_id' => $orderId,
                'gross_amount' => '100000.00',
                'currency' => 'IDR',
                'payment_type' => 'gopay',
                'transaction_status' => 'pending',
                'actions' => [[
                    'name' => 'generate-qr-code-v2',
                    'method' => 'GET',
                    'url' => 'https://api.sandbox.midtrans.com/v2/qris/example/qr-code',
                ]],
                'expiry_time' => now()->addMinutes(7)->format('Y-m-d H:i:s O'),
            ], 201),
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.payment.charge', $booking), [
                'payment_channel' => 'qris',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment.midtrans_order_id', $orderId);

        $requestId = (string) $response->headers->get('X-Request-ID');
        $correlationId = (string) $response->headers->get('X-Correlation-ID');

        $this->assertSame(1, $exporter->attempts);
        $this->assertSame($requestId, $correlationId);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.gateway-charge.created',
            'resource_id' => (string) $payment->id,
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
        ]);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
    }

    private function useUnavailableExporter(): AlwaysUnavailableTelemetryExporter
    {
        $exporter = new AlwaysUnavailableTelemetryExporter;
        $this->app->instance(TelemetryExporter::class, $exporter);
        $this->app->forgetInstance(FailOpenTelemetry::class);

        return $exporter;
    }

    private function createGatewayPayment(): array
    {
        $provider = User::factory()->create(['role' => 'provider']);
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::query()->create([
            'booking_code' => 'OBS-'.Str::upper(Str::random(12)),
            'booking_date' => now()->addDay()->toDateString(),
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'booking_type' => 'scheduled',
            'total_duration' => 60,
            'total_price' => 100000,
            'participant_count' => 1,
            'status' => 'pending_payment',
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'full_payment',
            'amount' => 100000,
            'status' => 'pending',
            'payment_method' => 'manual',
        ]);
        $payment->gatewayTransaction()->create([
            'gateway' => 'midtrans',
            'payment_channel' => 'qris',
            'expires_at' => now()->addMinutes(7),
        ]);

        return [$customer, $booking, $payment->refresh()];
    }

    private function bookableBranchServiceAndStaff(): array
    {
        $provider = User::factory()->create(['role' => 'provider']);

        ProviderProfile::query()->create([
            'user_id' => $provider->id,
            'phone_number' => '08123456789',
            'status' => 'active',
            'document_status' => 'verified',
        ]);

        $branch = ProviderBranch::query()->create([
            'provider_id' => $provider->id,
            'branch_name' => 'Telemetry Salon '.$provider->id,
            'email' => 'telemetry-'.$provider->id.'@example.test',
            'phone_code' => '+62',
            'phone_number' => '8123456789',
            'address' => 'Jl. Telemetry '.$provider->id,
            'country_id' => 'Indonesia',
            'state_id' => 'DKI Jakarta',
            'city_id' => 'Jakarta',
            'zip_code' => '12345',
            'working_start_hour' => '09:00',
            'working_end_hour' => '21:00',
            'working_days' => [strtolower(now()->addDays(3)->englishDayOfWeek)],
            'holidays' => [],
            'status' => 'active',
        ]);

        $category = ServiceCategory::query()->create([
            'name' => 'Telemetry Hair',
            'slug' => 'telemetry-hair-'.$provider->id,
            'description' => 'Layanan rambut',
            'status' => 'active',
            'is_featured' => true,
        ]);

        $service = Service::query()->create([
            'provider_id' => $provider->id,
            'title' => 'Telemetry Hair Spa '.$provider->id,
            'slug' => 'telemetry-hair-spa-'.$provider->id,
            'category' => $category->name,
            'category_id' => $category->id,
            'code' => 'OBSHAIR'.$provider->id,
            'description' => 'Treatment rambut',
            'includes' => 'Konsultasi dan treatment',
            'price_type' => 'fixed',
            'price' => 100000,
            'minimum_duration' => 50,
            'estimated_duration' => 60,
            'maximum_duration' => 80,
            'is_queue_enabled' => true,
            'is_scheduled_enabled' => true,
            'requires_dp' => false,
            'dp_amount' => null,
            'payment_policy' => 'Bayar setelah layanan',
            'slots' => [],
            'additional_services' => [],
            'holidays' => [],
            'branch_ids' => [$branch->id],
            'status' => 'active',
            'verify_status' => 'verified',
        ]);

        $staff = ProviderStaff::query()->create([
            'provider_id' => $provider->id,
            'first_name' => 'Sari',
            'last_name' => 'Observability',
            'email' => 'sari-observability-'.$provider->id.'@example.test',
            'gender' => 'female',
            'branch_id' => $branch->id,
            'role' => 'Senior Stylist',
            'current_status' => 'available',
            'status' => 'active',
        ]);
        $staff->skills()->attach($service->id);

        StaffSchedule::query()->create([
            'staff_id' => $staff->id,
            'day_of_week' => strtolower(now()->addDays(3)->englishDayOfWeek),
            'start_time' => '09:00',
            'end_time' => '21:00',
            'is_available' => true,
        ]);

        return [$branch, $service, $staff];
    }
}

final class AlwaysUnavailableTelemetryExporter implements TelemetryExporter
{
    public int $attempts = 0;

    public function exportHttpServerSpan(
        Request $request,
        ?Response $response,
        float $startedAt,
        float $finishedAt,
    ): void {
        $this->attempts++;

        throw new RuntimeException('Collector is unavailable.');
    }
}
