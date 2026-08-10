<?php

namespace App\Modules\Branch\Infrastructure\Persistence\Models;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Review\Infrastructure\Persistence\Models\BranchReview;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class ProviderBranch extends Model
{
    protected $table = 'provider_branches';

    protected $fillable = [
        'provider_id',
        'branch_name',
        'email',
        'phone_code',
        'phone_number',
        'address',
        'country_id',
        'state_id',
        'city_id',
        'latitude',
        'longitude',
        'zip_code',
        'working_start_hour',
        'working_end_hour',
        'working_days',
        'holidays',
        'image',
        'images',
        'status',
    ];

    protected $casts = [
        'working_days' => 'array',
        'holidays' => 'array',
        'images' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected $appends = [
        'image_url',
        'image_urls',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        $path = str_starts_with($this->image, 'storage/')
            ? $this->image
            : 'storage/' . ltrim($this->image, '/');

        return asset($path);
    }

    /**
     * Full gallery of branch photos as resolvable URLs. Falls back to the single
     * cover image when no gallery has been uploaded yet.
     *
     * @return array<int, string>
     */
    public function getImageUrlsAttribute(): array
    {
        $paths = collect($this->images ?? []);

        if ($paths->isEmpty() && $this->image) {
            $paths = collect([$this->image]);
        }

        return $paths
            ->filter()
            ->map(function (string $path) {
                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    return $path;
                }

                return asset(str_starts_with($path, 'storage/') ? $path : 'storage/' . ltrim($path, '/'));
            })
            ->values()
            ->all();
    }

    public function staffs(): HasMany
    {
        return $this->hasMany(ProviderStaff::class, 'branch_id', 'id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'branch_id');
    }

    public function branchReviews()
    {
        return $this->hasManyThrough(
            BranchReview::class,
            Booking::class,
            'branch_id',
            'booking_id',
            'id',
            'id'
        );
    }

    public function servicesForBranch(): Collection
    {
        return Service::query()
            ->where('provider_id', $this->provider_id)
            ->where('status', 'active')
            ->latest()
            ->get()
            ->filter(function (Service $service) {
                $branchIds = $service->branch_ids;

                if (empty($branchIds)) {
                    return true;
                }

                return in_array((int) $this->id, array_map('intval', (array) $branchIds), true);
            })
            ->values();
    }

    public function scopeVisibleToCustomer(Builder $query)
    {
        return $query->where('status', 'active')
            ->whereHas('provider.providerProfile', function ($q) {
                $q->where('status', 'active')
                  ->where('document_status', 'verified');
            });
    }

    public function getHasOngoingBookingsAttribute(): bool
    {
        return $this->bookings()->whereIn('status', ['open', 'pending', 'inprogress', 'rescheduled'])->exists();
    }

    public function getCanBeDeletedAttribute(): bool
    {
        return !$this->bookings()->exists() 
            && !$this->staffs()->exists() 
            && $this->servicesForBranch()->isEmpty();
    }
}
