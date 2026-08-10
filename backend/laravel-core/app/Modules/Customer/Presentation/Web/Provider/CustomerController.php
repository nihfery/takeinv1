<?php

namespace App\Modules\Customer\Presentation\Web\Provider;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Review\Infrastructure\Persistence\Models\BranchReview;
use App\Modules\Review\Infrastructure\Persistence\Models\StaffReview;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    private const EXCLUDED_BOOKING_STATUSES = [
        'pending_hold',
        'expired_hold',
        'payment_expired',
        'cancelled',
        'canceled',
        'provider_cancelled',
        'customer_cancelled',
    ];

    public function index(Request $request)
    {
        $providerId = ProviderAccountScope::providerId();
        $branchId = ProviderAccountScope::branchId();
        $search = trim((string) $request->query('search', ''));

        $customers = User::query()
            ->with('customerProfile')
            ->whereHas(
                'customerBookings',
                fn (Builder $query) => $this->scopeBookings($query, $providerId, $branchId)
            )
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('customerProfile', function (Builder $profileQuery) use ($search) {
                            $profileQuery
                                ->where('customer_id', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%");
                        });
                });
            })
            ->withCount([
                'customerBookings as provider_bookings_count' => fn (Builder $query) => $this->scopeBookings(
                    $query,
                    $providerId,
                    $branchId
                ),
            ])
            ->withSum([
                'customerBookings as provider_total_spent' => fn (Builder $query) => $this->scopeBookings(
                    $query,
                    $providerId,
                    $branchId
                )->whereNotIn('status', self::EXCLUDED_BOOKING_STATUSES),
            ], 'total_price')
            ->withMax([
                'customerBookings as provider_last_booking_date' => fn (Builder $query) => $this->scopeBookings(
                    $query,
                    $providerId,
                    $branchId
                ),
            ], 'booking_date')
            ->orderByDesc('provider_last_booking_date')
            ->paginate(20)
            ->withQueryString();

        $bookingQuery = $this->bookings($providerId, $branchId)->whereNotNull('customer_id');
        $returningCustomerQuery = (clone $bookingQuery)
            ->select('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1');

        $summary = [
            'total_customers' => (clone $bookingQuery)->distinct('customer_id')->count('customer_id'),
            'returning_customers' => DB::query()->fromSub($returningCustomerQuery, 'returning_customers')->count(),
            'new_this_month' => (clone $bookingQuery)
                ->whereBetween('booking_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->distinct('customer_id')
                ->count('customer_id'),
            'total_bookings' => (clone $bookingQuery)->count(),
        ];

        return view('provider.pages.customers.index', compact('customers', 'summary', 'search'));
    }

    public function reviews(Request $request)
    {
        $providerId = ProviderAccountScope::providerId();
        $branchId = ProviderAccountScope::branchId();
        $rating = $request->integer('rating');
        $rating = in_array($rating, [1, 2, 3, 4, 5], true) ? $rating : null;

        $branchReviewQuery = BranchReview::query()
            ->whereHas('booking', fn (Builder $query) => $this->scopeBookings($query, $providerId, $branchId));

        $staffReviewQuery = StaffReview::query()
            ->whereHas('booking', fn (Builder $query) => $this->scopeBookings($query, $providerId, $branchId));

        $summary = [
            'total_reviews' => (clone $branchReviewQuery)->count() + (clone $staffReviewQuery)->count(),
            'branch_average' => round((float) ((clone $branchReviewQuery)->avg('rating') ?: 0), 1),
            'staff_average' => round((float) ((clone $staffReviewQuery)->avg('rating') ?: 0), 1),
            'five_star' => (clone $branchReviewQuery)->where('rating', 5)->count()
                + (clone $staffReviewQuery)->where('rating', 5)->count(),
        ];

        $branchReviews = (clone $branchReviewQuery)
            ->with(['booking.customer.customerProfile', 'booking.branch'])
            ->when($rating, fn (Builder $query) => $query->where('rating', $rating))
            ->latest()
            ->paginate(10, ['*'], 'branch_page')
            ->withQueryString();

        $staffReviews = (clone $staffReviewQuery)
            ->with(['booking.customer.customerProfile', 'booking.branch', 'staff'])
            ->when($rating, fn (Builder $query) => $query->where('rating', $rating))
            ->latest()
            ->paginate(10, ['*'], 'staff_page')
            ->withQueryString();

        return view('provider.pages.customers.reviews', compact(
            'branchReviews',
            'staffReviews',
            'summary',
            'rating'
        ));
    }

    private function bookings(int $providerId, ?int $branchId): Builder
    {
        return $this->scopeBookings(Booking::query(), $providerId, $branchId);
    }

    private function scopeBookings(Builder $query, int $providerId, ?int $branchId): Builder
    {
        $query->where('provider_id', $providerId);
        ProviderAccountScope::applyBranchScope($query, $branchId);

        return $query;
    }
}
