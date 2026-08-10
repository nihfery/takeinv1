<?php

namespace App\Modules\Provider\Presentation\Web\Provider;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Provider\Application\Queries\ResolveDashboardPeriod;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const ACTIVE_STATUSES = ['open', 'pending', 'pending_hold', 'pending_payment', 'confirmed', 'waiting', 'checked_in', 'inprogress', 'in_progress', 'rescheduled'];
    private const COMPLETED_STATUSES = ['completed', 'order_completed'];
    private const CANCELED_STATUSES = ['cancelled', 'canceled', 'expired_hold', 'payment_expired', 'provider_cancelled', 'customer_cancelled', 'no_show'];

    private const SERVICE_COLORS = ['#111827', '#374151', '#6b7280', '#9ca3af', '#d1d5db', '#e5e7eb'];
    private const PAYMENT_COLORS = [
        'paid' => '#111827',
        'unpaid' => '#4b5563',
        'refunded' => '#9ca3af',
        'cancelled' => '#d1d5db',
    ];

    public function __construct(private readonly ResolveDashboardPeriod $resolveDashboardPeriod)
    {
    }

    public function index(Request $request)
    {
        $provider = Auth::user();

        if (! $provider || $provider->role !== 'provider') {
            abort(403, 'Access denied.');
        }

        $providerId = ProviderAccountScope::providerId($provider);
        $branchId = ProviderAccountScope::branchId($provider);
        $period = $this->resolveDashboardPeriod->handle($request->query('period', '6m'));
        $selectedPeriod = $period->selected;
        $startDate = $period->start;
        $endDate = $period->end;
        $previousStart = $period->previousStart;
        $previousEnd = $period->previousEnd;
        $periodOptions = $period->options;
        $periodLabel = $period->label;
        $dashboardData = Cache::remember(
            'provider_dashboard:data:' . implode(':', [$providerId, $branchId ?: 'all', $selectedPeriod, now()->format('Ymd')]),
            30,
            function () use ($providerId, $branchId, $selectedPeriod, $startDate, $endDate, $previousStart, $previousEnd) {
                $trendBuckets = $this->trendBuckets($providerId, $branchId, $selectedPeriod, $startDate, $endDate);

                return [
                    'stats' => $this->providerStats($providerId, $branchId),
                    'summaryCards' => $this->summaryCards($providerId, $branchId, $startDate, $endDate, $previousStart, $previousEnd),
                    'revenueChart' => $this->revenueTrendChart($trendBuckets),
                    'bookingSummary' => $this->bookingSummaryChart($trendBuckets),
                    'bestSellingServices' => $this->bestSellingServicesChart($providerId, $branchId, $startDate, $endDate),
                    'paymentStatus' => $this->paymentStatusChart($providerId, $branchId, $startDate, $endDate),
                    'topStaffPerformance' => $this->topStaffPerformanceChart($providerId, $branchId, $startDate, $endDate),
                    'operations' => $this->operationalOverview($providerId, $branchId),
                ];
            }
        );

        // We also need eligibility reasons to display
        $eligibilityService = new \App\Modules\Provider\Application\Services\SalonEligibilityService();
        $branches = ProviderBranch::where('provider_id', $providerId)->get();
        $branchEligibility = [];
        foreach ($branches as $b) {
            $branchEligibility[$b->id] = $eligibilityService->checkBranchEligibility($b);
        }
        
        $setupChecklist = $eligibilityService->getSetupChecklist($provider);

        return view('provider.pages.dashboard.index', array_merge(compact(
            'provider',
            'periodOptions',
            'selectedPeriod',
            'periodLabel',
            'branchEligibility',
            'setupChecklist'
        ), $dashboardData));
    }

    private function providerStats(int $providerId, ?int $branchId = null): array
    {
        $bookingQuery = Booking::query()->where('provider_id', $providerId);
        ProviderAccountScope::applyBranchScope($bookingQuery, $branchId);

        $branchQuery = ProviderBranch::query()->where('provider_id', $providerId);
        ProviderAccountScope::applyBranchModelScope($branchQuery, $branchId);

        $serviceQuery = Service::query()->where('provider_id', $providerId);
        ProviderAccountScope::applyServiceBranchScope($serviceQuery, $branchId);

        $staffQuery = ProviderStaff::query()->where('provider_id', $providerId);
        ProviderAccountScope::applyBranchScope($staffQuery, $branchId);

        return [
            'total_bookings' => (clone $bookingQuery)->count(),
            'today_bookings' => (clone $bookingQuery)->whereDate('booking_date', now()->toDateString())->count(),
            'customers_count' => (clone $bookingQuery)->whereNotNull('customer_id')->distinct('customer_id')->count('customer_id'),
            'branches_count' => $branchQuery->count(),
            'services_count' => $serviceQuery->count(),
            'staff_count' => $staffQuery->count(),
        ];
    }

    private function operationalOverview(int $providerId, ?int $branchId): array
    {
        $baseQuery = Booking::query()
            ->with(['customer', 'branch', 'staff', 'services'])
            ->where('provider_id', $providerId);
        ProviderAccountScope::applyBranchScope($baseQuery, $branchId);

        $today = now()->toDateString();
        $currentTime = now()->format('H:i:s');

        $upcomingQuery = (clone $baseQuery)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where(function ($query) use ($today, $currentTime) {
                $query->whereDate('booking_date', '>', $today)
                    ->orWhere(function ($todayQuery) use ($today, $currentTime) {
                        $todayQuery->whereDate('booking_date', $today)
                            ->whereTime('start_time', '>=', $currentTime);
                    });
            })
            ->orderBy('booking_date')
            ->orderBy('start_time');

        $upcoming = (clone $upcomingQuery)
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => $this->bookingOverviewItem($booking))
            ->values()
            ->all();

        $nextToday = (clone $upcomingQuery)
            ->whereDate('booking_date', $today)
            ->first();

        $activity = (clone $baseQuery)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => $this->bookingOverviewItem($booking))
            ->values()
            ->all();

        return [
            'upcoming' => $upcoming,
            'next_today' => $nextToday ? $this->bookingOverviewItem($nextToday) : null,
            'activity' => $activity,
        ];
    }

    private function bookingOverviewItem(Booking $booking): array
    {
        $serviceNames = $booking->services
            ->pluck('title')
            ->filter()
            ->values();
        $staffName = trim((string) ($booking->staff?->full_name ?? ''));
        $startTime = substr((string) $booking->start_time, 0, 5);

        return [
            'id' => $booking->id,
            'code' => $booking->booking_code ?: ('#' . $booking->id),
            'customer' => $booking->customer_name ?: $booking->customer?->name ?: 'Walk-in customer',
            'service' => $serviceNames->isNotEmpty() ? $serviceNames->implode(', ') : 'Appointment',
            'branch' => $booking->branch?->branch_name ?: 'Main location',
            'staff' => $staffName !== '' ? $staffName : 'Unassigned',
            'date_label' => $booking->booking_date?->format('D, d M Y') ?? '-',
            'date_short' => $booking->booking_date?->format('d M') ?? '-',
            'time_label' => $startTime !== '' ? $startTime : '-',
            'duration_label' => max(0, (int) $booking->total_duration) . ' min',
            'price_label' => $this->rupiah((float) $booking->total_price),
            'status' => (string) $booking->status,
            'status_label' => str((string) $booking->status)->replace('_', ' ')->title()->toString(),
            'status_tone' => match (true) {
                in_array($booking->status, self::COMPLETED_STATUSES, true) => 'completed',
                in_array($booking->status, self::CANCELED_STATUSES, true) => 'cancelled',
                default => 'active',
            },
        ];
    }

    private function summaryCards(int $providerId, ?int $branchId, Carbon $startDate, Carbon $endDate, Carbon $previousStart, Carbon $previousEnd): array
    {
        $totalRevenue = $this->paidRevenue($providerId, $branchId, $startDate, $endDate);
        $previousRevenue = $this->paidRevenue($providerId, $branchId, $previousStart, $previousEnd);
        $totalBookings = $this->bookingCount($providerId, $branchId, $startDate, $endDate);
        $previousBookings = $this->bookingCount($providerId, $branchId, $previousStart, $previousEnd);
        $completedBookings = $this->bookingCount($providerId, $branchId, $startDate, $endDate, self::COMPLETED_STATUSES);
        $previousCompletedBookings = $this->bookingCount($providerId, $branchId, $previousStart, $previousEnd, self::COMPLETED_STATUSES);
        $pendingPayment = $this->pendingPaymentAmount($providerId, $branchId, $startDate, $endDate);
        $previousPendingPayment = $this->pendingPaymentAmount($providerId, $branchId, $previousStart, $previousEnd);

        return [
            [
                'icon' => 'revenue',
                'title' => 'Total Revenue',
                'value' => $this->rupiah($totalRevenue),
                'raw_value' => $totalRevenue,
                'change' => $this->changeMeta($totalRevenue, $previousRevenue),
            ],
            [
                'icon' => 'booking',
                'title' => 'Total Bookings',
                'value' => number_format($totalBookings),
                'raw_value' => $totalBookings,
                'change' => $this->changeMeta($totalBookings, $previousBookings),
            ],
            [
                'icon' => 'completed',
                'title' => 'Completed Bookings',
                'value' => number_format($completedBookings),
                'raw_value' => $completedBookings,
                'change' => $this->changeMeta($completedBookings, $previousCompletedBookings),
            ],
            [
                'icon' => 'pending',
                'title' => 'Pending Payment',
                'value' => $this->rupiah($pendingPayment),
                'raw_value' => $pendingPayment,
                'change' => $this->changeMeta($pendingPayment, $previousPendingPayment),
            ],
        ];
    }

    private function trendBuckets(int $providerId, ?int $branchId, string $period, Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->bucketRanges($period, $startDate, $endDate)->map(function (array $bucket) use ($providerId, $branchId) {
            $bookingQuery = Booking::query()
                ->where('provider_id', $providerId)
                ->whereBetween('booking_date', [$bucket['start']->toDateString(), $bucket['end']->toDateString()]);
            ProviderAccountScope::applyBranchScope($bookingQuery, $branchId);

            $completedBookings = (clone $bookingQuery)->whereIn('status', self::COMPLETED_STATUSES)->count();
            $pendingBookings = (clone $bookingQuery)->whereIn('status', self::ACTIVE_STATUSES)->count();
            $cancelledBookings = (clone $bookingQuery)->whereIn('status', self::CANCELED_STATUSES)->count();
            $bookedRevenue = (float) (clone $bookingQuery)->sum('total_price');

            return [
                'label' => $bucket['label'],
                'start' => $bucket['start'],
                'end' => $bucket['end'],
                'paid_revenue' => $this->paidRevenue($providerId, $branchId, $bucket['start'], $bucket['end']),
                'booked_revenue' => $bookedRevenue,
                'pending_payment' => $this->pendingPaymentAmount($providerId, $branchId, $bucket['start'], $bucket['end']),
                'completed_booking' => $completedBookings,
                'pending_booking' => $pendingBookings,
                'cancelled_booking' => $cancelledBookings,
            ];
        });
    }

    private function bucketRanges(string $period, Carbon $startDate, Carbon $endDate): Collection
    {
        $buckets = collect();

        if ($period === '7d') {
            $cursor = $startDate->copy();

            while ($cursor->lte($endDate)) {
                $buckets->push([
                    'label' => $cursor->format('d M'),
                    'start' => $cursor->copy()->startOfDay(),
                    'end' => $cursor->copy()->endOfDay(),
                ]);

                $cursor->addDay();
            }

            return $buckets;
        }

        if ($period === '30d') {
            $cursor = $startDate->copy();

            while ($cursor->lte($endDate)) {
                $bucketEnd = $cursor->copy()->addDays(4)->endOfDay();

                if ($bucketEnd->gt($endDate)) {
                    $bucketEnd = $endDate->copy();
                }

                $buckets->push([
                    'label' => $cursor->format('d M'),
                    'start' => $cursor->copy()->startOfDay(),
                    'end' => $bucketEnd,
                ]);

                $cursor = $bucketEnd->copy()->addDay()->startOfDay();
            }

            return $buckets;
        }

        $cursor = $startDate->copy()->startOfMonth();

        while ($cursor->lte($endDate)) {
            $bucketEnd = $cursor->copy()->endOfMonth();

            if ($bucketEnd->gt($endDate)) {
                $bucketEnd = $endDate->copy();
            }

            $buckets->push([
                'label' => $cursor->format('M Y'),
                'start' => $cursor->copy()->startOfMonth(),
                'end' => $bucketEnd,
            ]);

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return $buckets;
    }

    private function revenueTrendChart(Collection $buckets): array
    {
        $showPending = $buckets->sum('pending_payment') > 0;
        $series = [
            [
                'key' => 'paid_revenue',
                'label' => 'Paid Revenue',
                'color' => '#111827',
                'visible' => true,
            ],
            [
                'key' => 'booked_revenue',
                'label' => 'Booked Revenue',
                'color' => '#6b7280',
                'visible' => true,
            ],
            [
                'key' => 'pending_payment',
                'label' => 'Pending Payment',
                'color' => '#9ca3af',
                'visible' => $showPending,
            ],
        ];

        $visibleKeys = collect($series)->where('visible', true)->pluck('key')->all();
        $values = $buckets
            ->flatMap(fn (array $bucket) => collect($visibleKeys)->map(fn (string $key) => (float) $bucket[$key]))
            ->all();
        $maxValue = max([0, ...$values]);
        $chartWidth = 760;
        $chartHeight = 260;
        $top = 30;
        $bottom = 218;
        $count = max(1, $buckets->count());
        $sidePadding = 14;

        $series = collect($series)->map(function (array $item) use ($buckets, $chartWidth, $top, $bottom, $count, $maxValue, $sidePadding) {
            if (! $item['visible']) {
                $item['path'] = '';
                $item['points'] = [];

                return $item;
            }

            $points = $buckets->values()->map(function (array $bucket, int $index) use ($item, $chartWidth, $top, $bottom, $count, $maxValue, $sidePadding) {
                $usableWidth = $chartWidth - ($sidePadding * 2);
                $x = $count === 1 ? $chartWidth / 2 : $sidePadding + (($index / ($count - 1)) * $usableWidth);
                $value = (float) $bucket[$item['key']];
                $ratio = $maxValue > 0 ? $value / $maxValue : 0;
                $y = $bottom - ($ratio * ($bottom - $top));

                return [
                    'x' => round($x, 2),
                    'y' => round($y, 2),
                    'value' => $value,
                ];
            })->all();

            $item['path'] = $this->linePath($points);
            $item['points'] = $points;

            return $item;
        })->all();

        return [
            'has_data' => $maxValue > 0,
            'show_pending' => $showPending,
            'series' => $series,
            'buckets' => $buckets->values()->all(),
            'max_value' => $maxValue,
            'max_label' => $this->shortRupiah($maxValue),
            'mid_label' => $this->shortRupiah($maxValue / 2),
            'width' => $chartWidth,
            'height' => $chartHeight,
        ];
    }

    private function bookingSummaryChart(Collection $buckets): array
    {
        $values = $buckets->flatMap(fn (array $bucket) => [
            (int) $bucket['completed_booking'],
            (int) $bucket['pending_booking'],
            (int) $bucket['cancelled_booking'],
        ])->all();
        $maxValue = max([0, ...$values]);

        return [
            'has_data' => $maxValue > 0,
            'max_value' => $maxValue,
            'buckets' => $buckets->map(function (array $bucket) use ($maxValue) {
                $bucket['bars'] = [
                    'completed' => [
                        'label' => 'Completed Bookings',
                        'value' => (int) $bucket['completed_booking'],
                        'height' => $this->barHeight((int) $bucket['completed_booking'], $maxValue),
                    ],
                    'pending' => [
                        'label' => 'Pending Bookings',
                        'value' => (int) $bucket['pending_booking'],
                        'height' => $this->barHeight((int) $bucket['pending_booking'], $maxValue),
                    ],
                    'cancelled' => [
                        'label' => 'Cancelled Bookings',
                        'value' => (int) $bucket['cancelled_booking'],
                        'height' => $this->barHeight((int) $bucket['cancelled_booking'], $maxValue),
                    ],
                ];

                return $bucket;
            })->values()->all(),
        ];
    }

    private function bestSellingServicesChart(int $providerId, ?int $branchId, Carbon $startDate, Carbon $endDate): array
    {
        $bookingQuery = Booking::with('services')
            ->where('provider_id', $providerId)
            ->whereBetween('booking_date', [$startDate->toDateString(), $endDate->toDateString()]);
        ProviderAccountScope::applyBranchScope($bookingQuery, $branchId);

        $bookings = $bookingQuery->get();

        $serviceMap = collect();

        foreach ($bookings as $booking) {
            $services = $booking->services->isNotEmpty()
                ? $booking->services
                : collect([$booking->service])->filter();

            foreach ($services as $service) {
                $serviceId = (int) $service->id;
                $current = $serviceMap->get($serviceId, [
                    'id' => $serviceId,
                    'name' => $service->title ?? $service->name ?? 'Service',
                    'total_booking' => 0,
                    'revenue' => 0,
                ]);

                $current['total_booking']++;
                $current['revenue'] += (float) ($service->pivot->price ?? $service->price ?? 0);
                $serviceMap->put($serviceId, $current);
            }
        }

        $items = $serviceMap
            ->sortByDesc('total_booking')
            ->take(6)
            ->values();

        return $this->donutChart($items, 'total_booking', self::SERVICE_COLORS);
    }

    private function paymentStatusChart(int $providerId, ?int $branchId, Carbon $startDate, Carbon $endDate): array
    {
        $payments = $this->providerPaymentQuery($providerId, $branchId)
            ->with('booking')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                    ->orWhereBetween('updated_at', [$startDate, $endDate])
                    ->orWhereBetween('paid_at', [$startDate, $endDate]);
            })
            ->get();

        $items = collect([
            [
                'key' => 'paid',
                'name' => 'Paid',
                'total_booking' => 0,
                'color' => self::PAYMENT_COLORS['paid'],
            ],
            [
                'key' => 'unpaid',
                'name' => 'Unpaid',
                'total_booking' => 0,
                'color' => self::PAYMENT_COLORS['unpaid'],
            ],
            [
                'key' => 'refunded',
                'name' => 'Refunded',
                'total_booking' => 0,
                'color' => self::PAYMENT_COLORS['refunded'],
            ],
            [
                'key' => 'cancelled',
                'name' => 'Cancelled',
                'total_booking' => 0,
                'color' => self::PAYMENT_COLORS['cancelled'],
            ],
        ])->keyBy('key');

        foreach ($payments as $payment) {
            $bookingCancelled = $payment->booking && in_array($payment->booking->status, self::CANCELED_STATUSES, true);
            $key = match (true) {
                $bookingCancelled || $payment->status === 'failed' => 'cancelled',
                $payment->status === 'paid' => 'paid',
                $payment->status === 'refunded' => 'refunded',
                default => 'unpaid',
            };

            $item = $items->get($key);
            $item['total_booking']++;
            $items->put($key, $item);
        }

        return $this->donutChart($items->values(), 'total_booking', $items->pluck('color')->all());
    }

    private function topStaffPerformanceChart(int $providerId, ?int $branchId, Carbon $startDate, Carbon $endDate): array
    {
        $staffQuery = ProviderStaff::withAvg('staffReviews', 'rating')->with(['bookings' => function ($query) use ($startDate, $endDate, $branchId) {
            $query
                ->with('payment')
                ->whereBetween('booking_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->whereNotIn('status', self::CANCELED_STATUSES);

            ProviderAccountScope::applyBranchScope($query, $branchId);
        }])
            ->where('provider_id', $providerId);
        ProviderAccountScope::applyBranchScope($staffQuery, $branchId);

        $staffMembers = $staffQuery->get();

        $items = $staffMembers
            ->map(function (ProviderStaff $staff) {
                $staffBookings = $staff->bookings;
                $paidRevenue = $staffBookings->sum(function ($booking) {
                    if ($booking->payment && $booking->payment->status === 'paid') {
                        return (float) $booking->payment->amount;
                    }

                    return 0;
                });
                $staffName = trim((string) $staff->full_name)
                    ?: $staff->username
                    ?: $staff->email
                    ?: 'Staff #' . $staff->id;

                return [
                    'name' => $staffName,
                    'total_booking' => $staffBookings->count(),
                    'rating' => (float) ($staff->staff_reviews_avg_rating ?? 0),
                    'revenue' => $paidRevenue,
                ];
            })
            ->filter(fn (array $item) => $item['total_booking'] > 0)
            ->sortByDesc('total_booking')
            ->take(5)
            ->values();

        $maxBooking = max([0, ...$items->pluck('total_booking')->all()]);

        return [
            'has_data' => $items->isNotEmpty(),
            'max_booking' => $maxBooking,
            'items' => $items->map(function (array $item) use ($maxBooking) {
                $item['width'] = $maxBooking > 0 ? max(8, round(($item['total_booking'] / $maxBooking) * 100)) : 0;
                $item['revenue_label'] = $this->rupiah($item['revenue']);
                $item['rating_label'] = $item['rating'] > 0 ? number_format($item['rating'], 1) : '-';

                return $item;
            })->all(),
        ];
    }

    private function providerPaymentQuery(int $providerId, ?int $branchId = null)
    {
        return Payment::query()->whereHas('booking', function ($query) use ($providerId, $branchId) {
            $query->where('provider_id', $providerId);
            ProviderAccountScope::applyBranchScope($query, $branchId);
        });
    }

    private function paidRevenue(int $providerId, ?int $branchId, Carbon $startDate, Carbon $endDate): float
    {
        return (float) $this->providerPaymentQuery($providerId, $branchId)
            ->where('status', 'paid')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('paid_at', [$startDate, $endDate])
                    ->orWhere(function ($fallbackQuery) use ($startDate, $endDate) {
                        $fallbackQuery->whereNull('paid_at')
                            ->whereBetween('updated_at', [$startDate, $endDate]);
                    });
            })
            ->sum('amount');
    }

    private function pendingPaymentAmount(int $providerId, ?int $branchId, Carbon $startDate, Carbon $endDate): float
    {
        return (float) $this->providerPaymentQuery($providerId, $branchId)
            ->whereIn('status', ['pending', 'unpaid'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
    }

    private function bookingCount(int $providerId, ?int $branchId, Carbon $startDate, Carbon $endDate, ?array $statuses = null): int
    {
        $query = Booking::query()
            ->where('provider_id', $providerId)
            ->whereBetween('booking_date', [$startDate->toDateString(), $endDate->toDateString()]);
        ProviderAccountScope::applyBranchScope($query, $branchId);

        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        return $query->count();
    }

    private function changeMeta(float|int $current, float|int $previous): array
    {
        if ((float) $previous === 0.0) {
            $percentage = (float) $current > 0 ? 100 : 0;
        } else {
            $percentage = (($current - $previous) / abs($previous)) * 100;
        }

        $direction = $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'flat');
        $prefix = $percentage > 0 ? '+' : '';

        return [
            'direction' => $direction,
            'label' => $prefix . number_format($percentage, 1) . '% vs previous',
        ];
    }

    private function donutChart(Collection $items, string $valueKey, array $colors): array
    {
        $total = (float) $items->sum($valueKey);
        $cursor = 0.0;

        $items = $items->values()->map(function (array $item, int $index) use ($total, $valueKey, $colors, &$cursor) {
            $value = (float) $item[$valueKey];
            $percentage = $total > 0 ? ($value / $total) * 100 : 0;
            $color = $item['color'] ?? $colors[$index % count($colors)];
            $start = $cursor;
            $cursor += $percentage;

            $item['color'] = $color;
            $item['percentage'] = $percentage;
            $item['percentage_label'] = number_format($percentage, 0) . '%';
            $item['segment'] = [$start, $cursor];

            return $item;
        });

        $gradient = $items->map(function (array $item) {
            [$start, $end] = $item['segment'];

            return "{$item['color']} {$start}% {$end}%";
        })->implode(', ');

        return [
            'has_data' => $total > 0,
            'total' => $total,
            'total_label' => number_format($total),
            'items' => $items->all(),
            'gradient' => $total > 0 ? "conic-gradient({$gradient})" : 'conic-gradient(#efe8e2 0 100%)',
        ];
    }

    private function linePath(array $points): string
    {
        if (empty($points)) {
            return '';
        }

        return collect($points)
            ->map(fn (array $point, int $index) => ($index === 0 ? 'M ' : 'L ') . $point['x'] . ' ' . $point['y'])
            ->implode(' ');
    }

    private function barHeight(int $value, int $maxValue): int
    {
        if ($value <= 0 || $maxValue <= 0) {
            return 2;
        }

        return max(10, (int) round(($value / $maxValue) * 100));
    }

    private function rupiah(float|int $value): string
    {
        return 'Rp' . number_format((float) $value);
    }

    private function shortRupiah(float|int $value): string
    {
        $value = (float) $value;

        if ($value >= 1_000_000_000) {
            return 'Rp' . number_format($value / 1_000_000_000, 2) . 'B';
        }

        if ($value >= 1_000_000) {
            return 'Rp' . number_format($value / 1_000_000, 2) . 'M';
        }

        if ($value >= 1_000) {
            return 'Rp' . number_format($value / 1_000) . 'K';
        }

        return $this->rupiah($value);
    }

}
