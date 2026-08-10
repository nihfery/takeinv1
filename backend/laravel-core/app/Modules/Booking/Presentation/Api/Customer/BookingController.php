<?php

namespace App\Modules\Booking\Presentation\Api\Customer;

use App\Http\Controllers\Api\ApiController;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notification\Application\Services\AppNotificationService;
use App\Modules\Booking\Application\Services\BookingFlowService;
use App\Modules\Payment\Infrastructure\Gateways\Midtrans\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BookingController extends ApiController
{
    public function __construct(
        private readonly BookingFlowService $bookingFlow,
        private readonly MidtransService $midtrans
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'customer');
        $this->midtrans->expireOverduePaymentsForCustomer((int) $request->user()->id);

        $bookings = Booking::query()
            ->with($this->bookingFlow->bookingRelations())
            ->where('customer_id', $request->user()->id)
            ->when($request->query('status') && $request->query('status') !== 'all', fn ($query) => $query->where('status', $request->query('status')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return response()->json($bookings);
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('provider_branches', 'id')],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', Rule::exists('services', 'id')],
            'booking_date' => ['nullable', 'date', 'after_or_equal:today'],
            'staff_id' => ['nullable', 'integer', Rule::exists('provider_staffs', 'id')],
            'held_booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
            'booking_type' => ['required', Rule::in(['scheduled', 'queue'])],
            'participant_count' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ]);

        $branch = ProviderBranch::query()
            ->with('provider.providerProfile')
            ->whereKey($validated['branch_id'])
            ->where('status', 'active')
            ->firstOrFail();

        abort_unless($this->bookingFlow->branchIsBookable($branch), 404);

        $services = $this->bookingFlow->servicesForBooking(
            $branch,
            $this->bookingFlow->normalizedServiceIds($validated),
            $validated['booking_type']
        );
        $heldBookingId = null;
        $customer = $this->optionalCustomer($request);

        if ($customer?->id && filled($validated['held_booking_id'] ?? null)) {
            $heldBookingId = Booking::query()
                ->whereKey($validated['held_booking_id'])
                ->where('customer_id', $customer->id)
                ->whereIn('status', [BookingFlowService::STATUS_PENDING_HOLD, 'pending'])
                ->whereNotNull('hold_expires_at')
                ->where('hold_expires_at', '>', now())
                ->value('id');
        }

        if (! $heldBookingId && $customer?->id) {
            $heldBookingId = $this->bookingFlow->activeHoldIdForCustomerSelection(
                (int) $customer->id,
                $branch,
                $services,
                $validated['booking_date'] ?? null,
                filled($validated['staff_id'] ?? null) ? (int) $validated['staff_id'] : null,
                (int) ($validated['participant_count'] ?? 1)
            );
        }

        return response()->json([
            'data' => $this->bookingFlow->availabilityPayload(
                $branch,
                $services,
                $validated['booking_date'] ?? null,
                $validated['staff_id'] ?? null,
                $validated['booking_type'],
                $heldBookingId ? (int) $heldBookingId : null,
                $customer?->id ? (int) $customer->id : null,
                (int) ($validated['participant_count'] ?? 1)
            ),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    private function optionalCustomer(Request $request): ?User
    {
        $user = $request->user('sanctum') ?: $request->user() ?: Auth::guard('web')->user();

        return $user instanceof User && $user->role === 'customer'
            ? $user
            : null;
    }

    public function eligibleStaff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('provider_branches', 'id')],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', Rule::exists('services', 'id')],
            'booking_date' => ['nullable', 'date', 'after_or_equal:today'],
            'staff_id' => ['nullable', 'integer', Rule::exists('provider_staffs', 'id')],
            'booking_type' => ['required', Rule::in(['scheduled', 'queue'])],
            'participant_count' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ]);

        $branch = ProviderBranch::query()
            ->with('provider.providerProfile')
            ->whereKey($validated['branch_id'])
            ->where('status', 'active')
            ->firstOrFail();

        abort_unless($this->bookingFlow->branchIsBookable($branch), 404);

        $services = $this->bookingFlow->servicesForBooking(
            $branch,
            $this->bookingFlow->normalizedServiceIds($validated),
            $validated['booking_type']
        );

        $payload = $this->bookingFlow->availabilityPayload(
            $branch,
            $services,
            $validated['booking_date'] ?? null,
            $validated['staff_id'] ?? null,
            $validated['booking_type'],
            null,
            null,
            (int) ($validated['participant_count'] ?? 1)
        );

        unset($payload['available_slots'], $payload['queue_estimation']);

        return response()->json([
            'data' => $payload,
        ])->header('Cache-Control', 'private, max-age=15')
            ->header('Pragma', 'cache');
    }

    public function interaction(Request $request): Response
    {
        $request->validate([
            'event' => ['required', Rule::in([
                'service_selected',
                'staff_selected',
                'date_selected',
                'slot_selected',
                'continue_to_confirm',
            ])],
            'branch_id' => ['nullable', 'integer'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer'],
            'staff_id' => ['nullable', 'integer'],
            'booking_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
        ]);

        return response()->noContent();
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('provider_branches', 'id')],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', Rule::exists('services', 'id')],
            'booking_type' => ['required', Rule::in(['scheduled', 'queue'])],
            'staff_id' => ['nullable', 'integer', Rule::exists('provider_staffs', 'id')],
            'booking_date' => ['nullable', 'date', 'after_or_equal:today', 'required_if:booking_type,scheduled'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_if:booking_type,scheduled'],
            'payment_type' => ['required', Rule::in(['dp', 'full_payment', 'pay_at_salon'])],
            'payment_channel' => ['nullable', Rule::in(MidtransService::CHANNELS)],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'hold_only' => ['sometimes', 'boolean'],
            'booking_hold_expires_at' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'participant_count' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'guests' => ['nullable', 'array', 'max:4'],
            'guests.*.name' => ['nullable', 'string', 'max:100'],
            'guests.*.phone' => ['nullable', 'string', 'max:30'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
            'guests.*.gender' => ['nullable', Rule::in(['male', 'female'])],
            'guests.*.age_group' => ['nullable', Rule::in(['child', 'teen', 'adult', 'senior'])],
            'guests.*.description' => ['nullable', 'string', 'max:1000'],
            'participant_selections' => ['nullable', 'array', 'min:2', 'max:5'],
            'participant_selections.*.position' => ['required_with:participant_selections', 'integer', 'min:1', 'max:5'],
            'participant_selections.*.is_primary' => ['sometimes', 'boolean'],
            'participant_selections.*.name' => ['nullable', 'string', 'max:100'],
            'participant_selections.*.phone' => ['nullable', 'string', 'max:30'],
            'participant_selections.*.email' => ['nullable', 'email', 'max:255'],
            'participant_selections.*.gender' => ['nullable', Rule::in(['male', 'female'])],
            'participant_selections.*.age_group' => ['nullable', Rule::in(['child', 'teen', 'adult', 'senior'])],
            'participant_selections.*.description' => ['nullable', 'string', 'max:1000'],
            'participant_selections.*.service_ids' => ['required_with:participant_selections', 'array', 'min:1'],
            'participant_selections.*.service_ids.*' => ['required', 'integer', Rule::exists('services', 'id')],
            'participant_selections.*.staff_id' => ['nullable', 'integer', Rule::exists('provider_staffs', 'id')],
            'participant_selections.*.booking_date' => ['required_with:participant_selections', 'date', 'after_or_equal:today'],
            'participant_selections.*.start_time' => ['required_with:participant_selections', 'date_format:H:i'],
        ]);

        $booking = $this->bookingFlow->createBooking($validated, $request->user());

        if (! $this->bookingFlow->bookingHasActiveHold($booking)) {
            $this->notifyProviderBookingCreated($booking, $request);
        }

        return response()->json([
            'message' => $this->bookingFlow->bookingHasActiveHold($booking)
                ? 'The schedule has been held for 3 minutes.'
                : 'Booking berhasil dibuat.',
            'data' => $booking,
        ], 201);
    }

    public function finalize(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeCustomerBooking($request, $booking);

        $validated = $request->validate([
            'payment_type' => ['required', Rule::in(['dp', 'full_payment', 'pay_at_salon'])],
            'payment_channel' => ['nullable', Rule::in(MidtransService::CHANNELS)],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'participant_count' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'guests' => ['nullable', 'array', 'max:4'],
            'guests.*.name' => ['nullable', 'string', 'max:100'],
            'guests.*.phone' => ['nullable', 'string', 'max:30'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
            'guests.*.gender' => ['nullable', Rule::in(['male', 'female'])],
            'guests.*.age_group' => ['nullable', Rule::in(['child', 'teen', 'adult', 'senior'])],
            'guests.*.description' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking = $this->bookingFlow->finalizeHeldBooking($booking, $validated);
        $this->notifyProviderBookingCreated($booking, $request);

        return response()->json([
            'message' => 'Booking berhasil dikonfirmasi.',
            'data' => $booking,
        ]);
    }

    public function extendHold(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeCustomerBooking($request, $booking);

        $booking = $this->bookingFlow->extendHeldBooking($booking);

        return response()->json([
            'message' => 'The temporary booking hold has been extended.',
            'data' => $booking,
        ]);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeCustomerBooking($request, $booking);
        $booking->loadMissing('payment');

        if ($booking->payment) {
            $this->midtrans->expirePaymentIfOverdue($booking->payment);
        }

        return response()->json([
            'data' => $booking->load($this->bookingFlow->bookingRelations()),
        ]);
    }

    public function showByCode(Request $request, string $bookingCode): JsonResponse
    {
        $this->authorizeRole($request, 'customer');

        $booking = Booking::query()
            ->where('booking_code', $bookingCode)
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        $booking->loadMissing('payment');

        if ($booking->payment) {
            $this->midtrans->expirePaymentIfOverdue($booking->payment);
        }

        return response()->json([
            'data' => $booking->load($this->bookingFlow->bookingRelations()),
        ]);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeCustomerBooking($request, $booking);

        if ($this->bookingFlow->bookingIsTemporaryHold($booking)) {
            $bookingId = (int) $booking->id;

            abort_unless(
                $this->bookingFlow->discardBookingHold($booking),
                422,
                'Booking sementara ini sudah tidak tersedia.'
            );

            return response()->json([
                'message' => 'Slot sementara berhasil dilepas.',
                'data' => [
                    'id' => $bookingId,
                    'released' => true,
                ],
            ]);
        }

        abort_unless(
            in_array($booking->status, BookingFlowService::CANCELLABLE_STATUSES, true),
            422,
            'Booking ini tidak bisa dibatalkan.'
        );

        $booking = $this->bookingFlow->updateStatus($booking, 'cancelled');
        $this->notifyProviderBookingCancelled($booking, $request);

        return response()->json([
            'message' => 'Booking berhasil dibatalkan.',
            'data' => $booking,
        ]);
    }

    public function reschedule(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeCustomerBooking($request, $booking);

        $validated = $request->validate([
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'staff_id' => ['nullable', 'integer', Rule::exists('provider_staffs', 'id')],
        ]);

        $booking = $this->bookingFlow->rescheduleBooking($booking, $validated);
        $this->notifyProviderBookingRescheduled($booking, $request);

        return response()->json([
            'message' => 'Booking berhasil di-reschedule.',
            'data' => $booking,
        ]);
    }

    private function notifyProviderBookingCreated(Booking $booking, Request $request): void
    {
        app(AppNotificationService::class)->createForUsers(
            app(AppNotificationService::class)->providerRecipients((int) $booking->provider_id, 'bookings'),
            'booking.created',
            'Booking baru',
            (($booking->customer_name ?: $request->user()?->name) ?: 'Customer') . ' membuat booking ' . $booking->booking_code . '.',
            route('provider.bookings.index', ['date' => $booking->booking_date?->toDateString()]),
            [
                'booking_id' => (int) $booking->id,
                'booking_code' => $booking->booking_code,
                'provider_id' => (int) $booking->provider_id,
                'branch_id' => (int) $booking->branch_id,
            ],
            (int) $request->user()?->id
        );
    }

    private function notifyProviderBookingCancelled(Booking $booking, Request $request): void
    {
        app(AppNotificationService::class)->createForUsers(
            app(AppNotificationService::class)->providerRecipients((int) $booking->provider_id, 'bookings'),
            'booking.cancelled',
            'Booking dibatalkan',
            (($booking->customer_name ?: $request->user()?->name) ?: 'Customer') . ' membatalkan booking ' . $booking->booking_code . '.',
            route('provider.bookings.index', ['date' => $booking->booking_date?->toDateString()]),
            [
                'booking_id' => (int) $booking->id,
                'booking_code' => $booking->booking_code,
                'provider_id' => (int) $booking->provider_id,
                'branch_id' => (int) $booking->branch_id,
            ],
            (int) $request->user()?->id
        );
    }

    private function notifyProviderBookingRescheduled(Booking $booking, Request $request): void
    {
        app(AppNotificationService::class)->createForUsers(
            app(AppNotificationService::class)->providerRecipients((int) $booking->provider_id, 'bookings'),
            'booking.rescheduled',
            'Booking di-reschedule',
            (($booking->customer_name ?: $request->user()?->name) ?: 'Customer') . ' mengubah jadwal booking ' . $booking->booking_code . '.',
            route('provider.bookings.index', ['date' => $booking->booking_date?->toDateString()]),
            [
                'booking_id' => (int) $booking->id,
                'booking_code' => $booking->booking_code,
                'provider_id' => (int) $booking->provider_id,
                'branch_id' => (int) $booking->branch_id,
            ],
            (int) $request->user()?->id
        );
    }

    private function authorizeCustomerBooking(Request $request, Booking $booking): void
    {
        $this->authorizeRole($request, 'customer');

        abort_unless((int) $booking->customer_id === (int) $request->user()->id, 403, 'Access denied.');
    }
}
