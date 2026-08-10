<?php

namespace App\Modules\Booking\Application\Services;

use App\Modules\Availability\Application\Actions\CheckConflict;
use App\Modules\Availability\Application\Actions\ResolveEligibleStaff;
use App\Modules\Booking\Application\Queries\ResolveBookingServices;
use App\Modules\Payment\Infrastructure\Gateways\Midtrans\MidtransService;
use App\Modules\Promotion\Application\Services\CouponService;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Booking\Infrastructure\Persistence\Models\BookingParticipant;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingFlowService
{
    private const TRANSACTION_ATTEMPTS = 5;

    public const BOOKING_HOLD_MINUTES = 3;

    public const STATUS_PENDING_HOLD = 'pending_hold';

    public const STATUS_EXPIRED_HOLD = 'expired_hold';

    public const STATUS_PAYMENT_EXPIRED = 'payment_expired';

    public const ACTIVE_BOOKING_STATUSES = [
        'open',
        'pending',
        self::STATUS_PENDING_HOLD,
        'pending_payment',
        'confirmed',
        'waiting',
        'checked_in',
        'inprogress',
        'in_progress',
        'rescheduled',
    ];

    public const CLOSED_BOOKING_STATUSES = [
        'completed',
        'order_completed',
        'refund_completed',
        self::STATUS_EXPIRED_HOLD,
        self::STATUS_PAYMENT_EXPIRED,
        'provider_cancelled',
        'customer_cancelled',
        'cancelled',
        'no_show',
    ];

    public const CANCELLABLE_STATUSES = [
        'open',
        'pending',
        self::STATUS_PENDING_HOLD,
        'pending_payment',
        'confirmed',
        'waiting',
        'checked_in',
        'rescheduled',
    ];

    public const RESCHEDULABLE_STATUSES = [
        'open',
        'pending_payment',
        'confirmed',
        'rescheduled',
    ];

    private const FINALIZED_STATUSES = [
        'pending_payment',
        'confirmed',
        'waiting',
        'checked_in',
        'in_progress',
        'inprogress',
        'rescheduled',
        'completed',
    ];

    public function __construct(
        private readonly CouponService $coupons,
        private readonly ResolveBookingServices $resolveBookingServices,
        private readonly ResolveEligibleStaff $resolveEligibleStaff,
        private readonly CheckConflict $checkConflict
    ) {
    }

    public function normalizedServiceIds(array $payload): array
    {
        return $this->resolveBookingServices->normalizedServiceIds($payload);
    }

    public function branchIsBookable(ProviderBranch $branch, bool $isWalkIn = false): bool
    {
        return $this->resolveBookingServices->branchIsBookable($branch, $isWalkIn);
    }

    public function servicesForBooking(ProviderBranch $branch, array $serviceIds, string $bookingType = 'scheduled'): Collection
    {
        return $this->resolveBookingServices->servicesForBooking($branch, $serviceIds, $bookingType);
    }

    public function totals(Collection $services, int $participantCount = 1): array
    {
        $participantCount = max(1, $participantCount);

        return [
            'total_price' => (float) $services->sum(fn (Service $service) => (float) ($service->price ?? 0)) * $participantCount,
            'total_duration' => (int) $services->sum(fn (Service $service) => (int) ($service->estimated_duration ?: 30)) * $participantCount,
        ];
    }

    public function availabilityPayload(
        ProviderBranch $branch,
        Collection $services,
        ?string $bookingDate,
        ?int $staffId,
        string $bookingType,
        ?int $ignoreActiveHoldBookingId = null,
        ?int $customerId = null,
        int $participantCount = 1
    ): array {
        $this->releaseExpiredHolds();

        $participantCount = max(1, $participantCount);
        $totals = $this->totals($services, $participantCount);
        $date = $bookingDate ?: now()->toDateString();
        $eligibleStaff = $this->eligibleStaff($branch, $services, $bookingDate, $staffId);

        return [
            'server_now' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'eligible_staff' => $eligibleStaff->map(fn (ProviderStaff $staff) => $this->staffPayload($staff))->values(),
            'available_slots' => $bookingType === 'scheduled' && $bookingDate
                ? $this->availableSlots($branch, $services, $date, $staffId, $eligibleStaff, $ignoreActiveHoldBookingId, true, $customerId, $participantCount)
                : [],
            'time_slots' => $bookingType === 'scheduled' && $bookingDate
                ? $this->availableSlots($branch, $services, $date, $staffId, $eligibleStaff, $ignoreActiveHoldBookingId, false, $customerId, $participantCount)
                : [],
            'estimated_duration' => $totals['total_duration'],
            'total_price' => $totals['total_price'],
            'participant_count' => $participantCount,
            'queue_estimation' => $bookingType === 'queue'
                ? $this->queueEstimation($branch, $totals['total_duration'], $staffId)
                : null,
        ];
    }

    public function availableSlots(
        ProviderBranch $branch,
        Collection $services,
        string $date,
        ?int $staffId = null,
        ?Collection $eligibleStaff = null,
        ?int $ignoreActiveHoldBookingId = null,
        bool $availableOnly = true,
        ?int $customerId = null,
        int $participantCount = 1
    ): array
    {
        $duration = $this->totals($services, $participantCount)['total_duration'];
        $slotStepMinutes = max(1, $duration);
        $eligibleStaff ??= $this->eligibleStaff($branch, $services, $date, $staffId);
        if ($staffId) {
            $eligibleStaff = $eligibleStaff
                ->filter(fn (ProviderStaff $staff) => (int) $staff->id === (int) $staffId)
                ->values();
        }

        if ($eligibleStaff->isEmpty()) {
            return [];
        }

        $activeBookings = Booking::query()
            ->whereIn('staff_id', $eligibleStaff->pluck('id'))
            ->whereDate('booking_date', $date)
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->when($ignoreActiveHoldBookingId, fn ($query) => $query->whereKeyNot($ignoreActiveHoldBookingId))
            // A temporary hold belonging to the same customer must not hide its
            // own slot. When that customer picks a new time, createBooking()
            // releases the old hold atomically before reserving the new one.
            ->when($customerId, fn ($query) => $query->where(function ($nested) use ($customerId) {
                $nested
                    ->where('customer_id', '!=', $customerId)
                    ->orWhereNotIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
                    ->orWhereNull('hold_expires_at')
                    ->orWhere('hold_expires_at', '<=', now());
            }))
            ->get()
            ->groupBy('staff_id');
        $activeBranchHolds = Booking::query()
            ->where('branch_id', $branch->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '>', now())
            ->when($ignoreActiveHoldBookingId, fn ($query) => $query->whereKeyNot($ignoreActiveHoldBookingId))
            ->when($customerId, fn ($query) => $query->where('customer_id', '!=', $customerId))
            ->get();
        $slots = [];
        $now = now();

        foreach ($eligibleStaff as $staff) {
            $staffBookings = $activeBookings->get($staff->id, collect());

            foreach ($this->workingWindows($branch, $staff, $date) as $window) {
                $cursor = Carbon::parse($date . ' ' . $window['start']);
                $windowEnd = Carbon::parse($date . ' ' . $window['end']);

                while ($cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
                    $start = $cursor->format('H:i');
                    $end = $cursor->copy()->addMinutes($duration)->format('H:i');

                    $slotAvailable = $cursor->gt($now)
                        && ! $this->checkConflict->slotConflictsWithBookings($activeBranchHolds, $date, $start, $duration)
                        && ! $this->checkConflict->slotConflictsWithBookings($staffBookings, $date, $start, $duration)
                        && ! $this->checkConflict->hasParticipantStaffConflict(
                            $staff,
                            $date,
                            $start,
                            $duration,
                            $ignoreActiveHoldBookingId,
                            $customerId
                        );

                    if ($slotAvailable || ! $availableOnly) {
                        $slots[] = [
                            'time' => $start,
                            'staff_id' => $staff->id,
                            'staff_name' => $staff->full_name ?: $staff->email,
                            'estimated_end_time' => $end,
                            'is_available' => $slotAvailable,
                            'status' => $slotAvailable ? 'Available' : 'Not available',
                        ];
                    }

                    $cursor->addMinutes($slotStepMinutes);
                }
            }
        }

        return collect($slots)
            ->sortBy(['time', 'staff_name'])
            ->values()
            ->all();
    }

    public function createBooking(array $payload, ?User $customer = null, bool $walkIn = false): Booking
    {
        $this->releaseExpiredHolds();

        if ($customer && filled($payload['idempotency_key'] ?? null)) {
            $existingBooking = Booking::query()
                ->where('customer_id', $customer->id)
                ->where('idempotency_key', $payload['idempotency_key'])
                ->first();

            if ($existingBooking && ! in_array($existingBooking->status, self::CLOSED_BOOKING_STATUSES, true)) {
                return $existingBooking->load($this->bookingRelations());
            }
        }

        if (! empty($payload['participant_selections'])) {
            return $this->createBookingWithParticipantSelections($payload, $customer, $walkIn);
        }

        $branch = ProviderBranch::query()
            ->with('provider.providerProfile')
            ->whereKey($payload['branch_id'] ?? null)
            ->where('status', 'active')
            ->firstOrFail();

        if (! $this->branchIsBookable($branch, $walkIn)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch belum tersedia untuk booking.',
            ]);
        }

        $bookingType = $walkIn ? 'walk_in' : ($payload['booking_type'] ?? 'scheduled');
        $scheduledAppointment = $bookingType === 'scheduled'
            || ($bookingType === 'walk_in' && filled($payload['start_time'] ?? null));
        $holdOnly = (bool) ($payload['hold_only'] ?? false);
        $participantCount = $this->participantCount($payload);
        $guests = $this->validatedGuests($payload, $participantCount, ! $holdOnly);
        $serviceIds = $this->normalizedServiceIds($payload);
        $services = $this->servicesForBooking(
            $branch,
            $serviceIds,
            $scheduledAppointment ? 'scheduled' : $bookingType
        );
        $totals = $this->totals($services, $participantCount);
        $priceSummary = $this->coupons->priceSummary(
            $services,
            $holdOnly ? null : ($payload['coupon_code'] ?? null),
            $participantCount
        );
        $bookingDate = $payload['booking_date'] ?? now()->toDateString();
        $startTime = $payload['start_time'] ?? null;
        $staffId = filled($payload['staff_id'] ?? null) ? (int) $payload['staff_id'] : null;
        $holdExpiresAt = $holdOnly
            ? $this->holdExpirationFromPayload($payload)
            : null;

        if ($holdOnly && $customer) {
            $this->cancelActiveHoldsForCustomer((int) $customer->id, (int) $branch->id);
        }

        if ($scheduledAppointment) {
            if (blank($bookingDate) || blank($startTime)) {
                throw ValidationException::withMessages([
                    'booking_date' => 'Date and time are required for a scheduled booking.',
                    'start_time' => 'Date and time are required for a scheduled booking.',
                ]);
            }

            if (Carbon::parse($bookingDate)->isPast() && Carbon::parse($bookingDate)->isBefore(now()->startOfDay())) {
                throw ValidationException::withMessages([
                    'booking_date' => 'The booking date cannot be in the past.',
                ]);
            }

            if (Carbon::parse($bookingDate . ' ' . $startTime)->lte(now())) {
                throw ValidationException::withMessages([
                    'start_time' => 'This booking time has passed. Please choose a later time.',
                ]);
            }

            $staff = $this->chooseStaffForScheduled(
                $branch,
                $services,
                $bookingDate,
                $startTime,
                $staffId,
                null,
                $customer?->id ? (int) $customer->id : null,
                $participantCount
            );
        } else {
            $bookingDate = $bookingDate ?: now()->toDateString();
            $staff = $this->chooseStaffForQueue($branch, $services, $staffId, $bookingDate);
            $startTime = null;
        }

        $paymentType = $holdOnly ? 'pay_at_salon' : ($payload['payment_type'] ?? 'pay_at_salon');
        $payment = $this->paymentPayload(
            $paymentType,
            $services,
            $priceSummary['payable_amount'],
            $payload['payment_channel'] ?? null
        );

        return DB::transaction(function () use ($payload, $customer, $branch, $services, $totals, $bookingType, $bookingDate, $startTime, $staff, $paymentType, $holdOnly, $holdExpiresAt, $participantCount, $guests, $scheduledAppointment) {
            $existingBooking = $this->lockCustomerAndResolveIdempotentBooking(
                $customer,
                $payload['idempotency_key'] ?? null
            );

            if ($existingBooking) {
                return $existingBooking;
            }

            if ($scheduledAppointment && $staff && $startTime) {
                ProviderStaff::query()->whereKey($staff->id)->lockForUpdate()->first();

                $lockedBranchHolds = Booking::query()
                    ->where('branch_id', $branch->id)
                    ->whereDate('booking_date', $bookingDate)
                    ->whereIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
                    ->whereNotNull('hold_expires_at')
                    ->where('hold_expires_at', '>', now())
                    ->when($customer?->id, fn ($query) => $query->where('customer_id', '!=', $customer->id))
                    ->lockForUpdate()
                    ->get();

                if ($this->checkConflict->slotConflictsWithBookings($lockedBranchHolds, $bookingDate, $startTime, $totals['total_duration'])) {
                    throw ValidationException::withMessages([
                        'start_time' => 'This time was just booked by another customer. Please choose another time.',
                    ]);
                }

                $lockedBookings = Booking::query()
                    ->where('staff_id', $staff->id)
                    ->whereDate('booking_date', $bookingDate)
                    ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
                    ->when($customer?->id, fn ($query) => $query->where(function ($nested) use ($customer) {
                        $nested
                            ->where('customer_id', '!=', $customer->id)
                            ->orWhereNotIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
                            ->orWhereNull('hold_expires_at')
                            ->orWhere('hold_expires_at', '<=', now());
                    }))
                    ->lockForUpdate()
                    ->get();

                if ($this->checkConflict->slotConflictsWithBookings($lockedBookings, $bookingDate, $startTime, $totals['total_duration'])) {
                    throw ValidationException::withMessages([
                        'start_time' => 'This time was just booked by another customer. Please choose another time.',
                    ]);
                }
            }

            $priceSummary = $this->coupons->priceSummary(
                $services,
                $holdOnly ? null : ($payload['coupon_code'] ?? null),
                $participantCount,
                true
            );
            $payment = $this->paymentPayload(
                $paymentType,
                $services,
                $priceSummary['payable_amount'],
                $payload['payment_channel'] ?? null
            );

            $queueNumber = ! $scheduledAppointment && in_array($bookingType, ['queue', 'walk_in'], true)
                ? $this->nextQueueNumber($branch, $bookingDate)
                : null;

            $status = $holdOnly
                ? self::STATUS_PENDING_HOLD
                : ($payment['status'] === 'pending'
                ? 'pending_payment'
                : ($scheduledAppointment
                    ? 'confirmed'
                    : match ($bookingType) {
                        'queue', 'walk_in' => 'waiting',
                        default => 'confirmed',
                    }));

            $estimatedEndTime = $startTime
                ? Carbon::parse($bookingDate . ' ' . $startTime)->addMinutes($totals['total_duration'])->format('H:i')
                : null;

            $booking = Booking::create([
                'booking_code' => $this->uniqueBookingCode(),
                'booking_date' => $bookingDate,
                'start_time' => $startTime,
                'estimated_end_time' => $estimatedEndTime,
                'provider_id' => $branch->provider_id,
                'customer_id' => $customer?->id,
                'branch_id' => $branch->id,
                'staff_id' => $staff?->id,
                'booking_type' => $bookingType,
                'total_price' => $priceSummary['payable_amount'],
                'total_duration' => $totals['total_duration'],
                'customer_name' => $payload['customer_name'] ?? $customer?->name,
                'customer_phone' => $payload['customer_phone'] ?? optional($customer?->customerProfile)->phone_number,
                'participant_count' => $participantCount,
                'notes' => $payload['notes'] ?? null,
                'queue_number' => $queueNumber,
                'status' => $status,
                'held_at' => $holdOnly ? now() : null,
                'hold_expires_at' => $holdExpiresAt,
                'expired_at' => null,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
            ]);

            $booking->services()->attach(
                $services->mapWithKeys(fn (Service $service) => [
                    $service->id => [
                        'price' => $service->price ?? 0,
                        'estimated_duration' => $service->estimated_duration ?: 30,
                    ],
                ])->all()
            );

            $this->persistPayment($booking, $payment);

            $this->syncBookingParticipants($booking, $customer, $guests);

            if ($priceSummary['coupon']) {
                $priceSummary['coupon']->increment('used_count');
            }

            return $booking->refresh()->load($this->bookingRelations());
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function lockCustomerAndResolveIdempotentBooking(
        ?User $customer,
        mixed $idempotencyKey
    ): ?Booking {
        if (! $customer) {
            return null;
        }

        User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

        if (blank($idempotencyKey)) {
            return null;
        }

        // The customer row above is the serialization lock for this key.
        // Do not gap-lock a missing booking-key row: concurrent customers can
        // otherwise deadlock while they wait for the same staff slot.
        $existingBooking = Booking::query()
            ->where('customer_id', $customer->id)
            ->where('idempotency_key', (string) $idempotencyKey)
            ->first();

        if (! $existingBooking) {
            return null;
        }

        if (! in_array($existingBooking->status, self::CLOSED_BOOKING_STATUSES, true)) {
            return $existingBooking->load($this->bookingRelations());
        }

        $existingBooking->update(['idempotency_key' => null]);

        return null;
    }

    private function createBookingWithParticipantSelections(array $payload, ?User $customer, bool $walkIn): Booking
    {
        $branch = ProviderBranch::query()
            ->with('provider.providerProfile')
            ->whereKey($payload['branch_id'] ?? null)
            ->where('status', 'active')
            ->firstOrFail();

        if (! $this->branchIsBookable($branch, $walkIn)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch belum tersedia untuk booking.',
            ]);
        }

        $bookingType = $walkIn ? 'walk_in' : ($payload['booking_type'] ?? 'scheduled');
        if ($bookingType !== 'scheduled') {
            throw ValidationException::withMessages([
                'booking_type' => 'Pilihan layanan dan jadwal per peserta hanya tersedia untuk booking terjadwal.',
            ]);
        }

        $holdOnly = (bool) ($payload['hold_only'] ?? false);
        $participantCount = $this->participantCount($payload);
        $selections = collect($payload['participant_selections'])
            ->sortBy(fn (array $selection) => (int) ($selection['position'] ?? 0))
            ->values();

        if ($selections->count() !== $participantCount
            || $selections->pluck('position')->map(fn ($position) => (int) $position)->unique()->count() !== $participantCount) {
            throw ValidationException::withMessages([
                'participant_selections' => 'Lengkapi pilihan layanan, professional, tanggal, dan jam untuk setiap peserta.',
            ]);
        }

        $expectedPositions = range(1, $participantCount);
        if ($selections->pluck('position')->map(fn ($position) => (int) $position)->all() !== $expectedPositions) {
            throw ValidationException::withMessages([
                'participant_selections' => 'Urutan peserta tidak valid.',
            ]);
        }

        $plans = collect();

        foreach ($selections as $index => $selection) {
            $position = $index + 1;
            $services = $this->servicesForBooking(
                $branch,
                $this->normalizedServiceIds(['service_ids' => $selection['service_ids'] ?? []]),
                'scheduled'
            );
            $bookingDate = (string) ($selection['booking_date'] ?? '');
            $startTime = substr((string) ($selection['start_time'] ?? ''), 0, 5);

            if ($bookingDate === '' || $startTime === '' || Carbon::parse($bookingDate . ' ' . $startTime)->lte(now())) {
                throw ValidationException::withMessages([
                    "participant_selections.{$index}.start_time" => "The schedule for participant {$position} has passed or is incomplete.",
                ]);
            }

            $name = trim((string) ($selection['name'] ?? ''));
            $phone = trim((string) ($selection['phone'] ?? ''));
            $gender = filled($selection['gender'] ?? null) ? (string) $selection['gender'] : null;
            $ageGroup = filled($selection['age_group'] ?? null) ? (string) $selection['age_group'] : null;
            if (! $holdOnly && $position > 1 && ($name === '' || $phone === '' || $gender === null || $ageGroup === null)) {
                throw ValidationException::withMessages([
                    "participant_selections.{$index}.name" => "Name, phone number, gender, and age group are required for participant {$position}.",
                ]);
            }

            $duration = $this->totals($services, 1)['total_duration'];
            $requestedStaffId = filled($selection['staff_id'] ?? null) ? (int) $selection['staff_id'] : null;
            $eligibleStaff = $this->eligibleStaff($branch, $services, $bookingDate, $requestedStaffId);
            $staff = $eligibleStaff->first(function (ProviderStaff $candidate) use ($branch, $bookingDate, $startTime, $duration, $plans, $customer) {
                if (! $this->staffCanTakeSlot(
                    $branch,
                    $candidate,
                    $bookingDate,
                    $startTime,
                    $duration,
                    null,
                    $customer?->id ? (int) $customer->id : null
                )) {
                    return false;
                }

                return ! $plans->contains(fn (array $plan) =>
                    (int) $plan['staff']->id === (int) $candidate->id
                    && $plan['booking_date'] === $bookingDate
                    && $this->timeRangesOverlap($startTime, $duration, $plan['start_time'], $plan['duration'])
                );
            });

            if (! $staff) {
                throw ValidationException::withMessages([
                    "participant_selections.{$index}.staff_id" => "Professional atau jam untuk peserta ke-{$position} sudah tidak tersedia.",
                ]);
            }

            $plans->push([
                'position' => $position,
                'is_primary' => $position === 1,
                'name' => $position === 1 ? ($customer?->name ?: ($name ?: 'Customer')) : $name,
                'phone' => $position === 1
                    ? (optional($customer?->customerProfile)->phone_number ?: ($phone ?: null))
                    : $phone,
                'email' => $position === 1 ? $customer?->email : (filled($selection['email'] ?? null) ? trim((string) $selection['email']) : null),
                'gender' => $position === 1
                    ? optional($customer?->customerProfile)->gender
                    : $gender,
                'age_group' => $position === 1 ? null : $ageGroup,
                'description' => filled($selection['description'] ?? null)
                    ? trim((string) $selection['description'])
                    : null,
                'services' => $services,
                'staff' => $staff,
                'booking_date' => $bookingDate,
                'start_time' => $startTime,
                'duration' => $duration,
                'raw_price' => (float) $services->sum(fn (Service $service) => (float) ($service->price ?? 0)),
                'estimated_end_time' => Carbon::parse($bookingDate . ' ' . $startTime)->addMinutes($duration)->format('H:i'),
            ]);
        }

        $allServices = $plans->flatMap(fn (array $plan) => $plan['services'])->values();
        $priceSummary = $this->coupons->priceSummary($allServices, $holdOnly ? null : ($payload['coupon_code'] ?? null), 1);
        $paymentType = $holdOnly ? 'pay_at_salon' : ($payload['payment_type'] ?? 'pay_at_salon');
        $payment = $this->paymentPayload(
            $paymentType,
            $allServices,
            $priceSummary['payable_amount'],
            $payload['payment_channel'] ?? null
        );
        $primaryPlan = $plans->first();
        $holdExpiresAt = $holdOnly ? $this->holdExpirationFromPayload($payload) : null;

        if ($holdOnly && $customer) {
            $this->cancelActiveHoldsForCustomer((int) $customer->id, (int) $branch->id);
        }

        return DB::transaction(function () use ($payload, $customer, $branch, $plans, $allServices, $paymentType, $holdOnly, $holdExpiresAt, $participantCount, $primaryPlan) {
            $existingBooking = $this->lockCustomerAndResolveIdempotentBooking(
                $customer,
                $payload['idempotency_key'] ?? null
            );

            if ($existingBooking) {
                return $existingBooking;
            }

            ProviderStaff::query()
                ->whereIn('id', $plans->pluck('staff.id')->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($plans as $index => $plan) {
                if (! $this->staffCanTakeSlot(
                    $branch,
                    $plan['staff'],
                    $plan['booking_date'],
                    $plan['start_time'],
                    $plan['duration'],
                    null,
                    $customer?->id ? (int) $customer->id : null
                )) {
                    $position = $index + 1;
                    throw ValidationException::withMessages([
                        "participant_selections.{$index}.start_time" => "The schedule for participant {$position} was just taken. Please choose another time.",
                    ]);
                }
            }

            $priceSummary = $this->coupons->priceSummary(
                $allServices,
                $holdOnly ? null : ($payload['coupon_code'] ?? null),
                1,
                true
            );
            $payment = $this->paymentPayload(
                $paymentType,
                $allServices,
                $priceSummary['payable_amount'],
                $payload['payment_channel'] ?? null
            );

            $status = $holdOnly
                ? self::STATUS_PENDING_HOLD
                : ($payment['status'] === 'pending' ? 'pending_payment' : 'confirmed');

            $booking = Booking::create([
                'booking_code' => $this->uniqueBookingCode(),
                'booking_date' => $primaryPlan['booking_date'],
                'start_time' => $primaryPlan['start_time'],
                'estimated_end_time' => $primaryPlan['estimated_end_time'],
                'provider_id' => $branch->provider_id,
                'customer_id' => $customer?->id,
                'branch_id' => $branch->id,
                'staff_id' => $primaryPlan['staff']->id,
                'booking_type' => 'scheduled',
                'total_price' => $priceSummary['payable_amount'],
                'total_duration' => (int) $plans->sum('duration'),
                'customer_name' => $primaryPlan['name'],
                'customer_phone' => $primaryPlan['phone'],
                'participant_count' => $participantCount,
                'notes' => $payload['notes'] ?? null,
                'status' => $status,
                'held_at' => $holdOnly ? now() : null,
                'hold_expires_at' => $holdExpiresAt,
                'expired_at' => null,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
            ]);

            $booking->services()->attach(
                $allServices->unique('id')->mapWithKeys(fn (Service $service) => [
                    $service->id => [
                        'price' => $service->price ?? 0,
                        'estimated_duration' => $service->estimated_duration ?: 30,
                    ],
                ])->all()
            );

            $this->persistPayment($booking, $payment);

            foreach ($plans as $plan) {
                $participant = $booking->participants()->create([
                    'position' => $plan['position'],
                    'is_primary' => $plan['is_primary'],
                    'name' => $plan['name'] ?: "Participant {$plan['position']}",
                    'phone' => $plan['phone'],
                    'email' => $plan['email'],
                    'gender' => $plan['gender'],
                    'age_group' => $plan['age_group'],
                    'description' => $plan['description'],
                    'provider_staff_id' => $plan['staff']->id,
                    'booking_date' => $plan['booking_date'],
                    'start_time' => $plan['start_time'],
                    'estimated_end_time' => $plan['estimated_end_time'],
                    'total_duration' => $plan['duration'],
                    'total_price' => $plan['raw_price'],
                ]);

                $participant->services()->attach(
                    $plan['services']->mapWithKeys(fn (Service $service) => [
                        $service->id => [
                            'price' => $service->price ?? 0,
                            'estimated_duration' => $service->estimated_duration ?: 30,
                        ],
                    ])->all()
                );
            }

            if ($priceSummary['coupon']) {
                $priceSummary['coupon']->increment('used_count');
            }

            return $booking->refresh()->load($this->bookingRelations());
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function timeRangesOverlap(string $firstStart, int $firstDuration, string $secondStart, int $secondDuration): bool
    {
        $date = now()->toDateString();
        $firstStartAt = Carbon::parse($date . ' ' . $firstStart);
        $firstEndAt = $firstStartAt->copy()->addMinutes($firstDuration);
        $secondStartAt = Carbon::parse($date . ' ' . $secondStart);
        $secondEndAt = $secondStartAt->copy()->addMinutes($secondDuration);

        return $firstStartAt->lt($secondEndAt) && $firstEndAt->gt($secondStartAt);
    }

    public function finalizeHeldBooking(Booking $booking, array $payload): Booking
    {
        $this->releaseExpiredHolds((int) $booking->customer_id);

        $booking->refresh()->loadMissing([
            'branch.provider.providerProfile',
            'services',
            'payment',
            'customer.customerProfile',
            'participants.services',
            'participants.staff',
        ]);

        if (! $this->bookingHasActiveHold($booking)) {
            if ($this->bookingIsFinalized($booking)) {
                return $booking->load($this->bookingRelations());
            }

            throw ValidationException::withMessages([
                'booking' => 'The booking hold has expired. Please choose a schedule again.',
            ]);
        }

        $branch = $booking->branch;

        if (! $branch || ! $this->branchIsBookable($branch, $booking->booking_type === 'walk_in')) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch belum tersedia untuk booking.',
            ]);
        }

        $serviceIds = $booking->services->pluck('id')->map(fn ($id) => (int) $id)->all();
        $services = $this->servicesForBooking($branch, $serviceIds, $booking->booking_type ?: 'scheduled');
        $participantCount = max(1, (int) ($booking->participant_count ?: 1));

        if (isset($payload['participant_count']) && (int) $payload['participant_count'] !== $participantCount) {
            throw ValidationException::withMessages([
                'participant_count' => 'Jumlah peserta berubah. Silakan pilih ulang jadwal agar durasi booking dapat diperiksa kembali.',
            ]);
        }

        $guests = $this->validatedGuests($payload, $participantCount, true);
        $hasParticipantSelections = $booking->participants->count() === $participantCount
            && $booking->participants->every(fn (BookingParticipant $participant) =>
                $participant->booking_date && $participant->start_time && $participant->services->isNotEmpty()
            );
        $pricingServices = $hasParticipantSelections
            ? $booking->participants->flatMap(fn (BookingParticipant $participant) => $participant->services)->values()
            : $services;
        $totals = $hasParticipantSelections
            ? [
                'total_price' => (float) $booking->participants->sum('total_price'),
                'total_duration' => (int) $booking->participants->sum('total_duration'),
            ]
            : $this->totals($services, $participantCount);
        $priceSummary = $this->coupons->priceSummary(
            $pricingServices,
            $payload['coupon_code'] ?? null,
            $hasParticipantSelections ? 1 : $participantCount
        );
        $payment = $this->paymentPayload(
            $payload['payment_type'] ?? 'pay_at_salon',
            $pricingServices,
            $priceSummary['payable_amount'],
            $payload['payment_channel'] ?? null
        );
        $bookingDate = $booking->booking_date?->toDateString() ?: now()->toDateString();
        $startTime = substr((string) $booking->start_time, 0, 5);

        return DB::transaction(function () use ($booking, $totals, $bookingDate, $startTime, $payload, $guests, $hasParticipantSelections, $pricingServices, $participantCount) {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()
                ->with(['payment', 'services', 'branch.provider.providerProfile', 'customer.customerProfile', 'participants.services', 'participants.staff'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->bookingHasActiveHold($lockedBooking)) {
                if ($this->bookingIsFinalized($lockedBooking)) {
                    return $lockedBooking->refresh()->load($this->bookingRelations());
                }

                throw ValidationException::withMessages([
                'booking' => 'The booking hold has expired. Please choose a schedule again.',
                ]);
            }

            if ($hasParticipantSelections) {
                ProviderStaff::query()
                    ->whereIn('id', $lockedBooking->participants->pluck('provider_staff_id')->filter()->unique()->all())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lockedBooking->participants as $index => $participant) {
                    $participantDate = $participant->booking_date?->toDateString();
                    $participantStart = substr((string) $participant->start_time, 0, 5);

                    if (! $participant->staff || ! $participantDate || ! $this->staffCanTakeSlot(
                        $lockedBooking->branch,
                        $participant->staff,
                        $participantDate,
                        $participantStart,
                        (int) $participant->total_duration,
                        (int) $lockedBooking->id
                    )) {
                        $position = $index + 1;
                        throw ValidationException::withMessages([
                            "participant_selections.{$index}.start_time" => "The schedule for participant {$position} was just taken. Please choose another time.",
                        ]);
                    }
                }
            } elseif ($lockedBooking->booking_type === 'scheduled' && $lockedBooking->staff_id && $startTime) {
                ProviderStaff::query()->whereKey($lockedBooking->staff_id)->lockForUpdate()->first();

                $lockedBranchHolds = Booking::query()
                    ->where('branch_id', $lockedBooking->branch_id)
                    ->whereDate('booking_date', $bookingDate)
                    ->whereIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
                    ->whereNotNull('hold_expires_at')
                    ->where('hold_expires_at', '>', now())
                    ->where('id', '!=', $lockedBooking->id)
                    ->lockForUpdate()
                    ->get();

                if ($this->checkConflict->slotConflictsWithBookings($lockedBranchHolds, $bookingDate, $startTime, $totals['total_duration'])) {
                    throw ValidationException::withMessages([
                        'start_time' => 'This time was just booked by another customer. Please choose another time.',
                    ]);
                }

                $lockedBookings = Booking::query()
                    ->where('staff_id', $lockedBooking->staff_id)
                    ->whereDate('booking_date', $bookingDate)
                    ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
                    ->where('id', '!=', $lockedBooking->id)
                    ->lockForUpdate()
                    ->get();

                if ($this->checkConflict->slotConflictsWithBookings($lockedBookings, $bookingDate, $startTime, $totals['total_duration'])) {
                    throw ValidationException::withMessages([
                        'start_time' => 'This time was just booked by another customer. Please choose another time.',
                    ]);
                }
            }

            $priceSummary = $this->coupons->priceSummary(
                $pricingServices,
                $payload['coupon_code'] ?? null,
                $hasParticipantSelections ? 1 : $participantCount,
                true
            );
            $payment = $this->paymentPayload(
                $payload['payment_type'] ?? 'pay_at_salon',
                $pricingServices,
                $priceSummary['payable_amount'],
                $payload['payment_channel'] ?? null
            );

            $status = $payment['status'] === 'pending'
                ? 'pending_payment'
                : match ($lockedBooking->booking_type) {
                    'queue', 'walk_in' => 'waiting',
                    default => 'confirmed',
                };

            $lockedBooking->update([
                'total_price' => $priceSummary['payable_amount'],
                'notes' => $payload['notes'] ?? $lockedBooking->notes,
                'status' => $status,
                'hold_expires_at' => null,
                'expired_at' => null,
            ]);

            $this->persistPayment($lockedBooking, $payment);

            $this->syncBookingParticipants($lockedBooking, $lockedBooking->customer, $guests);

            if ($priceSummary['coupon']) {
                $priceSummary['coupon']->increment('used_count');
            }

            return $lockedBooking->refresh()->load($this->bookingRelations());
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function chooseStaffForScheduled(
        ProviderBranch $branch,
        Collection $services,
        string $date,
        string $startTime,
        ?int $staffId = null,
        ?int $ignoreBookingId = null,
        ?int $customerId = null,
        int $participantCount = 1
    ): ProviderStaff {
        $duration = $this->totals($services, $participantCount)['total_duration'];
        $eligibleStaff = $this->eligibleStaff($branch, $services, $date, $staffId);

        foreach ($eligibleStaff as $staff) {
            if ($this->staffCanTakeSlot($branch, $staff, $date, $startTime, $duration, $ignoreBookingId, $customerId)) {
                return $staff;
            }
        }

        throw ValidationException::withMessages([
            'staff_id' => $staffId
                ? 'Staff tidak tersedia untuk service dan slot yang dipilih.'
                : 'No staff are available for this time yet.',
        ]);
    }

    public function rescheduleBooking(Booking $booking, array $payload): Booking
    {
        $booking->loadMissing(['branch.provider.providerProfile', 'services']);

        if ($booking->booking_type !== 'scheduled') {
            throw ValidationException::withMessages([
                'booking_type' => 'Hanya booking jam pasti yang bisa di-reschedule.',
            ]);
        }

        if (! in_array($booking->status, self::RESCHEDULABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Booking ini tidak bisa di-reschedule.',
            ]);
        }

        $currentBookingDate = $booking->booking_date
            ? Carbon::parse($booking->booking_date)->startOfDay()
            : null;

        if (! $currentBookingDate || $currentBookingDate->lte(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'booking_date' => 'Reschedule hanya bisa dilakukan paling lambat H-1 dari tanggal booking.',
            ]);
        }

        $branch = $booking->branch;

        if (! $branch || ! $this->branchIsBookable($branch)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch belum tersedia untuk reschedule.',
            ]);
        }

        $serviceIds = $booking->services
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $services = $this->servicesForBooking($branch, $serviceIds, 'scheduled');
        $bookingDate = $payload['booking_date'] ?? null;
        $startTime = $payload['start_time'] ?? null;

        if (blank($bookingDate) || blank($startTime)) {
            throw ValidationException::withMessages([
                'booking_date' => 'A new date and time are required.',
                'start_time' => 'A new date and time are required.',
            ]);
        }

        if (Carbon::parse($bookingDate)->isBefore(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'booking_date' => 'The new date cannot be in the past.',
            ]);
        }

        if (Carbon::parse($bookingDate . ' ' . $startTime)->lte(now())) {
            throw ValidationException::withMessages([
                'start_time' => 'The new time has passed. Please choose a later time.',
            ]);
        }

        $staffId = filled($payload['staff_id'] ?? null) ? (int) $payload['staff_id'] : null;
        $participantCount = max(1, (int) ($booking->participant_count ?: 1));
        $staff = $this->chooseStaffForScheduled(
            $branch,
            $services,
            $bookingDate,
            $startTime,
            $staffId,
            (int) $booking->id,
            null,
            $participantCount
        );
        $totals = $this->totals($services, $participantCount);
        $estimatedEndTime = Carbon::parse($bookingDate . ' ' . $startTime)
            ->addMinutes($totals['total_duration'])
            ->format('H:i');

        return DB::transaction(function () use ($booking, $branch, $bookingDate, $startTime, $staff, $estimatedEndTime, $totals) {
            ProviderStaff::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();

            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedBooking->status, self::RESCHEDULABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Booking ini tidak bisa di-reschedule.',
                ]);
            }

            if (! $this->staffCanTakeSlot(
                $branch,
                $staff,
                $bookingDate,
                $startTime,
                $totals['total_duration'],
                (int) $lockedBooking->id
            )) {
                throw ValidationException::withMessages([
                    'start_time' => 'This time was just booked by another customer. Please choose another time.',
                ]);
            }

            $lockedBooking->update([
                'booking_date' => $bookingDate,
                'start_time' => $startTime,
                'estimated_end_time' => $estimatedEndTime,
                'staff_id' => $staff->id,
                'queue_number' => null,
                'total_duration' => $totals['total_duration'],
                'status' => 'rescheduled',
            ]);

            return $lockedBooking->refresh()->load($this->bookingRelations());
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function chooseStaffForQueue(
        ProviderBranch $branch,
        Collection $services,
        ?int $staffId = null,
        ?string $date = null
    ): ?ProviderStaff {
        $date = $date ?: now()->toDateString();
        $eligibleStaff = $this->eligibleStaff($branch, $services, $date, $staffId);

        if ($eligibleStaff->isEmpty()) {
            throw ValidationException::withMessages([
                'staff_id' => 'No staff member has all the skills required for these services yet.',
            ]);
        }

        // STRICT VALIDATION: Check if branch & staff are operating RIGHT NOW
        $now = now();
        $availableStaff = $eligibleStaff->filter(function ($staff) use ($branch, $date, $now) {
            $windows = $this->workingWindows($branch, $staff, $date);
            foreach ($windows as $window) {
                $start = \Carbon\Carbon::parse($date . ' ' . $window['start']);
                $end = \Carbon\Carbon::parse($date . ' ' . $window['end']);
                if ($now->between($start, $end)) {
                    return true;
                }
            }
            return false;
        });

        if ($availableStaff->isEmpty()) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang atau Staff sedang di luar jam operasional atau libur saat ini.',
            ]);
        }

        $eligibleStaff = $availableStaff;

        if ($staffId) {
            return $eligibleStaff->first();
        }

        return $eligibleStaff
            ->sortBy(fn (ProviderStaff $staff) => $this->activeWorkloadMinutes($staff, $date))
            ->first();
    }

    public function eligibleStaff(ProviderBranch $branch, Collection $services, ?string $date = null, ?int $staffId = null): Collection
    {
        return $this->resolveEligibleStaff->execute($branch, $services, $date, $staffId);
    }

    public function queueEstimation(ProviderBranch $branch, int $requestedDuration, ?int $staffId = null): array
    {
        $date = now()->toDateString();
        $query = Booking::query()
            ->where('branch_id', $branch->id)
            ->whereDate('booking_date', $date)
            ->whereIn('booking_type', ['queue', 'walk_in'])
            ->whereIn('status', ['waiting', 'checked_in', 'in_progress', 'inprogress'])
            ->when($staffId, fn ($query) => $query->where('staff_id', $staffId));

        $waitingMinutes = (int) $query->get()->sum(fn (Booking $booking) => (int) ($booking->total_duration ?: 30));
        $waitingCount = (clone $query)->count();

        if (! $staffId) {
            $staffCount = max(1, ProviderStaff::where('branch_id', $branch->id)->where('status', 'active')->count());
            $waitingMinutes = (int) ceil($waitingMinutes / $staffCount);
        }

        $min = max(0, $waitingMinutes - 10);
        $max = max(10, $waitingMinutes + (int) ceil($requestedDuration / 2) + 10);

        return [
            'waiting_count' => $waitingCount,
            'estimated_wait_min' => $min,
            'estimated_wait_max' => $max,
            'label' => "{$min} - {$max} menit",
        ];
    }

    public function staffCanTakeSlot(
        ProviderBranch $branch,
        ProviderStaff $staff,
        string $date,
        string $startTime,
        int $duration,
        ?int $ignoreBookingId = null,
        ?int $customerId = null
    ): bool
    {
        $slotStart = Carbon::parse($date . ' ' . $startTime);
        $slotEnd = $slotStart->copy()->addMinutes($duration);

        if ($slotStart->lte(now())) {
            return false;
        }

        $insideWorkingWindow = collect($this->workingWindows($branch, $staff, $date))
            ->contains(function (array $window) use ($date, $slotStart, $slotEnd) {
                $windowStart = Carbon::parse($date . ' ' . $window['start']);
                $windowEnd = Carbon::parse($date . ' ' . $window['end']);

                return $slotStart->gte($windowStart) && $slotEnd->lte($windowEnd);
            });

        return $insideWorkingWindow
            && $this->slotMatchesWorkingWindowStep($branch, $staff, $date, $startTime, $duration)
            && ! $this->hasStaffConflict($staff, $date, $startTime, $duration, $ignoreBookingId, $customerId)
            && ! $this->checkConflict->hasParticipantStaffConflict($staff, $date, $startTime, $duration, $ignoreBookingId, $customerId);
    }

    public function hasStaffConflict(
        ProviderStaff $staff,
        string $date,
        string $startTime,
        int $duration,
        ?int $ignoreBookingId = null,
        ?int $customerId = null
    ): bool
    {
        return $this->checkConflict->execute($staff, $date, $startTime, $duration, $ignoreBookingId, $customerId);
    }

    private function slotMatchesWorkingWindowStep(
        ProviderBranch $branch,
        ProviderStaff $staff,
        string $date,
        string $startTime,
        int $duration
    ): bool {
        $slotStart = Carbon::parse($date . ' ' . $startTime);
        $slotEnd = $slotStart->copy()->addMinutes($duration);
        $stepMinutes = max(1, $duration);

        return collect($this->workingWindows($branch, $staff, $date))
            ->contains(function (array $window) use ($date, $slotStart, $slotEnd, $stepMinutes) {
                $windowStart = Carbon::parse($date . ' ' . $window['start']);
                $windowEnd = Carbon::parse($date . ' ' . $window['end']);

                if (! $slotStart->gte($windowStart) || ! $slotEnd->lte($windowEnd)) {
                    return false;
                }

                $minutesFromWindowStart = (int) $windowStart->diffInMinutes($slotStart, false);

                return $minutesFromWindowStart >= 0
                    && $minutesFromWindowStart % $stepMinutes === 0;
            });
    }

    private function participantCount(array $payload): int
    {
        $count = array_key_exists('participant_count', $payload)
            ? (int) $payload['participant_count']
            : count((array) ($payload['guests'] ?? [])) + 1;

        if ($count < 1 || $count > 5) {
            throw ValidationException::withMessages([
                'participant_count' => 'Jumlah peserta harus antara 1 sampai 5 orang.',
            ]);
        }

        return $count;
    }

    private function validatedGuests(array $payload, int $participantCount, bool $detailsRequired): array
    {
        $guests = collect((array) ($payload['guests'] ?? []))
            ->map(fn ($guest) => [
                'name' => trim((string) ($guest['name'] ?? '')),
                'phone' => trim((string) ($guest['phone'] ?? '')),
                'email' => filled($guest['email'] ?? null) ? trim((string) $guest['email']) : null,
                'gender' => filled($guest['gender'] ?? null) ? (string) $guest['gender'] : null,
                'age_group' => filled($guest['age_group'] ?? null) ? (string) $guest['age_group'] : null,
                'description' => filled($guest['description'] ?? null) ? trim((string) $guest['description']) : null,
            ])
            ->values();
        $expectedGuestCount = $participantCount - 1;

        if ($guests->count() > $expectedGuestCount || ($detailsRequired && $guests->count() !== $expectedGuestCount)) {
            throw ValidationException::withMessages([
                'guests' => "Lengkapi data diri untuk {$expectedGuestCount} orang tambahan.",
            ]);
        }

        if ($detailsRequired) {
            $incompleteGuest = $guests->search(fn (array $guest) =>
                $guest['name'] === ''
                || $guest['phone'] === ''
                || $guest['gender'] === null
                || $guest['age_group'] === null
            );

            if ($incompleteGuest !== false) {
                $position = (int) $incompleteGuest + 1;

                throw ValidationException::withMessages([
                    "guests.{$incompleteGuest}.name" => "Name, phone number, gender, and age group are required for additional person {$position}.",
                ]);
            }
        }

        return $guests->all();
    }

    private function syncBookingParticipants(Booking $booking, ?User $customer, array $guests): void
    {
        $booking->loadMissing('participants');

        // A personal booking already owns its customer, professional, schedule,
        // services, duration, and price on the booking aggregate. Persisting the
        // same person again as participant position 1 only duplicates data.
        if ((int) $booking->participant_count === 1) {
            $booking->participants()->delete();
            $booking->unsetRelation('participants');

            return;
        }

        $hasParticipantSelections = $booking->participants->isNotEmpty()
            && $booking->participants->every(fn (BookingParticipant $participant) =>
                $participant->booking_date && $participant->start_time
            );

        if ($hasParticipantSelections) {
            $primary = $booking->participants->firstWhere('is_primary', true)
                ?: $booking->participants->firstWhere('position', 1);
            $primary?->update([
                'name' => $booking->customer_name ?: ($customer?->name ?: 'Customer'),
                'phone' => $booking->customer_phone ?: optional($customer?->customerProfile)->phone_number,
                'email' => $customer?->email,
                'gender' => optional($customer?->customerProfile)->gender,
            ]);

            foreach ($guests as $index => $guest) {
                $booking->participants->firstWhere('position', $index + 2)?->update([
                    'name' => $guest['name'],
                    'phone' => $guest['phone'],
                    'email' => $guest['email'],
                    'gender' => $guest['gender'],
                    'age_group' => $guest['age_group'],
                    'description' => $guest['description'],
                ]);
            }

            return;
        }

        $booking->participants()->delete();
        $booking->participants()->create([
            'position' => 1,
            'is_primary' => true,
            'name' => $booking->customer_name ?: ($customer?->name ?: 'Customer'),
            'phone' => $booking->customer_phone ?: optional($customer?->customerProfile)->phone_number,
            'email' => $customer?->email,
            'gender' => optional($customer?->customerProfile)->gender,
        ]);

        foreach ($guests as $index => $guest) {
            $booking->participants()->create([
                'position' => $index + 2,
                'is_primary' => false,
                'name' => $guest['name'],
                'phone' => $guest['phone'],
                'email' => $guest['email'],
                'gender' => $guest['gender'],
                'age_group' => $guest['age_group'],
                'description' => $guest['description'],
            ]);
        }
    }

    public function bookingRelations(): array
    {
        return [
            'provider:id,name,email',
            'provider.providerProfile:user_id,status,document_status,image',
            'customer:id,name,email',
            'customer.customerProfile',
            'branch',
            'staff',
            'services.serviceCategory',
            'payment',
            'participants.staff',
            'participants.services.serviceCategory',
            'branchReview',
            'staffReview',
        ];
    }

    public function completeBooking(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking->update([
                'status' => 'completed',
                'actual_end_time' => now(),
                'completed_at' => now(),
            ]);

            if ($booking->staff) {
                $booking->staff->update(['current_status' => 'available']);
            }

            if ($booking->payment?->payment_type === 'pay_at_salon') {
                $booking->payment->update([
                    'amount' => $booking->total_price,
                    'status' => 'paid',
                    'payment_method' => 'pay_at_salon',
                    'paid_at' => now(),
                ]);
            }

            return $booking->refresh()->load($this->bookingRelations());
        });
    }

    public function updateStatus(Booking $booking, string $status): Booking
    {
        return DB::transaction(function () use ($booking, $status) {
            $updates = ['status' => $status];

            if ($status === 'checked_in' && ! $booking->checked_in_at) {
                $updates['checked_in_at'] = now();
            }

            if ($status === 'in_progress') {
                $updates['actual_start_time'] = $booking->actual_start_time ?: now();

                if ($booking->staff) {
                    $booking->staff->update(['current_status' => 'busy']);
                }
            }

            if (in_array($status, ['cancelled', 'customer_cancelled', 'provider_cancelled', 'no_show'], true) && $booking->staff) {
                $booking->staff->update(['current_status' => 'available']);
            }

            if (in_array($status, ['cancelled', 'customer_cancelled', 'provider_cancelled', 'no_show', self::STATUS_EXPIRED_HOLD, self::STATUS_PAYMENT_EXPIRED], true)) {
                $updates['hold_expires_at'] = null;
                $updates['idempotency_key'] = null;
                $updates['expired_at'] = in_array($status, [self::STATUS_EXPIRED_HOLD, self::STATUS_PAYMENT_EXPIRED], true)
                    ? now()
                    : $booking->expired_at;
            }

            $booking->update($updates);
            $booking->loadMissing('payment');

            if (in_array($status, ['cancelled', 'customer_cancelled', 'provider_cancelled', 'no_show'], true) && $booking->payment && ! in_array($booking->payment->status, ['paid', 'refunded'], true)) {
                $booking->payment->update(['status' => 'failed']);
            }

            return $booking->refresh()->load($this->bookingRelations());
        });
    }

    public function releaseExpiredHolds(?int $customerId = null): int
    {
        $holds = Booking::query()
            ->whereIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->with('payment')
            ->get();

        return $holds->filter(fn (Booking $booking) => $this->discardBookingHold($booking))->count();
    }

    public function bookingIsTemporaryHold(Booking $booking): bool
    {
        return in_array($booking->status, [self::STATUS_PENDING_HOLD, 'pending'], true)
            && $booking->hold_expires_at !== null;
    }

    public function bookingHasActiveHold(Booking $booking): bool
    {
        return $this->bookingIsTemporaryHold($booking)
            && $booking->hold_expires_at->gt(now());
    }

    public function discardBookingHold(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            /** @var Booking|null $lockedBooking */
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedBooking || ! $this->bookingIsTemporaryHold($lockedBooking)) {
                return false;
            }

            // Payments, services, participants, and any linked activity use
            // cascading foreign keys, so an abandoned hold leaves no booking
            // history behind.
            return (bool) $lockedBooking->delete();
        });
    }

    public function bookingIsFinalized(Booking $booking): bool
    {
        return in_array($booking->status, self::FINALIZED_STATUSES, true)
            && ! $booking->hold_expires_at;
    }

    public function activeHoldIdForCustomerSelection(
        int $customerId,
        ProviderBranch $branch,
        Collection $services,
        ?string $bookingDate,
        ?int $staffId = null,
        int $participantCount = 1
    ): ?int {
        if (! $bookingDate || $services->isEmpty()) {
            return null;
        }

        $serviceIds = $services
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $hold = Booking::query()
            ->with('services:id')
            ->where('customer_id', $customerId)
            ->where('branch_id', $branch->id)
            ->whereDate('booking_date', $bookingDate)
            ->whereIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '>', now())
            ->where('participant_count', max(1, $participantCount))
            ->when($staffId, fn ($query) => $query->where('staff_id', $staffId))
            ->latest('hold_expires_at')
            ->get()
            ->first(function (Booking $booking) use ($serviceIds) {
                $heldServiceIds = $booking->services
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                return $heldServiceIds === $serviceIds;
            });

        return $hold?->id;
    }

    public function extendHeldBooking(Booking $booking): Booking
    {
        $this->releaseExpiredHolds((int) $booking->customer_id);

        return DB::transaction(function () use ($booking) {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()
                ->with(['payment', 'services'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->bookingHasActiveHold($lockedBooking)) {
                throw ValidationException::withMessages([
                    'booking' => 'The booking hold has expired. Please choose a schedule again.',
                ]);
            }

            $lockedBooking->update([
                'hold_expires_at' => now()->addMinutes(self::BOOKING_HOLD_MINUTES),
                'expired_at' => null,
            ]);

            return $lockedBooking->refresh()->load($this->bookingRelations());
        });
    }

    private function holdExpirationFromPayload(array $payload): Carbon
    {
        $maximumExpiry = now()->addMinutes(self::BOOKING_HOLD_MINUTES);
        $requestedExpiry = filled($payload['booking_hold_expires_at'] ?? null)
            ? Carbon::parse($payload['booking_hold_expires_at'])->setTimezone(config('app.timezone'))
            : null;

        if (! $requestedExpiry) {
            return $maximumExpiry;
        }

        if ($requestedExpiry->lte(now())) {
            throw ValidationException::withMessages([
                'booking_hold_expires_at' => 'The booking hold has expired. Please start your reservation again.',
            ]);
        }

        return $requestedExpiry->lt($maximumExpiry)
            ? $requestedExpiry
            : $maximumExpiry;
    }

    private function cancelActiveHoldsForCustomer(int $customerId, ?int $branchId = null): int
    {
        $holds = Booking::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '>', now())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->with('payment')
            ->get();

        return $holds->filter(fn (Booking $booking) => $this->discardBookingHold($booking))->count();
    }

    private function staffPayload(ProviderStaff $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->full_name ?: $staff->email,
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'gender' => $staff->gender,
            'bio' => $staff->bio,
            'role' => $staff->role,
            'image' => $staff->image,
            'rating' => $staff->rating,
            'current_status' => $staff->current_status,
            'status' => $staff->status,
            'branch_id' => $staff->branch_id,
            'skills' => $staff->skills->map(fn (Service $service) => [
                'id' => $service->id,
                'title' => $service->title,
            ])->values(),
            'schedules' => $staff->schedules->map(fn ($schedule) => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => substr((string) $schedule->end_time, 0, 5),
                'is_available' => (bool) $schedule->is_available,
            ])->values(),
        ];
    }

    private function isStaffWorking(ProviderBranch $branch, ProviderStaff $staff, string $date): bool
    {
        return count($this->workingWindows($branch, $staff, $date)) > 0;
    }

    private function workingWindows(ProviderBranch $branch, ProviderStaff $staff, string $date): array
    {
        if (! $this->branchWorksOnDate($branch, $date)) {
            return [];
        }

        $dayAliases = $this->dayAliases(Carbon::parse($date));
        $schedules = $staff->schedules
            ->filter(function ($schedule) use ($dayAliases) {
                return $schedule->is_available
                    && in_array(Str::lower((string) $schedule->day_of_week), $dayAliases, true);
            })
            ->values();

        if ($schedules->isEmpty()) {
            return [[
                'start' => $this->shortTime($branch->working_start_hour ?: '09:00'),
                'end' => $this->shortTime($branch->working_end_hour ?: '18:00'),
            ]];
        }

        $branchStart = $this->shortTime($branch->working_start_hour ?: '09:00');
        $branchEnd = $this->shortTime($branch->working_end_hour ?: '18:00');

        return $schedules
            ->map(function ($schedule) use ($branchStart, $branchEnd) {
                $start = max($this->shortTime($schedule->start_time), $branchStart);
                $end = min($this->shortTime($schedule->end_time), $branchEnd);

                return compact('start', 'end');
            })
            ->filter(fn (array $window) => $window['start'] < $window['end'])
            ->values()
            ->all();
    }

    private function branchWorksOnDate(ProviderBranch $branch, string $date): bool
    {
        $workingDays = collect((array) $branch->working_days)->map(fn ($day) => Str::lower((string) $day))->all();

        if (empty($workingDays)) {
            return true;
        }

        return count(array_intersect($workingDays, $this->dayAliases(Carbon::parse($date)))) > 0;
    }

    private function dayAliases(Carbon $date): array
    {
        $aliases = [
            0 => ['0', 'sunday', 'sun', 'minggu', 'ahad'],
            1 => ['1', 'monday', 'mon', 'senin'],
            2 => ['2', 'tuesday', 'tue', 'selasa'],
            3 => ['3', 'wednesday', 'wed', 'rabu'],
            4 => ['4', 'thursday', 'thu', 'kamis'],
            5 => ['5', 'friday', 'fri', 'jumat', "jum'at"],
            6 => ['6', 'saturday', 'sat', 'sabtu'],
        ];

        return $aliases[$date->dayOfWeek] ?? [];
    }

    private function shortTime(mixed $value): string
    {
        return substr((string) $value, 0, 5);
    }

    private function activeWorkloadMinutes(ProviderStaff $staff, string $date): int
    {
        return (int) Booking::query()
            ->where('staff_id', $staff->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', ['waiting', 'checked_in', 'in_progress', 'inprogress'])
            ->sum('total_duration');
    }

    private function nextQueueNumber(ProviderBranch $branch, string $date): int
    {
        return ((int) Booking::query()
            ->where('branch_id', $branch->id)
            ->whereDate('booking_date', $date)
            ->whereIn('booking_type', ['queue', 'walk_in'])
            ->max('queue_number')) + 1;
    }

    private function persistPayment(Booking $booking, array $payment): Payment
    {
        $paymentModel = Payment::query()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'payment_type' => $payment['payment_type'],
                'amount' => $payment['amount'],
                'status' => $payment['status'],
                'payment_method' => $payment['payment_method'],
                'paid_at' => $payment['paid_at'],
            ]
        );

        if ($payment['payment_type'] === 'pay_at_salon') {
            $paymentModel->gatewayTransaction()->delete();
        } else {
            $paymentModel->gatewayTransaction()->updateOrCreate(
                [],
                [
                    'gateway' => 'midtrans',
                    'payment_channel' => $payment['payment_channel'],
                    'provider_order_id' => null,
                    'provider_transaction_id' => null,
                    'provider_status' => null,
                    'fraud_status' => null,
                    'payment_code_label' => null,
                    'payment_code' => null,
                    'biller_code' => null,
                    'qr_url' => null,
                    'deeplink_url' => null,
                    'expires_at' => $payment['expiry_time'],
                    'raw_response' => null,
                    'raw_notification' => null,
                ]
            );
        }

        return $paymentModel->refresh();
    }

    private function paymentPayload(string $paymentType, Collection $services, float $totalPrice, ?string $paymentChannel = null): array
    {
        $paymentType = in_array($paymentType, ['dp', 'full_payment', 'pay_at_salon'], true)
            ? $paymentType
            : 'pay_at_salon';
        $paymentChannel = in_array($paymentChannel, MidtransService::CHANNELS, true)
            ? $paymentChannel
            : null;

        if ($paymentType === 'pay_at_salon') {
            return [
                'payment_type' => 'pay_at_salon',
                'amount' => 0,
                'status' => 'unpaid',
                'payment_method' => 'pay_at_salon',
                'payment_channel' => null,
                'paid_at' => null,
                'expiry_time' => null,
            ];
        }

        if ($paymentType === 'dp') {
            $configuredDp = (float) $services->sum(fn (Service $service) => (float) ($service->dp_amount ?? 0));
            $amount = $configuredDp > 0 ? $configuredDp : round($totalPrice * 0.3, 2);

            return [
                'payment_type' => 'dp',
                'amount' => $amount,
                'status' => 'pending',
                'payment_method' => 'manual',
                'payment_channel' => $paymentChannel,
                'paid_at' => null,
                'expiry_time' => now()->addMinutes(MidtransService::PAYMENT_EXPIRY_MINUTES),
            ];
        }

        return [
            'payment_type' => 'full_payment',
            'amount' => $totalPrice,
            'status' => 'pending',
            'payment_method' => 'manual',
            'payment_channel' => $paymentChannel,
            'paid_at' => null,
            'expiry_time' => now()->addMinutes(MidtransService::PAYMENT_EXPIRY_MINUTES),
        ];
    }

    private function uniqueBookingCode(): string
    {
        do {
            $code = 'BK-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }
}
