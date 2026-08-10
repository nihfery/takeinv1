<?php

namespace App\Modules\Payment\Presentation\Api\Customer;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Booking\Application\Services\BookingFlowService;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Payment\Infrastructure\Gateways\Midtrans\MidtransService;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentController extends ApiController
{
    public function __construct(
        private readonly BookingFlowService $bookingFlow,
        private readonly MidtransService $midtrans,
        private readonly RecordAuditEvent $audit,
    ) {
    }

    public function charge(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeCustomerBooking($request, $booking);
        $this->ensureBookingIsFinalized($booking);
        $validated = $request->validate([
            'payment_channel' => ['nullable', Rule::in(MidtransService::CHANNELS)],
        ]);
        $payment = $this->gatewayPayment($booking);
        $gateway = $payment->gatewayTransaction;
        $requestedChannel = $validated['payment_channel'] ?? null;
        $channel = $requestedChannel ?: $gateway?->payment_channel ?: 'qris';

        abort_if(
            $requestedChannel
                && $gateway?->provider_order_id
                && $gateway->payment_channel
                && $gateway->payment_channel !== $requestedChannel,
            422,
            'Metode pembayaran tidak dapat diubah setelah transaksi Midtrans dibuat.'
        );

        if ($payment->status === 'paid') {
            return $this->bookingPaymentResponse(
                $booking,
                'Pembayaran booking sudah terverifikasi.'
            );
        }

        abort_unless(
            in_array($payment->status, ['unpaid', 'pending'], true),
            422,
            'Tagihan ini tidak dapat dibuat ulang.'
        );

        $before = $this->paymentAuditState($payment, $booking);
        $auditAction = null;

        if ($gateway?->provider_order_id && $gateway->raw_response) {
            $payment = $payment->refresh()->load('gatewayTransaction');
        } else {
            $hadProviderOrder = (bool) $gateway?->provider_order_id;

            if (! $hadProviderOrder) {
                $payment = $this->midtrans->reservePaymentOrder($payment, $channel);
                $gateway = $payment->gatewayTransaction;
            }

            if ($hadProviderOrder) {
                try {
                    $response = $this->midtrans->status((string) $gateway->provider_order_id);
                    $payment = $this->midtrans->updatePaymentFromStatus($payment, $response);
                    $auditAction = 'payment.gateway-charge.recovered';
                } catch (Throwable $statusFailure) {
                    if ($statusFailure instanceof ValidationException
                        && ! array_key_exists('payment', $statusFailure->errors())) {
                        throw $statusFailure;
                    }
                }
            }

            if (! $auditAction) {
                [$payment, $auditAction] = $this->chargeOrRecover($payment, $channel);
            }
        }

        if ($auditAction) {
            $booking->refresh();
            $this->audit->execute(
                $auditAction,
                Payment::class,
                $payment->id,
                before: $before,
                after: $this->paymentAuditState($payment, $booking),
                actor: $request->user(),
                providerId: (int) $booking->provider_id,
            );
        }

        return $this->bookingPaymentResponse($booking, 'Transaksi Midtrans siap dibayar.');
    }

    public function status(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeCustomerBooking($request, $booking);
        $this->ensureBookingIsFinalized($booking);
        $payment = $this->gatewayPayment($booking);
        $orderId = $payment->gatewayTransaction?->provider_order_id;

        abort_unless($orderId, 422, 'Transaksi Midtrans belum dibuat.');

        $before = $this->paymentAuditState($payment, $booking);
        $response = $this->midtrans->status((string) $orderId);
        $payment = $this->midtrans->updatePaymentFromStatus($payment, $response);
        $booking->refresh();
        $after = $this->paymentAuditState($payment, $booking);

        if ($before !== $after) {
            $this->audit->execute(
                'payment.gateway-status.verified',
                Payment::class,
                $payment->id,
                before: $before,
                after: $after,
                actor: $request->user(),
                providerId: (int) $booking->provider_id,
            );
        }

        return $this->bookingPaymentResponse($booking, 'Status pembayaran berhasil diverifikasi.');
    }

    public function confirmByCode(Request $request, string $bookingCode): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        abort_unless(
            config('payments.allow_customer_manual_confirmation'),
            409,
            'Manual payment confirmation is disabled. Payment status must be verified by the gateway.'
        );

        $booking = Booking::query()
            ->where('booking_code', $bookingCode)
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();
        $this->ensureBookingIsFinalized($booking);
        DB::transaction(function () use ($booking, $request): void {
            $payment = Payment::query()
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->first();

            abort_unless($payment, 404, 'Payment tidak ditemukan.');
            abort_if($payment->payment_type === 'pay_at_salon', 422, 'Booking ini dibayar langsung di salon.');
            abort_if($payment->status === 'paid', 422, 'This booking has already been paid.');
            $beforePaymentStatus = (string) $payment->status;

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            $payment->gatewayTransaction()->updateOrCreate(
                [],
                [
                    'gateway' => 'manual',
                    'provider_status' => 'manually_confirmed',
                    'expires_at' => null,
                ]
            );

            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $beforeStatus = (string) $lockedBooking->status;
            $lockedBooking->update(['status' => 'confirmed']);

            $this->audit->execute(
                'payment.manual-confirmed',
                Payment::class,
                $payment->id,
                before: [
                    'payment_status' => $beforePaymentStatus,
                    'booking_status' => $beforeStatus,
                ],
                after: [
                    'payment_status' => 'paid',
                    'booking_status' => 'confirmed',
                ],
                actor: $request->user(),
                providerId: (int) $lockedBooking->provider_id,
            );
        });

        return response()->json([
            'message' => 'Payment confirmed successfully.',
            'data' => $booking->refresh()->load($this->bookingFlow->bookingRelations()),
        ]);
    }

    private function authorizeCustomerBooking(Request $request, Booking $booking): void
    {
        $this->authorizeRole($request, 'customer');

        abort_unless((int) $booking->customer_id === (int) $request->user()->id, 403, 'Access denied.');
    }

    private function ensureBookingIsFinalized(Booking $booking): void
    {
        $this->bookingFlow->releaseExpiredHolds((int) $booking->customer_id);
        $booking->refresh();

        abort_unless(
            $this->bookingFlow->bookingIsFinalized($booking),
            422,
            'Booking belum siap dibayar atau waktu booking sudah habis. Silakan pilih jadwal lagi.'
        );
    }

    private function gatewayPayment(Booking $booking): Payment
    {
        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->with('gatewayTransaction')
            ->first();

        abort_unless($payment, 404, 'Payment tidak ditemukan.');
        abort_if($payment->payment_type === 'pay_at_salon', 422, 'Booking ini dibayar langsung di salon.');
        abort_if(
            $payment->gatewayTransaction && $payment->gatewayTransaction->gateway !== 'midtrans',
            422,
            'Tagihan ini bukan transaksi Midtrans.'
        );

        return $payment;
    }

    private function bookingPaymentResponse(Booking $booking, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $booking->refresh()->load($this->bookingFlow->bookingRelations()),
        ]);
    }

    private function paymentAuditState(Payment $payment, Booking $booking): array
    {
        return [
            'payment_status' => (string) $payment->status,
            'payment_method' => $payment->payment_method,
            'booking_status' => (string) $booking->status,
            'payment_channel' => $payment->gatewayTransaction?->payment_channel,
            'provider_status' => $payment->gatewayTransaction?->provider_status,
        ];
    }

    private function chargeOrRecover(Payment $payment, string $channel): array
    {
        try {
            $response = $this->midtrans->charge($payment, $channel);

            return [
                $this->midtrans->updatePaymentFromCharge($payment, $response, $channel),
                'payment.gateway-charge.created',
            ];
        } catch (Throwable $chargeFailure) {
            try {
                $response = $this->midtrans->status($this->midtrans->paymentOrderId($payment));

                return [
                    $this->midtrans->updatePaymentFromStatus($payment, $response),
                    'payment.gateway-charge.recovered',
                ];
            } catch (Throwable) {
                throw $chargeFailure;
            }
        }
    }
}
