<?php

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'title',
        'slug',
        'category',
        'category_id',
        'code',
        'description',
        'includes',
        'price_type',
        'price',
        'minimum_duration',
        'estimated_duration',
        'maximum_duration',
        'is_queue_enabled',
        'is_scheduled_enabled',
        'requires_dp',
        'dp_amount',
        'payment_policy',
        'slots',
        'additional_services',
        'holidays',
        'branch_ids',
        'gallery_image',
        'video_url',
        'status',
        'verify_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'minimum_duration' => 'integer',
        'estimated_duration' => 'integer',
        'maximum_duration' => 'integer',
        'is_queue_enabled' => 'boolean',
        'is_scheduled_enabled' => 'boolean',
        'requires_dp' => 'boolean',
        'dp_amount' => 'decimal:2',
        'slots' => 'array',
        'additional_services' => 'array',
        'holidays' => 'array',
        'branch_ids' => 'array',
    ];

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_services')
            ->withPivot(['price', 'estimated_duration'])
            ->withTimestamps();
    }

    public function categoryModel()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function staff()
    {
        return $this->belongsToMany(ProviderStaff::class, 'staff_skills', 'service_id', 'staff_id')
            ->withTimestamps();
    }

    public function multiServiceBookings()
    {
        return $this->bookings();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopePubliclyCategorized(Builder $query): Builder
    {
        return $query->whereHas(
            'serviceCategory',
            fn (Builder $categoryQuery) => $categoryQuery->publicLeaves()
        );
    }

    public function providerDocumentStatus(): string
    {
        return optional($this->provider?->providerProfile)->document_status ?? 'pending';
    }

    public function isProviderDocumentVerified(): bool
    {
        return $this->providerDocumentStatus() === 'verified';
    }

    public function getHasOngoingBookingsAttribute(): bool
    {
        return $this->multiServiceBookings()->whereIn('status', ['open', 'pending', 'inprogress', 'rescheduled'])->exists();
    }

    public function getCanBeDeletedAttribute(): bool
    {
        return ! $this->multiServiceBookings()->exists();
    }
}
