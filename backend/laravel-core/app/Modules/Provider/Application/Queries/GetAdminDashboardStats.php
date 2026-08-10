<?php

namespace App\Modules\Provider\Application\Queries;

use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

final class GetAdminDashboardStats
{
    public function handle(
        array $activeBookingStatuses,
        array $completedBookingStatuses,
        array $cancelledBookingStatuses,
    ): array {
        $providerQuery = $this->providerOwners();
        $totalProviders = (clone $providerQuery)->count();
        $activeProviders = (clone $providerQuery)
            ->whereHas('providerProfile', fn (Builder $query) => $query->where('status', 'active'))
            ->count();

        $totalServices = Service::query()->count();
        $activeServices = Service::query()->where('status', 'active')->count();

        $bookingQuery = Booking::query();
        $totalBookings = (clone $bookingQuery)->count();
        $completedBookings = (clone $bookingQuery)->whereIn('status', $completedBookingStatuses)->count();
        $pendingBookings = (clone $bookingQuery)->whereIn('status', $activeBookingStatuses)->count();

        return [
            'total_providers' => $totalProviders,
            'active_providers' => $activeProviders,
            'inactive_providers' => max(0, $totalProviders - $activeProviders),

            'total_services' => $totalServices,
            'active_services' => $activeServices,
            'inactive_services' => max(0, $totalServices - $activeServices),

            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedBookings,
            'pending_bookings' => $pendingBookings,
            'cancelled_bookings' => (clone $bookingQuery)->whereIn('status', $cancelledBookingStatuses)->count(),

            'total_amount' => $this->bookingAmount(Booking::query()),
            'completed_amount' => $this->bookingAmount(Booking::query()->whereIn('status', $completedBookingStatuses)),
            'pending_amount' => $this->bookingAmount(Booking::query()->whereIn('status', $activeBookingStatuses)),
            'paid_amount' => (float) Payment::query()->where('status', 'paid')->sum('amount'),
        ];
    }

    public function providerOwners(): Builder
    {
        return User::query()
            ->where('role', 'provider')
            ->whereNull('provider_id')
            ->whereNull('provider_role_id');
    }

    public function bookingAmount(Builder $query): float
    {
        return (float) $query
            ->selectRaw('COALESCE(SUM(total_price), 0) as aggregate')
            ->value('aggregate');
    }
}
