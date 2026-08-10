<?php

namespace App\Modules\Staff\Infrastructure\Persistence\Models;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ServiceCategory;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderRole;
use App\Modules\Review\Infrastructure\Persistence\Models\StaffReview;
use App\Modules\Staff\Infrastructure\Persistence\Models\StaffSchedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderStaff extends Model
{
    use HasFactory;

    protected $table = 'provider_staffs';

    protected $fillable = [
        'provider_id',
        'image',
        'first_name',
        'last_name',
        'email',
        'username',
        'country_code',
        'phone_number',
        'gender',
        'date_of_birth',
        'address',
        'country_id',
        'state_id',
        'city_id',
        'postal_code',
        'bio',
        'category_id',
        'branch_id',
        'role',
        'provider_role_id',
        'rating',
        'current_status',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'rating' => 'decimal:2',
    ];

    protected $appends = [
        'full_name',
    ];

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function providerRole()
    {
        return $this->belongsTo(ProviderRole::class, 'provider_role_id');
    }

    public function branch()
    {
        return $this->belongsTo(ProviderBranch::class, 'branch_id');
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function skills()
    {
        return $this->belongsToMany(Service::class, 'staff_skills', 'staff_id', 'service_id')
            ->withTimestamps();
    }

    public function schedules()
    {
        return $this->hasMany(StaffSchedule::class, 'staff_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'staff_id');
    }

    public function staffReviews(): HasMany
    {
        return $this->hasMany(StaffReview::class, 'staff_id');
    }

    public function getHasOngoingBookingsAttribute(): bool
    {
        return $this->bookings()->whereIn('status', ['open', 'pending', 'inprogress', 'rescheduled'])->exists();
    }

    public function getCanBeDeletedAttribute(): bool
    {
        return !$this->bookings()->exists();
    }
}
