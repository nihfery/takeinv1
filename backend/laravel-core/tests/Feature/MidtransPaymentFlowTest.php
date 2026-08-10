<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MidtransPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => 'midtrans-payment-flow-test-key',
            'services.midtrans.is_production' => false,
        ]);
        Http::preventStrayRequests();
    }

    public function test_customer_charge_creates_one_idempotent_midtrans_transaction(): void
    {
        [$customer, $booking, $payment] = $this->createGatewayPayment();
        $orderId = $this->expectedOrderId($booking, $payment);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/charge' => Http::response(
                $this->chargeResponse($orderId),
                201
            ),
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.payment.charge', $booking), [
                'payment_channel' => 'qris',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment.payment_method', 'midtrans')
            ->assertJsonPath('data.payment.payment_channel', 'qris')
            ->assertJsonPath('data.payment.midtrans_order_id', $orderId)
            ->assertJsonPath('data.payment.midtrans_transaction_status', 'pending')
            ->assertJsonPath('data.payment.payment_code_label', 'QRIS')
            ->assertJsonPath('data.payment.qr_url', 'https://api.sandbox.midtrans.com/v2/qris/example/qr-code');

        $this->assertSame($orderId, $response->json('data.payment.midtrans_order_id'));
        $this->assertDatabaseHas('payment_gateway_transactions', [
            'payment_id' => $payment->id,
            'gateway' => 'midtrans',
            'payment_channel' => 'qris',
            'provider_order_id' => $orderId,
            'provider_transaction_id' => 'payment-transaction-1',
            'provider_status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => (string) $customer->id,
            'action' => 'payment.gateway-charge.created',
            'resource_id' => (string) $payment->id,
            'provider_id' => $booking->provider_id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.payment.charge', $booking), [
                'payment_channel' => 'qris',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment.midtrans_order_id', $orderId)
            ->assertJsonPath('data.payment.qr_url', 'https://api.sandbox.midtrans.com/v2/qris/example/qr-code');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
            && $request['transaction_details']['order_id'] === $orderId
            && $request['transaction_details']['gross_amount'] === 100000
            && $request['payment_type'] === 'gopay');
    }

    public function test_customer_status_uses_authoritative_midtrans_state_to_confirm_booking(): void
    {
        [$customer, $booking, $payment] = $this->createGatewayPayment();
        $orderId = $this->expectedOrderId($booking, $payment);
        $payment->gatewayTransaction()->update([
            'provider_order_id' => $orderId,
            'provider_transaction_id' => 'payment-transaction-1',
            'provider_status' => 'pending',
            'raw_response' => $this->chargeResponse($orderId),
        ]);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'order_id' => $orderId,
                'status_code' => '200',
                'gross_amount' => '100000.00',
                'currency' => 'IDR',
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'transaction_id' => 'payment-transaction-1',
            ], 200),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson(route('api.customer.bookings.payment.status', $booking))
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.payment.payment_method', 'midtrans')
            ->assertJsonPath('data.payment.midtrans_transaction_status', 'settlement');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('midtrans', $payment->fresh()->payment_method);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => (string) $customer->id,
            'action' => 'payment.gateway-status.verified',
            'resource_id' => (string) $payment->id,
            'provider_id' => $booking->provider_id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson(route('api.customer.bookings.payment.status', $booking))
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'paid');

        $this->assertDatabaseCount('audit_logs', 1);
        Http::assertSentCount(2);
    }

    public function test_other_customer_cannot_charge_or_check_a_booking_payment(): void
    {
        [, $booking] = $this->createGatewayPayment();
        $attacker = User::factory()->create(['role' => 'customer']);

        $this->actingAs($attacker, 'sanctum')
            ->postJson(route('api.customer.bookings.payment.charge', $booking), [
                'payment_channel' => 'qris',
            ])
            ->assertForbidden();

        $this->actingAs($attacker, 'sanctum')
            ->getJson(route('api.customer.bookings.payment.status', $booking))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_overdue_gateway_order_is_reconciled_before_a_booking_slot_is_released(): void
    {
        [$customer, $booking, $payment] = $this->createGatewayPayment();
        $orderId = $this->expectedOrderId($booking, $payment);
        $payment->gatewayTransaction()->update([
            'provider_order_id' => $orderId,
            'provider_transaction_id' => 'payment-transaction-1',
            'provider_status' => 'pending',
            'expires_at' => now()->subMinute(),
            'raw_response' => $this->chargeResponse($orderId),
        ]);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'order_id' => $orderId,
                'status_code' => '200',
                'gross_amount' => '100000.00',
                'currency' => 'IDR',
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'transaction_id' => 'payment-transaction-1',
            ], 200),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson(route('api.customer.bookings.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $booking->id)
            ->assertJsonPath('data.0.status', 'confirmed')
            ->assertJsonPath('data.0.payment.status', 'paid');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_overdue_gateway_order_stays_pending_when_authoritative_status_is_unavailable(): void
    {
        [$customer, $booking, $payment] = $this->createGatewayPayment();
        $orderId = $this->expectedOrderId($booking, $payment);
        $payment->gatewayTransaction()->update([
            'provider_order_id' => $orderId,
            'provider_status' => 'pending',
            'expires_at' => now()->subMinute(),
            'raw_response' => $this->chargeResponse($orderId),
        ]);
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'status_code' => '503',
                'status_message' => 'Gateway temporarily unavailable.',
            ], 503),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson(route('api.customer.bookings.index'))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending_payment')
            ->assertJsonPath('data.0.payment.status', 'pending');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_overdue_pending_gateway_order_is_remotely_expired_before_releasing_slot(): void
    {
        [$customer, $booking, $payment] = $this->createGatewayPayment();
        $orderId = $this->expectedOrderId($booking, $payment);
        $payment->gatewayTransaction()->update([
            'provider_order_id' => $orderId,
            'provider_transaction_id' => 'payment-transaction-1',
            'provider_status' => 'pending',
            'expires_at' => now()->subMinute(),
            'raw_response' => $this->chargeResponse($orderId),
        ]);
        $statusChecks = 0;
        Http::fake(function (Request $request) use (&$statusChecks, $orderId) {
            if (str_ends_with($request->url(), '/status')) {
                $statusChecks++;
                $pending = $statusChecks === 1;

                return Http::response([
                    'order_id' => $orderId,
                    'status_code' => $pending ? '201' : '407',
                    'gross_amount' => '100000.00',
                    'currency' => 'IDR',
                    'transaction_status' => $pending ? 'pending' : 'expire',
                    'fraud_status' => 'accept',
                    'transaction_id' => 'payment-transaction-1',
                ], 200);
            }

            if (str_ends_with($request->url(), '/expire')) {
                return Http::response([
                    'status_code' => '407',
                    'status_message' => 'Transaction is expired',
                    'transaction_status' => 'expire',
                ], 200);
            }

            return Http::response([], 404);
        });

        $this->actingAs($customer, 'sanctum')
            ->getJson(route('api.customer.bookings.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $booking->id)
            ->assertJsonPath('data.0.status', 'payment_expired')
            ->assertJsonPath('data.0.payment.status', 'expired');

        $statusUrl = 'https://api.sandbox.midtrans.com/v2/' . rawurlencode($orderId) . '/status';
        $expireUrl = 'https://api.sandbox.midtrans.com/v2/' . rawurlencode($orderId) . '/expire';
        $requests = Http::recorded()
            ->map(fn (array $pair) => [$pair[0]->method(), $pair[0]->url()])
            ->all();

        $this->assertSame([
            ['GET', $statusUrl],
            ['POST', $expireUrl],
            ['GET', $statusUrl],
        ], $requests);
        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('payment_expired', $booking->fresh()->status);
        Http::assertSentCount(3);
    }

    public function test_untrusted_charge_order_id_never_mutates_business_state(): void
    {
        $this->assertUntrustedChargeFieldIsRejected('order_id', 'JSK-FOREIGN-ORDER');
    }

    public function test_untrusted_charge_amount_never_mutates_business_state(): void
    {
        $this->assertUntrustedChargeFieldIsRejected('gross_amount', '1.00');
    }

    public function test_untrusted_charge_currency_never_mutates_business_state(): void
    {
        $this->assertUntrustedChargeFieldIsRejected('currency', 'USD');
    }

    private function assertUntrustedChargeFieldIsRejected(string $invalidField, string $invalidValue): void
    {
        [$customer, $booking, $payment] = $this->createGatewayPayment();
        $response = $this->chargeResponse($this->expectedOrderId($booking, $payment));
        $response[$invalidField] = $invalidValue;

        Http::fake(function (Request $request) use ($response) {
            if ($request->url() === 'https://api.sandbox.midtrans.com/v2/charge') {
                return Http::response($response, 201);
            }

            return Http::response([
                'status_code' => '404',
                'status_message' => 'Transaction does not exist.',
            ], 404);
        });

        $this->actingAs($customer, 'sanctum')
            ->postJson(route('api.customer.bookings.payment.charge', $booking), [
                'payment_channel' => 'qris',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($invalidField);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('manual', $payment->fresh()->payment_method);
        $this->assertSame(
            $this->expectedOrderId($booking, $payment),
            $payment->fresh()->gatewayTransaction->provider_order_id
        );
        $this->assertSame('creating', $payment->fresh()->gatewayTransaction->provider_status);
        $this->assertNull($payment->fresh()->gatewayTransaction->raw_response);
        $this->assertSame('pending_payment', $booking->fresh()->status);
    }

    private function createGatewayPayment(): array
    {
        $provider = User::factory()->create(['role' => 'provider']);
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::query()->create([
            'booking_code' => 'PAY-' . Str::upper(Str::random(12)),
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

    private function expectedOrderId(Booking $booking, Payment $payment): string
    {
        return 'JSK-' . $booking->booking_code . '-' . $payment->id;
    }

    private function chargeResponse(string $orderId): array
    {
        return [
            'status_code' => '201',
            'status_message' => 'Success, GoPay transaction is created',
            'transaction_id' => 'payment-transaction-1',
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
        ];
    }
}
