<?php

namespace App\Modules\Booking\Infrastructure\Persistence\Models;

use App\Modules\Booking\Infrastructure\Persistence\Models\BookingParticipant;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerActivity;
use App\Modules\Customer\Infrastructure\Persistence\Models\CustomerProfile;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Review\Infrastructure\Persistence\Models\BranchReview;
use App\Modules\Review\Infrastructure\Persistence\Models\StaffReview;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;

class Booking extends Model
{
    use HasFactory;

    protected $appends = ['payment_status'];

    public const CUSTOMER_ACTIVITY_STATUSES = [
        'pending_payment',
        'confirmed',
        'waiting',
        'checked_in',
        'in_progress',
        'inprogress',
        'rescheduled',
        'completed',
        'order_completed',
        'cancelled',
        'customer_cancelled',
        'provider_cancelled',
        'no_show',
        'payment_expired',
        'refund_completed',
    ];

    protected static function booted(): void
    {
        $recordActivity = function (self $booking): void {
            if ($booking->customer_id && in_array($booking->status, self::CUSTOMER_ACTIVITY_STATUSES, true)) {
                CustomerActivity::recordBooking($booking);
            }
        };

        static::created($recordActivity);
        static::updated($recordActivity);
    }

    protected $fillable = [
        'booking_code',
        'booking_date',
        'provider_id',
        'customer_id',
        'branch_id',
        'staff_id',
        'booking_type',
        'start_time',
        'estimated_end_time',
        'actual_start_time',
        'actual_end_time',
        'total_duration',
        'total_price',
        'customer_name',
        'customer_phone',
        'participant_count',
        'notes',
        'queue_number',
        'checked_in_at',
        'completed_at',
        'status',
        'held_at',
        'hold_expires_at',
        'expired_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'total_price' => 'decimal:2',
            'total_duration' => 'integer',
            'participant_count' => 'integer',
            'actual_start_time' => 'datetime',
            'actual_end_time' => 'datetime',
            'checked_in_at' => 'datetime',
            'completed_at' => 'datetime',
            'held_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function branch()
    {
        return $this->belongsTo(ProviderBranch::class, 'branch_id');
    }

    public function staff()
    {
        return $this->belongsTo(ProviderStaff::class, 'staff_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'booking_services')
            ->withPivot(['price', 'estimated_duration'])
            ->withTimestamps();
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function getPaymentStatusAttribute(): string
    {
        if ($this->relationLoaded('payment')) {
            return $this->payment?->status ?? 'unpaid';
        }

        return $this->payment()->value('status') ?? 'unpaid';
    }

    public function participants()
    {
        return $this->hasMany(BookingParticipant::class)->orderBy('position');
    }

    /**
     * Build the operational appointments represented by this transaction.
     *
     * Personal bookings produce one appointment from the booking header. Group
     * bookings produce one appointment per participant while keeping payment
     * and lifecycle status attached to the single parent booking.
     */
    public function operationalEntries(): Collection
    {
        $this->loadMissing([
            'customer.customerProfile',
            'branch',
            'staff',
            'services',
            'payment',
            'participants.staff',
            'participants.services',
        ]);

        $participantCount = max(1, (int) ($this->participant_count ?: 1));
        $participants = $this->participants
            ->sortBy('position')
            ->values();

        if ($participantCount <= 1 || $participants->isEmpty()) {
            return collect([$this->operationalEntry(null, 1, 1)]);
        }

        return $participants
            ->map(fn (BookingParticipant $participant) => $this->operationalEntry(
                $participant,
                max(1, (int) $participant->position),
                $participantCount
            ))
            ->values();
    }

    private function operationalEntry(?BookingParticipant $participant, int $position, int $participantCount): object
    {
        $isGroup = $participantCount > 1;
        $customerProfile = $this->customer?->customerProfile;
        $services = $participant?->services?->isNotEmpty()
            ? $participant->services
            : $this->services;
        $serviceDuration = (int) $services->sum(
            fn ($service) => (int) ($service->pivot?->estimated_duration ?: $service->estimated_duration ?: 0)
        );
        $servicePrice = (float) $services->sum(
            fn ($service) => (float) ($service->pivot?->price ?? $service->price ?? 0)
        );
        $duration = (int) ($participant?->total_duration ?: $serviceDuration);
        $price = (float) ($participant?->total_price ?: $servicePrice);

        if ($duration <= 0) {
            $duration = $isGroup
                ? (int) round(((int) $this->total_duration) / $participantCount)
                : (int) $this->total_duration;
        }

        if ($price <= 0) {
            $price = $isGroup
                ? round(((float) $this->total_price) / $participantCount, 2)
                : (float) $this->total_price;
        }

        $bookingCode = $this->booking_code ?: ('#' . $this->id);

        return (object) [
            'booking' => $this,
            'participant' => $participant,
            'position' => $position,
            'participant_count' => $participantCount,
            'is_group' => $isGroup,
            'participant_label' => $isGroup ? "Participant {$position} of {$participantCount}" : null,
            'display_code' => $isGroup ? "{$bookingCode}-P{$position}" : $bookingCode,
            'customer_name' => $participant?->name
                ?: $this->customer_name
                ?: $this->customer?->name
                ?: 'Walk-in',
            'customer_phone' => $participant?->phone
                ?: $this->customer_phone
                ?: $this->customer?->customerProfile?->phone_number,
            'customer_email' => $participant?->email ?: $this->customer?->email,
            'customer_gender' => $participant?->gender ?: $customerProfile?->gender,
            'participant_age_group' => $participant?->age_group,
            'customer_date_of_birth' => $participant && ! $participant->is_primary ? null : $customerProfile?->date_of_birth,
            'customer_religion' => $participant && ! $participant->is_primary ? null : $customerProfile?->religion,
            'customer_allergies' => $participant && ! $participant->is_primary ? null : $customerProfile?->allergies,
            'customer_address' => $participant && ! $participant->is_primary ? null : collect([
                $customerProfile?->address_line_1,
                $customerProfile?->address_line_2,
                $customerProfile?->city,
                $customerProfile?->state,
                $customerProfile?->country,
            ])->filter()->join(', '),
            'participant_description' => $participant?->description,
            'booking_date' => $participant?->booking_date ?: $this->booking_date,
            'start_time' => $participant?->start_time ?: $this->start_time,
            'estimated_end_time' => $participant?->estimated_end_time ?: $this->estimated_end_time,
            'total_duration' => $duration,
            'total_price' => $price,
            'booking_total_price' => (float) $this->total_price,
            'staff' => $participant?->staff ?: $this->staff,
            'services' => $services,
        ];
    }

    public function branchReview()
    {
        return $this->hasOne(BranchReview::class);
    }

    public function staffReview()
    {
        return $this->hasOne(StaffReview::class);
    }
}
