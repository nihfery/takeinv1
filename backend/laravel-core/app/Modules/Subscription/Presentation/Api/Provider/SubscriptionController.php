<?php

namespace App\Modules\Subscription\Presentation\Api\Provider;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Gateways\Midtrans\MidtransService;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription;
use App\Modules\Subscription\Infrastructure\Persistence\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubscriptionController extends ApiController
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly RecordAuditEvent $audit,
    ) {
    }

    public function index(Request $request)
    {
        $providerId = $this->authorizeProviderOwner($request);
        $plans = SubscriptionPlan::where('is_active', true)->get();

        $profile = ProviderProfile::query()->where('user_id', $providerId)->first();
        $currentSubscription = $profile
            ? $profile->activeSubscription()->first()
            : null;

        return response()->json([
            'plans' => $plans,
            'current_subscription' => $currentSubscription,
        ]);
    }

    public function purchase(Request $request, SubscriptionPlan $plan)
    {
        $providerId = $this->authorizeProviderOwner($request);
        abort_unless($plan->is_active, 400, 'Paket berlangganan tidak tersedia.');
        $validated = $request->validate([
            'payment_channel' => ['nullable', Rule::in(MidtransService::CHANNELS)],
        ]);
        $channel = $validated['payment_channel'] ?? 'qris';
        $reconciledCheckout = $this->reconcileExistingCheckout(
            $request,
            $providerId,
            $plan
        );

        if ($reconciledCheckout) {
            return $this->checkoutResponse($reconciledCheckout);
        }

        $this->midtrans->ensureConfigured();

        [$subscription, $created] = DB::transaction(function () use ($providerId, $plan, $channel) {
            User::query()->whereKey($providerId)->lockForUpdate()->firstOrFail();

            $subscription = ProviderSubscription::query()
                ->where('provider_id', $providerId)
                ->where('payment_status', 'pending')
                ->where('subscription_status', 'inactive')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($subscription) {
                abort_unless(
                    (int) $subscription->plan_id === (int) $plan->id,
                    409,
                    'Selesaikan tagihan subscription yang masih pending sebelum memilih paket lain.'
                );
                abort_if(
                    $subscription->gateway_expires_at?->lte(now()),
                    409,
                    'Tagihan subscription lama perlu direkonsiliasi. Silakan coba lagi.'
                );

                return [$subscription, false];
            }

            ProviderSubscription::query()
                ->where('provider_id', $providerId)
                ->whereIn('payment_status', ['expired', 'failed', 'refunded'])
                ->where('subscription_status', 'inactive')
                ->whereNotNull('gateway_response')
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);

            $subscription = ProviderSubscription::query()->create([
                'provider_id' => $providerId,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'price' => $plan->price,
                'currency' => 'IDR',
                'duration_days' => $plan->duration_days,
                'max_branches' => $plan->max_branches,
                'payment_status' => 'pending',
                'subscription_status' => 'inactive',
                'midtrans_order_id' => 'SUB-' . Str::upper((string) Str::ulid()),
                'payment_channel' => $channel,
            ]);

            return [$subscription, true];
        });
        $channel = (string) ($subscription->payment_channel ?: $channel);

        if ($created) {
            $this->audit->execute(
                'subscription.purchase.created',
                ProviderSubscription::class,
                $subscription->id,
                after: [
                    'plan_id' => (int) $plan->id,
                    'payment_status' => $subscription->payment_status,
                    'subscription_status' => $subscription->subscription_status,
                    'payment_channel' => $subscription->payment_channel,
                ],
                actor: $request->user(),
                providerId: $providerId,
            );
        }

        $auditAction = null;

        if (! $subscription->gateway_response) {
            try {
                $response = $this->midtrans->chargeSubscription($subscription, $channel);
                $subscription = $this->midtrans->updateSubscriptionFromCharge(
                    $subscription,
                    $response,
                    $channel
                );
                $auditAction = 'subscription.gateway-charge.created';
            } catch (Throwable $chargeFailure) {
                try {
                    $response = $this->midtrans->status((string) $subscription->midtrans_order_id);
                    $subscription = $this->midtrans->updateSubscriptionFromStatus($subscription, $response);
                    $auditAction = 'subscription.gateway-charge.recovered';
                } catch (Throwable) {
                    throw $chargeFailure;
                }
            }
        }

        if ($auditAction) {
            $this->audit->execute(
                $auditAction,
                ProviderSubscription::class,
                $subscription->id,
                after: [
                    'plan_id' => (int) $plan->id,
                    'payment_status' => $subscription->payment_status,
                    'subscription_status' => $subscription->subscription_status,
                    'payment_channel' => $subscription->payment_channel,
                    'provider_status' => $subscription->midtrans_transaction_status,
                ],
                actor: $request->user(),
                providerId: $providerId,
            );
        }

        $subscription = $subscription->refresh();

        return $this->checkoutResponse($subscription);
    }

    private function reconcileExistingCheckout(
        Request $request,
        int $providerId,
        SubscriptionPlan $requestedPlan
    ): ?ProviderSubscription {
        $subscription = ProviderSubscription::query()
            ->where('provider_id', $providerId)
            ->where('payment_status', 'pending')
            ->where('subscription_status', 'inactive')
            ->latest('id')
            ->first();

        if (! $subscription) {
            return null;
        }

        $samePlan = (int) $subscription->plan_id === (int) $requestedPlan->id;
        $locallyExpired = $subscription->gateway_expires_at?->lte(now()) ?? false;

        if (! $locallyExpired) {
            abort_unless(
                $samePlan,
                409,
                'Selesaikan tagihan subscription yang masih pending sebelum memilih paket lain.'
            );

            return $subscription->gateway_response ? $subscription : null;
        }

        abort_unless(
            $subscription->midtrans_order_id && $subscription->gateway_response,
            409,
            'Tagihan lama belum dapat direkonsiliasi dengan Midtrans.'
        );

        $before = $this->subscriptionAuditState($subscription);

        try {
            $statusResponse = $this->midtrans->status((string) $subscription->midtrans_order_id);
            $subscription = $this->midtrans->updateSubscriptionFromStatus(
                $subscription,
                $statusResponse
            );

            if ($subscription->payment_status === 'pending') {
                try {
                    $this->midtrans->expire((string) $subscription->midtrans_order_id);
                } catch (Throwable) {
                    // A settlement can win the race with expire. The mandatory
                    // status read below is the authoritative conflict resolver.
                }

                $statusResponse = $this->midtrans->status(
                    (string) $subscription->midtrans_order_id
                );
                $subscription = $this->midtrans->updateSubscriptionFromStatus(
                    $subscription,
                    $statusResponse
                );
            }
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'payment' => 'Status tagihan subscription lama belum dapat diverifikasi. Coba lagi nanti.',
            ]);
        }

        $after = $this->subscriptionAuditState($subscription);

        if ($before !== $after) {
            $this->audit->execute(
                'subscription.gateway-status.reconciled',
                ProviderSubscription::class,
                $subscription->id,
                before: $before,
                after: $after,
                actor: $request->user(),
                providerId: $providerId,
            );
        }

        if (in_array($subscription->payment_status, ['pending', 'paid'], true)) {
            abort_unless(
                $samePlan,
                409,
                'Tagihan subscription sebelumnya telah diproses. Periksa paket aktif sebelum membeli lagi.'
            );

            return $subscription;
        }

        return null;
    }

    private function checkoutResponse(ProviderSubscription $subscription)
    {
        $subscription = $subscription->refresh();

        return response()->json([
            'message' => 'Berhasil membuat tagihan subscription',
            'subscription' => $subscription,
            'order_id' => $subscription->midtrans_order_id,
            'payment' => $this->midtrans->subscriptionCheckoutFields($subscription),
        ]);
    }

    private function subscriptionAuditState(ProviderSubscription $subscription): array
    {
        return [
            'payment_status' => (string) $subscription->payment_status,
            'subscription_status' => (string) $subscription->subscription_status,
            'provider_status' => $subscription->midtrans_transaction_status,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
        ];
    }

    private function authorizeProviderOwner(Request $request): int
    {
        $providerId = $this->providerId($request);

        abort_if(
            $this->isProviderBranchAccount($request),
            403,
            'Only the main provider account can manage subscriptions.'
        );

        return $providerId;
    }
}
