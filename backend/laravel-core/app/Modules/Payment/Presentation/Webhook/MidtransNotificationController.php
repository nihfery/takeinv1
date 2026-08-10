<?php

namespace App\Modules\Payment\Presentation\Webhook;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Payment\Infrastructure\Gateways\Midtrans\MidtransService;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransNotificationController extends ApiController
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly RecordAuditEvent $audit,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        abort_unless($this->midtrans->verifySignature($payload), 403, 'Invalid Midtrans signature.');

        $orderId = trim((string) $payload['order_id']);
        abort_if($orderId === '', 422, 'Invalid Midtrans order ID.');

        $verifiedPayload = $this->midtrans->status($orderId);
        $verifiedOrderId = isset($verifiedPayload['order_id']) && is_scalar($verifiedPayload['order_id'])
            ? (string) $verifiedPayload['order_id']
            : '';

        abort_unless(
            $verifiedOrderId !== '' && hash_equals($orderId, $verifiedOrderId),
            422,
            'Midtrans order ID did not match the notification.'
        );

        if (str_starts_with($orderId, 'SUB-')) {
            return $this->handleSubscriptionNotification($verifiedPayload, $orderId, $payload);
        }

        $payment = Payment::query()
            ->whereHas(
                'gatewayTransaction',
                fn ($query) => $query
                    ->where('gateway', 'midtrans')
                    ->where('provider_order_id', $orderId)
            )
            ->first();

        abort_unless($payment, 404, 'Payment not found.');

        $before = [
            'payment_status' => (string) $payment->status,
            'booking_status' => (string) $payment->booking?->status,
        ];
        $payment = $this->midtrans->updatePaymentFromStatus($payment, $verifiedPayload, true, $payload);

        $this->audit->execute(
            'payment.webhook.processed',
            Payment::class,
            $payment->id,
            before: $before,
            after: [
                'payment_status' => (string) $payment->status,
                'booking_status' => (string) $payment->booking?->status,
            ],
            providerId: $payment->booking?->provider_id,
        );

        return response()->json(['message' => 'OK']);
    }

    private function handleSubscriptionNotification(
        array $payload,
        string $orderId,
        array $rawNotification
    ): JsonResponse
    {
        $subscription = ProviderSubscription::query()
            ->where('midtrans_order_id', $orderId)
            ->first();

        abort_unless($subscription, 404, 'Subscription payment not found.');
        $before = [
            'payment_status' => (string) $subscription->payment_status,
            'subscription_status' => (string) $subscription->subscription_status,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
        ];
        $subscription = $this->midtrans->updateSubscriptionFromStatus(
            $subscription,
            $payload,
            true,
            $rawNotification
        );
        $this->recordSubscriptionWebhook($subscription, $before);

        return response()->json(['message' => 'OK']);
    }

    private function recordSubscriptionWebhook(ProviderSubscription $subscription, array $before): void
    {
        $action = $subscription->late_settlement_at
            && ($before['payment_status'] ?? null) !== 'paid'
                ? 'subscription.webhook.late-settlement-requires-resolution'
                : 'subscription.webhook.processed';

        $this->audit->execute(
            $action,
            ProviderSubscription::class,
            $subscription->id,
            before: $before,
            after: [
                'payment_status' => (string) $subscription->payment_status,
                'subscription_status' => (string) $subscription->subscription_status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'late_settlement_at' => $subscription->late_settlement_at?->toIso8601String(),
            ],
            providerId: (int) $subscription->provider_id,
        );
    }
}
