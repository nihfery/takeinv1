<?php

namespace App\Modules\Availability\Application\Actions;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Booking\Infrastructure\Persistence\Models\BookingParticipant;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CheckConflict
{
    private const STATUS_PENDING_HOLD = 'pending_hold';

    private const ACTIVE_BOOKING_STATUSES = [
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

    public function execute(
        ProviderStaff $staff,
        string $date,
        string $startTime,
        int $duration,
        ?int $ignoreBookingId = null,
        ?int $customerId = null
    ): bool {
        $bookings = Booking::query()
            ->where('staff_id', $staff->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->when($ignoreBookingId, fn ($query) => $query->where('id', '!=', $ignoreBookingId))
            ->when($customerId, fn ($query) => $query->where(function ($nested) use ($customerId) {
                $nested
                    ->where('customer_id', '!=', $customerId)
                    ->orWhereNotIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
                    ->orWhereNull('hold_expires_at')
                    ->orWhere('hold_expires_at', '<=', now());
            }))
            ->get();

        return $this->slotConflictsWithBookings($bookings, $date, $startTime, $duration);
    }

    public function hasParticipantStaffConflict(
        ProviderStaff $staff,
        string $date,
        string $startTime,
        int $duration,
        ?int $ignoreBookingId = null,
        ?int $customerId = null
    ): bool {
        $participants = BookingParticipant::query()
            ->with('booking:id,customer_id,status,hold_expires_at')
            ->where('provider_staff_id', $staff->id)
            ->whereDate('booking_date', $date)
            ->whereNotNull('start_time')
            ->whereHas('booking', function ($query) use ($ignoreBookingId, $customerId) {
                $query
                    ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
                    ->when($ignoreBookingId, fn ($bookingQuery) => $bookingQuery->where('id', '!=', $ignoreBookingId))
                    ->when($customerId, fn ($bookingQuery) => $bookingQuery->where(function ($nested) use ($customerId) {
                        $nested
                            ->where('customer_id', '!=', $customerId)
                            ->orWhereNotIn('status', [self::STATUS_PENDING_HOLD, 'pending'])
                            ->orWhereNull('hold_expires_at')
                            ->orWhere('hold_expires_at', '<=', now());
                    }));
            })
            ->get();

        $requestedStart = Carbon::parse($date . ' ' . $startTime);
        $requestedEnd = $requestedStart->copy()->addMinutes($duration);

        return $participants->contains(function (BookingParticipant $participant) use ($date, $requestedStart, $requestedEnd) {
            $participantStart = Carbon::parse($date . ' ' . $participant->start_time);
            $participantEnd = $participant->estimated_end_time
                ? Carbon::parse($date . ' ' . $participant->estimated_end_time)
                : $participantStart->copy()->addMinutes((int) ($participant->total_duration ?: 30));

            return $requestedStart->lt($participantEnd) && $requestedEnd->gt($participantStart);
        });
    }

    public function slotConflictsWithBookings(Collection $bookings, string $date, string $startTime, int $duration): bool
    {
        $requestedStart = Carbon::parse($date . ' ' . $startTime);
        $requestedEnd = $requestedStart->copy()->addMinutes($duration);

        return $bookings->contains(function (Booking $booking) use ($date, $requestedStart, $requestedEnd) {
            $bookingStartValue = $booking->start_time;

            if (! $bookingStartValue) {
                return false;
            }

            $bookingStart = Carbon::parse($date . ' ' . $bookingStartValue);
            $bookingEnd = $booking->estimated_end_time
                ? Carbon::parse($date . ' ' . $booking->estimated_end_time)
                : $bookingStart->copy()->addMinutes((int) ($booking->total_duration ?: 30));

            return $requestedStart->lt($bookingEnd) && $requestedEnd->gt($bookingStart);
        });
    }
}
