<?php

namespace App\Modules\Booking\Application\Queries;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ResolveBookingServices
{
    public function normalizedServiceIds(array $payload): array
    {
        $serviceIds = $payload['service_ids'] ?? [];

        return collect((array) $serviceIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function branchIsBookable(ProviderBranch $branch, bool $isWalkIn = false): bool
    {
        $branch->loadMissing('provider.providerProfile');

        $profile = optional($branch->provider?->providerProfile);

        if ($isWalkIn) {
            return $branch->status === 'active'
                && $branch->provider?->role === 'provider';
        }

        return $branch->status === 'active'
            && $branch->provider?->role === 'provider'
            && $profile->status === 'active'
            && $profile->document_status === 'verified';
    }

    public function servicesForBooking(ProviderBranch $branch, array $serviceIds, string $bookingType = 'scheduled'): Collection
    {
        if (empty($serviceIds)) {
            throw ValidationException::withMessages([
                'service_ids' => 'Please select at least one service.',
            ]);
        }

        $services = Service::query()
            ->with('serviceCategory')
            ->whereIn('id', $serviceIds)
            ->get()
            ->sortBy(fn (Service $service) => array_search((int) $service->id, $serviceIds, true))
            ->values();

        if ($services->count() !== count($serviceIds)) {
            throw ValidationException::withMessages([
                'service_ids' => 'Ada service yang tidak ditemukan.',
            ]);
        }

        $invalidServices = $services->filter(fn (Service $service) => $service->status !== 'active');

        if ($invalidServices->isNotEmpty()) {
            throw ValidationException::withMessages([
                'service_ids' => 'All selected services must be active.',
            ]);
        }

        if ($services->contains(fn (Service $service) => (int) $service->provider_id !== (int) $branch->provider_id)) {
            throw ValidationException::withMessages([
                'service_ids' => 'All services must belong to the same provider branch.',
            ]);
        }

        $services = $services
            ->map(fn (Service $service) => $this->canonicalServiceForBranch($service, $branch))
            ->unique(fn (Service $service) => (int) $service->id)
            ->values();

        $unavailableAtBranch = $services->filter(fn (Service $service) => ! $this->serviceBelongsToBranch($service, $branch));

        if ($unavailableAtBranch->isNotEmpty()) {
            throw ValidationException::withMessages([
                'service_ids' => 'Ada service yang belum tersedia di branch ini.',
            ]);
        }

        if ($bookingType === 'scheduled' && $services->contains(fn (Service $service) => ! $service->is_scheduled_enabled)) {
            throw ValidationException::withMessages([
                'booking_type' => 'Ada service yang belum mendukung booking jam pasti.',
            ]);
        }

        if (in_array($bookingType, ['queue', 'walk_in'], true)
            && $services->contains(fn (Service $service) => ! $service->is_queue_enabled)) {
            throw ValidationException::withMessages([
                'booking_type' => 'Ada service yang belum mendukung antrian.',
            ]);
        }

        return $services;
    }

    private function canonicalServiceForBranch(Service $service, ProviderBranch $branch): Service
    {
        if ($this->serviceBelongsToBranch($service, $branch)) {
            return $service;
        }

        return Service::query()
            ->with('serviceCategory')
            ->where('provider_id', $branch->provider_id)
            ->where('status', 'active')
            ->where('title', $service->title)
            ->get()
            ->first(fn (Service $candidate) => $this->serviceBelongsToBranch($candidate, $branch))
            ?: $service;
    }

    private function serviceBelongsToBranch(Service $service, ProviderBranch $branch): bool
    {
        $branchIds = $service->branch_ids;

        if (empty($branchIds)) {
            return true;
        }

        return in_array((int) $branch->id, array_map('intval', (array) $branchIds), true);
    }
}
