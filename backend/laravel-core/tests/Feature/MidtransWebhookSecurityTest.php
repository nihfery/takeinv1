<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MidtransWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'midtrans-webhook-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.midtrans.server_key' => self::SERVER_KEY,
            'services.midtrans.is_production' => false,
        ]);
        Http::preventStrayRequests();
    }

    public function test_invalid_signature_does_not_mutate_payment_state(): void
    {
        [$booking, $payment] = $this->createGatewayPayment();
        $payload = $this->signedNotification($payment->midtrans_order_id);
        $payload['signature_key'] = 'invalid';

        $this->postJson(route('api.midtrans.notification'), $payload)
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
    }

    public function test_callback_status_is_not_trusted_over_midtrans_status_api(): void
    {
        [$booking, $payment] = $this->createGatewayPayment();
        $payload = $this->signedNotification($payment->midtrans_order_id, transactionStatus: 'settlement');
        $this->fakeAuthoritativeStatus($payment->midtrans_order_id, transactionStatus: 'pending');

        $this->postJson(route('api.midtrans.notification'), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'OK');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->gatewayTransaction->provider_status);
    }

    public function test_delayed_pending_status_cannot_regress_paid_payment_or_confirmed_booking(): void
    {
        [$booking, $payment] = $this->createGatewayPayment('paid', 'confirmed');
        $paidAt = Carbon::parse('2026-08-10 09:00:00');
        $payment->update(['paid_at' => $paidAt]);
        $payment->gatewayTransaction()->update(['provider_status' => 'settlement']);
        $payload = $this->signedNotification($payment->midtrans_order_id, transactionStatus: 'pending');
        $this->fakeAuthoritativeStatus($payment->midtrans_order_id, transactionStatus: 'pending');

        $this->postJson(route('api.midtrans.notification'), $payload)
            ->assertOk();

        $payment = $payment->fresh();
        $this->assertSame('paid', $payment->status);
        $this->assertTrue($payment->paid_at->equalTo($paidAt));
        $this->assertSame('settlement', $payment->gatewayTransaction->provider_status);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_duplicate_paid_payment_notification_keeps_paid_timestamp_and_booking_state(): void
    {
        $this->travelTo(Carbon::parse('2026-08-10 09:00:00'));
        [$booking, $payment] = $this->createGatewayPayment();
        $payload = $this->signedNotification($payment->midtrans_order_id);
        $this->fakeAuthoritativeStatus($payment->midtrans_order_id);

        $this->postJson(route('api.midtrans.notification'), $payload)->assertOk();
        $paidAt = $payment->fresh()->paid_at;

        $this->travel(10)->minutes();
        $this->postJson(route('api.midtrans.notification'), $payload)->assertOk();

        $this->assertTrue($payment->fresh()->paid_at->equalTo($paidAt));
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_payment_amount_mismatch_cannot_confirm_booking(): void
    {
        [$booking, $payment] = $this->createGatewayPayment();
        $payload = $this->signedNotification($payment->midtrans_order_id, '1.00');
        $this->fakeAuthoritativeStatus($payment->midtrans_order_id, '1.00');

        $this->postJson(route('api.midtrans.notification'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gross_amount');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
    }

    public function test_payment_currency_mismatch_cannot_confirm_booking(): void
    {
        [$booking, $payment] = $this->createGatewayPayment();
        $payload = $this->signedNotification($payment->midtrans_order_id);
        $this->fakeAuthoritativeStatus($payment->midtrans_order_id, currency: 'USD');

        $this->postJson(route('api.midtrans.notification'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
    }

    public function test_duplicate_subscription_settlement_does_not_extend_or_reactivate_period(): void
    {
        $this->travelTo(Carbon::parse('2026-08-10 09:00:00'));
        $subscription = $this->createSubscription();
        $payload = $this->signedNotification($subscription->midtrans_order_id);
        $this->fakeAuthoritativeStatus($subscription->midtrans_order_id);

        $this->postJson(route('api.midtrans.notification'), $payload)->assertOk();
        $firstActivation = $subscription->fresh();
        $startsAt = $firstActivation->starts_at;
        $endsAt = $firstActivation->ends_at;
        $paidAt = $firstActivation->paid_at;

        $this->travel(10)->days();
        $this->postJson(route('api.midtrans.notification'), $payload)->assertOk();

        $subscription = $subscription->fresh();
        $this->assertSame('paid', $subscription->payment_status);
        $this->assertSame('active', $subscription->subscription_status);
        $this->assertTrue($subscription->starts_at->equalTo($startsAt));
        $this->assertTrue($subscription->ends_at->equalTo($endsAt));
        $this->assertTrue($subscription->paid_at->equalTo($paidAt));
    }

    public function test_subscription_amount_mismatch_cannot_activate_subscription(): void
    {
        $subscription = $this->createSubscription();
        $payload = $this->signedNotification($subscription->midtrans_order_id, '1.00');
        $this->fakeAuthoritativeStatus($subscription->midtrans_order_id, '1.00');

        $this->postJson(route('api.midtrans.notification'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gross_amount');

        $subscription = $subscription->fresh();
        $this->assertSame('pending', $subscription->payment_status);
        $this->assertSame('inactive', $subscription->subscription_status);
        $this->assertNull($subscription->starts_at);
        $this->assertNull($subscription->ends_at);
        $this->assertNull($subscription->paid_at);
    }

    public function test_delayed_pending_status_cannot_deactivate_paid_subscription(): void
    {
        $startsAt = Carbon::parse('2026-08-01 09:00:00');
        $endsAt = Carbon::parse('2026-08-31 09:00:00');
        $paidAt = Carbon::parse('2026-08-01 09:00:00');
        $subscription = $this->createSubscription();
        $subscription->update([
            'payment_status' => 'paid',
            'subscription_status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'paid_at' => $paidAt,
        ]);
        $payload = $this->signedNotification($subscription->midtrans_order_id, transactionStatus: 'pending');
        $this->fakeAuthoritativeStatus($subscription->midtrans_order_id, transactionStatus: 'pending');

        $this->postJson(route('api.midtrans.notification'), $payload)->assertOk();

        $subscription = $subscription->fresh();
        $this->assertSame('paid', $subscription->payment_status);
        $this->assertSame('active', $subscription->subscription_status);
        $this->assertTrue($subscription->starts_at->equalTo($startsAt));
        $this->assertTrue($subscription->ends_at->equalTo($endsAt));
        $this->assertTrue($subscription->paid_at->equalTo($paidAt));
    }

    public function test_authoritative_subscription_refund_deactivates_access_without_moving_period(): void
    {
        $startsAt = Carbon::parse('2026-08-01 09:00:00');
        $endsAt = Carbon::parse('2026-08-31 09:00:00');
        $subscription = $this->createSubscription();
        $subscription->update([
            'payment_status' => 'paid',
            'subscription_status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'paid_at' => $startsAt,
        ]);
        $payload = $this->signedNotification($subscription->midtrans_order_id, transactionStatus: 'refund');
        $this->fakeAuthoritativeStatus($subscription->midtrans_order_id, transactionStatus: 'refund');

        $this->postJson(route('api.midtrans.notification'), $payload)->assertOk();

        $subscription = $subscription->fresh();
        $this->assertSame('refunded', $subscription->payment_status);
        $this->assertSame('inactive', $subscription->subscription_status);
        $this->assertTrue($subscription->starts_at->equalTo($startsAt));
        $this->assertTrue($subscription->ends_at->equalTo($endsAt));
    }

    public function test_late_settlement_is_recorded_for_manual_resolution_without_stacking_entitlement(): void
    {
        $subscription = $this->createSubscription();
        $subscription->update([
            'payment_status' => 'expired',
            'subscription_status' => 'inactive',
            'midtrans_transaction_status' => 'expire',
            'superseded_at' => now(),
        ]);
        $payload = $this->signedNotification($subscription->midtrans_order_id);
        $this->fakeAuthoritativeStatus($subscription->midtrans_order_id);

        $this->postJson(route('api.midtrans.notification'), $payload)->assertOk();

        $subscription = $subscription->fresh();
        $this->assertSame('paid', $subscription->payment_status);
        $this->assertSame('inactive', $subscription->subscription_status);
        $this->assertSame('settlement', $subscription->midtrans_transaction_status);
        $this->assertNull($subscription->starts_at);
        $this->assertNull($subscription->ends_at);
        $this->assertNotNull($subscription->paid_at);
        $this->assertNotNull($subscription->late_settlement_at);
        $this->assertNotNull($subscription->gateway_notification);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'subscription.webhook.late-settlement-requires-resolution',
            'resource_id' => (string) $subscription->id,
            'provider_id' => $subscription->provider_id,
        ]);
    }

    private function createGatewayPayment(
        string $paymentStatus = 'pending',
        string $bookingStatus = 'pending_payment'
    ): array
    {
        $provider = User::factory()->create(['role' => 'provider']);
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::query()->create([
            'booking_code' => 'WEBHOOK-' . Str::upper(Str::random(12)),
            'booking_date' => now()->addDay()->toDateString(),
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'booking_type' => 'scheduled',
            'total_duration' => 60,
            'total_price' => 100000,
            'participant_count' => 1,
            'status' => $bookingStatus,
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'full_payment',
            'amount' => 100000,
            'status' => $paymentStatus,
            'payment_method' => 'midtrans',
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
        ]);
        $payment->gatewayTransaction()->create([
            'gateway' => 'midtrans',
            'provider_order_id' => 'JSK-WEBHOOK-' . Str::upper(Str::random(12)),
            'provider_transaction_id' => 'transaction-' . Str::uuid(),
            'provider_status' => $paymentStatus === 'paid' ? 'settlement' : 'pending',
        ]);

        return [$booking, $payment->refresh()];
    }

    private function createSubscription(): ProviderSubscription
    {
        $provider = User::factory()->create(['role' => 'provider']);

        return ProviderSubscription::query()->create([
            'provider_id' => $provider->id,
            'plan_name' => 'Webhook Security Plan',
            'price' => 100000,
            'currency' => 'IDR',
            'duration_days' => 30,
            'max_branches' => 3,
            'payment_status' => 'pending',
            'subscription_status' => 'inactive',
            'midtrans_order_id' => 'SUB-WEBHOOK-' . Str::upper(Str::random(12)),
        ]);
    }

    private function signedNotification(
        string $orderId,
        string $grossAmount = '100000.00',
        string $transactionStatus = 'settlement'
    ): array
    {
        $payload = [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => $transactionStatus,
            'fraud_status' => 'accept',
            'transaction_id' => 'transaction-' . Str::uuid(),
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . self::SERVER_KEY
        );

        return $payload;
    }

    private function fakeAuthoritativeStatus(
        string $orderId,
        string $grossAmount = '100000.00',
        string $transactionStatus = 'settlement',
        string $currency = 'IDR',
    ): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'order_id' => $orderId,
                'status_code' => $transactionStatus === 'pending' ? '201' : '200',
                'gross_amount' => $grossAmount,
                'currency' => $currency,
                'transaction_status' => $transactionStatus,
                'fraud_status' => 'accept',
                'transaction_id' => 'transaction-authoritative',
            ], 200),
        ]);
    }
}
