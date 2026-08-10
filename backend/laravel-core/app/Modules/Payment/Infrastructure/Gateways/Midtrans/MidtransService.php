<?php

namespace App\Modules\Payment\Infrastructure\Gateways\Midtrans;

use App\Modules\Booking\Application\Services\BookingFlowService;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Subscription\Infrastructure\Persistence\Models\ProviderSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MidtransService
{
    public const PAYMENT_EXPIRY_MINUTES = 7;

    public const CHANNELS = [
        'qris',
        'bca_va',
        'bni_va',
        'bri_va',
        'permata_va',
        'cimb_va',
        'mandiri_bill',
    ];

    public function ensureConfigured(): void
    {
        $this->serverKey();
    }

    public function charge(Payment $payment, string $channel): array
    {
        $serverKey = $this->serverKey();
        $payload = $this->chargePayload($payment, $channel);

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($this->baseUrl() . '/v2/charge', $payload);

        $data = $response->json() ?: [];

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => $data['status_message'] ?? 'Transaksi Midtrans gagal dibuat.',
            ]);
        }

        return $data;
    }

    public function chargeSubscription(ProviderSubscription $subscription, string $channel): array
    {
        $serverKey = $this->serverKey();
        $payload = $this->subscriptionChargePayload($subscription, $channel);

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($this->baseUrl() . '/v2/charge', $payload);

        $data = $response->json() ?: [];

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => $data['status_message'] ?? 'Transaksi subscription Midtrans gagal dibuat.',
            ]);
        }

        return $data;
    }

    public function status(string $orderId): array
    {
        $response = Http::withBasicAuth($this->serverKey(), '')
            ->acceptJson()
            ->timeout(20)
            ->get($this->baseUrl() . '/v2/' . rawurlencode($orderId) . '/status');

        $data = $response->json() ?: [];

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => $data['status_message'] ?? 'Status pembayaran belum bisa dicek.',
            ]);
        }

        return $data;
    }

    public function expire(string $orderId): array
    {
        $response = Http::withBasicAuth($this->serverKey(), '')
            ->acceptJson()
            ->timeout(20)
            ->post($this->baseUrl() . '/v2/' . rawurlencode($orderId) . '/expire');

        $data = $response->json() ?: [];

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => $data['status_message'] ?? 'Transaksi Midtrans belum bisa di-expire.',
            ]);
        }

        return $data;
    }

    public function displayFields(array $response, string $channel): array
    {
        $actions = collect($response['actions'] ?? []);
        $qrAction = $actions->firstWhere('name', 'generate-qr-code-v2')
            ?: $actions->firstWhere('name', 'generate-qr-code');
        $deeplinkAction = $actions->firstWhere('name', 'deeplink-redirect');
        $qrUrl = is_array($qrAction) ? ($qrAction['url'] ?? null) : null;
        $deeplinkUrl = is_array($deeplinkAction) ? ($deeplinkAction['url'] ?? null) : null;
        $codeLabel = null;
        $paymentCode = null;
        $billerCode = null;

        if (! empty($response['va_numbers'][0])) {
            $bank = strtoupper((string) ($response['va_numbers'][0]['bank'] ?? 'VA'));
            $codeLabel = "{$bank} Virtual Account";
            $paymentCode = $response['va_numbers'][0]['va_number'] ?? null;
        }

        if (! empty($response['permata_va_number'])) {
            $codeLabel = 'Permata Virtual Account';
            $paymentCode = $response['permata_va_number'];
        }

        if (! empty($response['bill_key']) || ! empty($response['biller_code'])) {
            $codeLabel = 'Mandiri Bill Key';
            $paymentCode = $response['bill_key'] ?? null;
            $billerCode = $response['biller_code'] ?? null;
        }

        if ($channel === 'qris') {
            $codeLabel = 'QRIS';
        }

        return [
            'gateway' => 'midtrans',
            'payment_channel' => $channel,
            'provider_order_id' => $response['order_id'] ?? null,
            'provider_transaction_id' => $response['transaction_id'] ?? null,
            'provider_status' => $response['transaction_status'] ?? null,
            'fraud_status' => $response['fraud_status'] ?? null,
            'payment_code_label' => $codeLabel,
            'payment_code' => $paymentCode,
            'biller_code' => $billerCode,
            'qr_url' => $qrUrl,
            'deeplink_url' => $deeplinkUrl,
            'expires_at' => $this->parseMidtransTime($response['expiry_time'] ?? null)
                ?: now()->addMinutes(self::PAYMENT_EXPIRY_MINUTES),
            'raw_response' => $response,
        ];
    }

    public function updatePaymentFromCharge(Payment $payment, array $response, string $channel): Payment
    {
        $status = $this->verifiedPaymentStatus($response);

        return DB::transaction(function () use ($payment, $response, $channel, $status) {
            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $gateway = $payment->gatewayTransaction()->lockForUpdate()->first()
                ?: $payment->gatewayTransaction()->make();
            $expectedOrderId = $gateway->provider_order_id
                ?: $this->makeOrderId($payment, $payment->booking()->firstOrFail());

            $this->assertMatchingOrderId($expectedOrderId, $response['order_id'] ?? null);
            $this->assertMatchingAmount($payment->amount, $response['gross_amount'] ?? null);
            $this->assertMatchingCurrency('IDR', $response['currency'] ?? null);

            $applyStatus = $this->canApplyPaymentStatus((string) $payment->status, $status);
            $effectiveStatus = $applyStatus ? $status : (string) $payment->status;
            $gatewayUpdates = $this->displayFields($response, $channel);

            if (! $applyStatus) {
                $gatewayUpdates['provider_status'] = $gateway->provider_status;
                $gatewayUpdates['fraud_status'] = $gateway->fraud_status;
            }

            $payment->update([
                'payment_method' => 'midtrans',
                'status' => $effectiveStatus,
                'paid_at' => $effectiveStatus === 'paid'
                    ? ($payment->paid_at ?: now())
                    : $payment->paid_at,
            ]);
            $gateway->fill($gatewayUpdates)->save();

            $this->updateBookingPaymentState($payment, $effectiveStatus);

            return $payment->refresh()->load('gatewayTransaction');
        });
    }

    public function updatePaymentFromStatus(
        Payment $payment,
        array $response,
        bool $notification = false,
        ?array $rawNotification = null
    ): Payment
    {
        $status = $this->verifiedPaymentStatus($response);

        return DB::transaction(function () use ($payment, $response, $notification, $rawNotification, $status) {
            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $gateway = $payment->gatewayTransaction()->lockForUpdate()->first()
                ?: $payment->gatewayTransaction()->make();
            $expectedOrderId = $gateway->provider_order_id
                ?: $this->makeOrderId($payment, $payment->booking()->firstOrFail());

            $this->assertMatchingOrderId($expectedOrderId, $response['order_id'] ?? null);
            $this->assertMatchingAmount($payment->amount, $response['gross_amount'] ?? null);
            $this->assertMatchingCurrency('IDR', $response['currency'] ?? null);

            $applyStatus = $this->canApplyPaymentStatus((string) $payment->status, $status);
            $effectiveStatus = $applyStatus ? $status : (string) $payment->status;

            $payment->update([
                'payment_method' => 'midtrans',
                'status' => $effectiveStatus,
                'paid_at' => $effectiveStatus === 'paid' ? ($payment->paid_at ?: now()) : $payment->paid_at,
            ]);
            $updates = [
                'gateway' => 'midtrans',
                'provider_order_id' => $expectedOrderId,
                'provider_status' => $applyStatus
                    ? ($response['transaction_status'] ?? $gateway->provider_status)
                    : $gateway->provider_status,
                'fraud_status' => $applyStatus
                    ? ($response['fraud_status'] ?? $gateway->fraud_status)
                    : $gateway->fraud_status,
            ];

            if (! $gateway->raw_response) {
                $display = $this->displayFields(
                    $response,
                    (string) ($gateway->payment_channel ?: 'qris')
                );

                foreach ([
                    'payment_channel',
                    'payment_code_label',
                    'payment_code',
                    'biller_code',
                    'qr_url',
                    'deeplink_url',
                ] as $field) {
                    if ($display[$field] !== null) {
                        $updates[$field] = $display[$field];
                    }
                }

                $updates['raw_response'] = $response;
            }

            if (! empty($response['expiry_time'])) {
                $updates['expires_at'] = $this->parseMidtransTime($response['expiry_time']);
            }

            if (! empty($response['transaction_id'])) {
                $updates['provider_transaction_id'] = $response['transaction_id'];
            }

            if ($notification) {
                $updates['raw_notification'] = $rawNotification ?? $response;
            }

            $gateway->fill($updates)->save();
            $this->updateBookingPaymentState($payment, $effectiveStatus);

            return $payment->refresh()->load('gatewayTransaction');
        });
    }

    public function paymentOrderId(Payment $payment): string
    {
        $payment->loadMissing('booking', 'gatewayTransaction');

        if ($payment->gatewayTransaction?->provider_order_id) {
            return (string) $payment->gatewayTransaction->provider_order_id;
        }

        if (! $payment->booking) {
            throw ValidationException::withMessages([
                'payment' => 'Booking untuk pembayaran ini tidak ditemukan.',
            ]);
        }

        return $this->makeOrderId($payment, $payment->booking);
    }

    public function reservePaymentOrder(Payment $payment, string $channel): Payment
    {
        $this->ensureConfigured();

        if (! in_array($channel, self::CHANNELS, true)) {
            throw ValidationException::withMessages([
                'payment_channel' => 'Metode pembayaran tidak tersedia.',
            ]);
        }

        return DB::transaction(function () use ($payment, $channel) {
            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($payment->status, ['unpaid', 'pending'], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Tagihan ini tidak dapat dibuat ulang.',
                ]);
            }

            $booking = $payment->booking()->firstOrFail();
            $gateway = $payment->gatewayTransaction()->lockForUpdate()->first()
                ?: $payment->gatewayTransaction()->make();

            if ($gateway->provider_order_id
                && $gateway->payment_channel
                && $gateway->payment_channel !== $channel) {
                throw ValidationException::withMessages([
                    'payment_channel' => 'Metode pembayaran tidak dapat diubah setelah transaksi Midtrans dibuat.',
                ]);
            }

            $gateway->fill([
                'gateway' => 'midtrans',
                'payment_channel' => $gateway->payment_channel ?: $channel,
                'provider_order_id' => $gateway->provider_order_id
                    ?: $this->makeOrderId($payment, $booking),
                'provider_status' => $gateway->provider_status ?: 'creating',
                'expires_at' => $gateway->expires_at ?: now()->addMinutes(self::PAYMENT_EXPIRY_MINUTES),
            ])->save();

            return $payment->refresh()->load('gatewayTransaction');
        });
    }

    public function updateSubscriptionFromCharge(
        ProviderSubscription $subscription,
        array $response,
        string $channel
    ): ProviderSubscription {
        return $this->updateSubscriptionTransaction(
            $subscription,
            $response,
            $channel,
            storeChargeResponse: true,
        );
    }

    public function updateSubscriptionFromStatus(
        ProviderSubscription $subscription,
        array $response,
        bool $notification = false,
        ?array $rawNotification = null
    ): ProviderSubscription {
        return $this->updateSubscriptionTransaction(
            $subscription,
            $response,
            (string) ($subscription->payment_channel ?: 'qris'),
            notification: $notification,
            rawNotification: $rawNotification,
        );
    }

    public function subscriptionCheckoutFields(ProviderSubscription $subscription): array
    {
        return [
            'gateway' => 'midtrans',
            'payment_channel' => $subscription->payment_channel,
            'provider_order_id' => $subscription->midtrans_order_id,
            'provider_transaction_id' => $subscription->midtrans_transaction_id,
            'provider_status' => $subscription->midtrans_transaction_status,
            'fraud_status' => $subscription->fraud_status,
            'payment_code_label' => $subscription->payment_code_label,
            'payment_code' => $subscription->payment_code,
            'biller_code' => $subscription->biller_code,
            'qr_url' => $subscription->qr_url,
            'deeplink_url' => $subscription->deeplink_url,
            'expires_at' => $subscription->gateway_expires_at,
        ];
    }

    private function updateSubscriptionTransaction(
        ProviderSubscription $subscription,
        array $response,
        string $channel,
        bool $storeChargeResponse = false,
        bool $notification = false,
        ?array $rawNotification = null
    ): ProviderSubscription {
        $status = $this->verifiedPaymentStatus($response);

        return DB::transaction(function () use (
            $subscription,
            $response,
            $channel,
            $status,
            $storeChargeResponse,
            $notification,
            $rawNotification
        ) {
            $subscription = ProviderSubscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMatchingOrderId(
                $subscription->midtrans_order_id,
                $response['order_id'] ?? null
            );
            $this->assertMatchingAmount($subscription->price, $response['gross_amount'] ?? null);
            $this->assertMatchingCurrency($subscription->currency, $response['currency'] ?? null);

            $supersededSettlement = $subscription->superseded_at !== null && $status === 'paid';
            $applyStatus = $this->canApplySubscriptionPaymentStatus(
                (string) $subscription->payment_status,
                $status
            );
            $wasPaid = $subscription->payment_status === 'paid';
            $gatewayFields = $this->subscriptionGatewayFields($response, $channel);

            if (! $storeChargeResponse && empty($response['expiry_time'])) {
                unset($gatewayFields['gateway_expires_at']);
            }

            if (! $applyStatus) {
                $gatewayFields['midtrans_transaction_status'] = $subscription->midtrans_transaction_status;
                $gatewayFields['fraud_status'] = $subscription->fraud_status;
            }

            $updates = $gatewayFields;
            $updates['gateway_response'] = $storeChargeResponse || ! $subscription->gateway_response
                ? $response
                : $subscription->gateway_response;

            if ($notification) {
                $updates['gateway_notification'] = $rawNotification ?? $response;
            }

            if ($applyStatus) {
                $updates['payment_status'] = $status;
                $updates['paid_at'] = $status === 'paid'
                    ? ($subscription->paid_at ?: now())
                    : $subscription->paid_at;

                if ($status === 'paid' && ! $wasPaid) {
                    if ($supersededSettlement) {
                        // Preserve the received money without silently stacking
                        // entitlement periods. Operations can resolve/refund the
                        // explicitly flagged late settlement from the audit trail.
                        $updates['subscription_status'] = 'inactive';
                        $updates['late_settlement_at'] = now();
                    } else {
                        $startsAt = ! $subscription->starts_at || $subscription->starts_at->isPast()
                            ? now()
                            : $subscription->starts_at;

                        $updates['subscription_status'] = 'active';
                        $updates['late_settlement_at'] = null;
                        $updates['starts_at'] = $startsAt;
                        $updates['ends_at'] = Carbon::parse($startsAt)
                            ->addDays((int) $subscription->duration_days);
                    }
                } elseif (in_array($status, ['failed', 'expired', 'canceled', 'refunded'], true)) {
                    $updates['subscription_status'] = 'inactive';
                }
            }

            $subscription->update($updates);

            return $subscription->refresh();
        });
    }

    public function isPaymentLocallyExpired(Payment $payment): bool
    {
        return in_array($payment->status, ['unpaid', 'pending'], true)
            && $payment->expiry_time
            && $payment->expiry_time->lte(now());
    }

    public function expirePayment(Payment $payment, ?array $response = null, bool $notification = false): Payment
    {
        if ($response !== null) {
            return $this->updatePaymentFromStatus(
                $payment,
                $response,
                $notification,
                $notification ? $response : null
            );
        }

        return DB::transaction(function () use ($payment) {
            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $gateway = $payment->gatewayTransaction()->lockForUpdate()->first()
                ?: $payment->gatewayTransaction()->make();

            if (! in_array($payment->status, ['unpaid', 'pending'], true)
                || $gateway->provider_order_id) {
                return $payment->refresh()->load('gatewayTransaction');
            }

            $payment->update(['status' => 'expired']);
            $gateway->fill([
                'gateway' => 'midtrans',
                'provider_status' => 'expire',
            ])->save();
            $this->updateBookingPaymentState($payment, 'expired');

            return $payment->refresh()->load('gatewayTransaction');
        });
    }

    public function expirePaymentIfOverdue(Payment $payment): Payment
    {
        $payment = Payment::query()
            ->with('gatewayTransaction')
            ->findOrFail($payment->getKey());

        if (! $this->isPaymentLocallyExpired($payment)) {
            return $payment;
        }

        $orderId = $payment->gatewayTransaction?->provider_order_id;

        if (! $orderId) {
            return $this->expirePayment($payment);
        }

        try {
            $response = $this->status((string) $orderId);
            $payment = $this->updatePaymentFromStatus($payment, $response);

            if ($payment->status === 'pending' && $this->isPaymentLocallyExpired($payment)) {
                try {
                    $this->expire((string) $orderId);
                } catch (\Throwable) {
                    // Settlement may win the race with expire. The mandatory
                    // status read below is the authoritative conflict resolver.
                }

                $response = $this->status((string) $orderId);
                $payment = $this->updatePaymentFromStatus($payment, $response);
            }

            return $payment;
        } catch (\Throwable) {
            // Once Midtrans knows the order, a local clock must never release
            // the slot without an authoritative state. Keep it pending so a
            // later poll/webhook can reconcile it safely.
            return $payment->refresh()->load('gatewayTransaction');
        }
    }

    public function expireOverduePaymentsForCustomer(int $customerId): int
    {
        $payments = Payment::query()
            ->whereIn('status', ['unpaid', 'pending'])
            ->whereHas('gatewayTransaction', fn ($query) => $query
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()))
            ->whereHas('booking', fn ($query) => $query->where('customer_id', $customerId))
            ->with(['booking', 'gatewayTransaction'])
            ->get();

        return $payments->reduce(function (int $expired, Payment $payment): int {
            $payment = $this->expirePaymentIfOverdue($payment);

            return $expired + ($payment->status === 'expired' ? 1 : 0);
        }, 0);
    }

    public function paymentStatus(?string $transactionStatus = null, ?string $fraudStatus = null): string
    {
        return match ($transactionStatus) {
            'settlement' => 'paid',
            'capture' => match ($fraudStatus) {
                'challenge' => 'pending',
                'deny' => 'failed',
                default => 'paid',
            },
            'pending' => 'pending',
            'refund', 'partial_refund' => 'refunded',
            'expire' => 'expired',
            'cancel', 'deny', 'failure' => 'failed',
            default => 'pending',
        };
    }

    public function verifySignature(array $payload): bool
    {
        foreach (['signature_key', 'order_id', 'status_code', 'gross_amount'] as $field) {
            if (! isset($payload[$field]) || ! is_scalar($payload[$field]) || is_bool($payload[$field])) {
                return false;
            }
        }

        $signature = (string) $payload['signature_key'];

        if ($signature === '') {
            return false;
        }

        $source = (string) $payload['order_id']
            . (string) $payload['status_code']
            . (string) $payload['gross_amount']
            . $this->serverKey();

        return hash_equals(hash('sha512', $source), $signature);
    }

    public function assertMatchingOrderId(mixed $expected, mixed $actual): void
    {
        if (! is_scalar($expected) || is_bool($expected) || ! is_scalar($actual) || is_bool($actual)) {
            throw ValidationException::withMessages([
                'order_id' => 'Order ID Midtrans tidak valid.',
            ]);
        }

        $expectedOrderId = trim((string) $expected);
        $actualOrderId = trim((string) $actual);

        if ($expectedOrderId === '' || ! hash_equals($expectedOrderId, $actualOrderId)) {
            throw ValidationException::withMessages([
                'order_id' => 'Order ID Midtrans tidak sesuai dengan tagihan.',
            ]);
        }
    }

    public function assertMatchingAmount(mixed $expected, mixed $actual): void
    {
        $expectedAmount = $this->canonicalAmount($expected);
        $actualAmount = $this->canonicalAmount($actual);

        if ($expectedAmount === null || $actualAmount === null || ! hash_equals($expectedAmount, $actualAmount)) {
            throw ValidationException::withMessages([
                'gross_amount' => 'Nominal notifikasi Midtrans tidak sesuai dengan tagihan.',
            ]);
        }
    }

    public function assertMatchingCurrency(mixed $expected, mixed $actual): void
    {
        if (! is_scalar($expected) || is_bool($expected) || ! is_scalar($actual) || is_bool($actual)) {
            throw ValidationException::withMessages([
                'currency' => 'Mata uang notifikasi Midtrans tidak valid.',
            ]);
        }

        $expectedCurrency = strtoupper(trim((string) $expected));
        $actualCurrency = strtoupper(trim((string) $actual));

        if ($expectedCurrency === '' || ! hash_equals($expectedCurrency, $actualCurrency)) {
            throw ValidationException::withMessages([
                'currency' => 'Mata uang notifikasi Midtrans tidak sesuai dengan tagihan.',
            ]);
        }
    }

    public function verifiedPaymentStatus(array $response): string
    {
        $status = $this->paymentStatus(
            isset($response['transaction_status']) && is_scalar($response['transaction_status'])
                ? strtolower((string) $response['transaction_status'])
                : null,
            isset($response['fraud_status']) && is_scalar($response['fraud_status'])
                ? strtolower((string) $response['fraud_status'])
                : null
        );

        if ($status !== 'paid') {
            return $status;
        }

        $statusCode = isset($response['status_code']) && is_scalar($response['status_code'])
            ? (string) $response['status_code']
            : '';
        $fraudStatus = isset($response['fraud_status']) && is_scalar($response['fraud_status'])
            ? strtolower((string) $response['fraud_status'])
            : '';

        if ($statusCode !== '200') {
            return 'pending';
        }

        return $fraudStatus !== '' && $fraudStatus !== 'accept' ? 'pending' : 'paid';
    }

    private function updateBookingPaymentState(Payment $payment, string $paymentStatus): void
    {
        $booking = Booking::query()
            ->whereKey($payment->booking_id)
            ->lockForUpdate()
            ->first();

        if (! $booking) {
            return;
        }

        $updates = [];

        if ($payment->payment_type !== 'pay_at_salon') {
            if ($paymentStatus === 'pending' && in_array($booking->status, ['open', 'pending', 'pending_payment'], true)) {
                $updates['status'] = 'pending_payment';
            }

            if ($paymentStatus === 'paid' && $booking->status === 'pending_payment') {
                $updates['status'] = in_array($booking->booking_type, ['queue', 'walk_in'], true)
                    ? 'waiting'
                    : 'confirmed';
            }

            if (in_array($paymentStatus, ['expired', 'failed'], true) && $booking->status === 'pending_payment') {
                $updates['status'] = BookingFlowService::STATUS_PAYMENT_EXPIRED;
                $updates['hold_expires_at'] = null;
                $updates['expired_at'] = now();
            }
        }

        if ($updates !== []) {
            $booking->update($updates);
        }
    }

    private function canApplyPaymentStatus(string $current, string $incoming): bool
    {
        if ($current === $incoming) {
            return true;
        }

        if ($current === 'refunded') {
            return false;
        }

        if ($current === 'paid') {
            return $incoming === 'refunded';
        }

        if (in_array($current, ['failed', 'expired'], true) && $incoming === 'pending') {
            return false;
        }

        return true;
    }

    private function canApplySubscriptionPaymentStatus(string $current, string $incoming): bool
    {
        if ($current === $incoming) {
            return true;
        }

        if ($current === 'refunded') {
            return false;
        }

        if ($current === 'paid') {
            return $incoming === 'refunded';
        }

        if (in_array($current, ['failed', 'expired'], true) && $incoming === 'pending') {
            return false;
        }

        return true;
    }

    private function canonicalAmount(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (! preg_match('/^\+?(\d+)(?:\.(\d{1,2}))?$/D', $value, $matches)) {
            return null;
        }

        $whole = ltrim($matches[1], '0');
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ($whole === '' ? '0' : $whole).'.'.$fraction;
    }

    private function subscriptionGatewayFields(array $response, string $channel): array
    {
        $display = $this->displayFields($response, $channel);

        return array_filter([
            'payment_channel' => $display['payment_channel'],
            'midtrans_transaction_id' => $display['provider_transaction_id'],
            'midtrans_transaction_status' => $display['provider_status'],
            'fraud_status' => $display['fraud_status'],
            'payment_code_label' => $display['payment_code_label'],
            'payment_code' => $display['payment_code'],
            'biller_code' => $display['biller_code'],
            'qr_url' => $display['qr_url'],
            'deeplink_url' => $display['deeplink_url'],
            'gateway_expires_at' => $display['expires_at'],
        ], static fn ($value) => $value !== null);
    }

    private function subscriptionChargePayload(
        ProviderSubscription $subscription,
        string $channel
    ): array {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw ValidationException::withMessages([
                'payment_channel' => 'Metode pembayaran tidak tersedia.',
            ]);
        }

        $subscription->loadMissing('provider.providerProfile');
        $grossAmount = (int) round((float) $subscription->price);
        $orderId = trim((string) $subscription->midtrans_order_id);

        if ($grossAmount < 1 || $orderId === '') {
            throw ValidationException::withMessages([
                'payment' => 'Nominal atau order subscription tidak valid.',
            ]);
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => $this->userDetails($subscription->provider),
            'custom_expiry' => [
                'expiry_duration' => self::PAYMENT_EXPIRY_MINUTES,
                'unit' => 'minute',
            ],
            'custom_field1' => 'provider-subscription',
            'custom_field2' => (string) $subscription->plan_id,
        ];

        if ($channel === 'qris') {
            return array_merge($payload, [
                'payment_type' => 'gopay',
            ]);
        }

        if ($channel === 'mandiri_bill') {
            return array_merge($payload, [
                'payment_type' => 'echannel',
                'echannel' => [
                    'bill_info1' => 'Payment For:',
                    'bill_info2' => 'JasaKu Subscription',
                ],
            ]);
        }

        return array_merge($payload, [
            'payment_type' => 'bank_transfer',
            'bank_transfer' => [
                'bank' => str_replace('_va', '', $channel),
            ],
        ]);
    }

    private function chargePayload(Payment $payment, string $channel): array
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw ValidationException::withMessages([
                'payment_channel' => 'Metode pembayaran tidak tersedia.',
            ]);
        }

        $payment->loadMissing('booking.customer.customerProfile', 'booking.services');
        $booking = $payment->booking;
        $grossAmount = (int) round((float) $payment->amount);

        if ($grossAmount < 1 || ! $booking) {
            throw ValidationException::withMessages([
                'payment' => 'Nominal pembayaran tidak valid.',
            ]);
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $payment->midtrans_order_id ?: $this->makeOrderId($payment, $booking),
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => $this->customerDetails($booking),
            'custom_expiry' => [
                'expiry_duration' => self::PAYMENT_EXPIRY_MINUTES,
                'unit' => 'minute',
            ],
            'custom_field1' => $booking->booking_code,
            'custom_field2' => $payment->payment_type,
        ];

        if ($channel === 'qris') {
            return array_merge($payload, [
                'payment_type' => 'gopay',
            ]);
        }

        if ($channel === 'mandiri_bill') {
            return array_merge($payload, [
                'payment_type' => 'echannel',
                'echannel' => [
                    'bill_info1' => 'Payment For:',
                    'bill_info2' => 'JasaKu Booking',
                ],
            ]);
        }

        return array_merge($payload, [
            'payment_type' => 'bank_transfer',
            'bank_transfer' => [
                'bank' => str_replace('_va', '', $channel),
            ],
        ]);
    }

    private function customerDetails(Booking $booking): array
    {
        $customer = $booking->customer;
        $profile = $customer?->customerProfile;
        $name = trim((string) ($booking->customer_name ?: $customer?->name));
        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return array_filter([
            'first_name' => $parts[0] ?? 'Customer',
            'last_name' => $parts[1] ?? null,
            'email' => $customer?->email,
            'phone' => $booking->customer_phone ?: $profile?->phone_number,
        ]);
    }

    private function userDetails(?User $user): array
    {
        $name = trim((string) $user?->name);
        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return array_filter([
            'first_name' => $parts[0] ?? 'Provider',
            'last_name' => $parts[1] ?? null,
            'email' => $user?->email,
            'phone' => $user?->providerProfile?->phone_number,
        ]);
    }

    private function makeOrderId(Payment $payment, Booking $booking): string
    {
        return 'JSK-' . $booking->booking_code . '-' . $payment->id;
    }

    private function parseMidtransTime(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function baseUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    private function serverKey(): string
    {
        $serverKey = (string) config('services.midtrans.server_key');

        if ($serverKey === '') {
            throw ValidationException::withMessages([
                'payment' => 'MIDTRANS_SERVER_KEY belum diisi di file .env.',
            ]);
        }

        return $serverKey;
    }
}
