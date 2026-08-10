<?php

namespace App\Modules\Booking\Presentation\Web\Provider;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\Actions\RecordAuditEvent;
use App\Modules\Booking\Application\Services\BookingFlowService;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Provider\Application\Support\ProviderAccountScope;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use App\Modules\Staff\Infrastructure\Persistence\Models\StaffSchedule;
use App\Modules\Subscription\Application\Services\ProviderEntitlementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingFlowService $bookingFlow,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function index(Request $request)
    {
        $status = (string) $request->get('status', 'all');
        $search = trim((string) $request->get('search', ''));
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $allowedStatuses = [
            'all',
            'open',
            'pending',
            'pending_hold',
            'expired_hold',
            'payment_expired',
            'pending_payment',
            'confirmed',
            'waiting',
            'checked_in',
            'in_progress',
            'inprogress',
            'completed',
            'order_completed',
            'refund_completed',
            'provider_cancelled',
            'customer_cancelled',
            'rescheduled',
            'cancelled',
            'no_show',
        ];

        $paymentStatuses = [
            'all' => 'All Payments',
            'unpaid' => 'Unpaid',
            'pending' => 'Pending',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'expired' => 'Expired',
        ];

        $bookingTypes = [
            'all' => 'All Modes',
            'scheduled' => 'Scheduled',
            'queue' => 'Queue',
            'walk_in' => 'Walk In',
        ];

        $sortOptions = [
            'booking_date' => 'Appointment Date',
            'created_at' => 'Created Date',
            'amount' => 'Total Amount',
            'payment_status' => 'Payment Status',
            'status' => 'Booking Status',
            'booking_type' => 'Mode',
            'booking_code' => 'Booking Code',
        ];

        $status = in_array($status, $allowedStatuses, true) ? $status : 'all';

        $paymentStatus = (string) $request->get('payment_status', 'all');
        $paymentStatus = array_key_exists($paymentStatus, $paymentStatuses) ? $paymentStatus : 'all';

        $bookingType = (string) $request->get('booking_type', 'all');
        $bookingType = array_key_exists($bookingType, $bookingTypes) ? $bookingType : 'all';

        $legacyDate = $request->filled('date') ? (string) $request->get('date') : null;
        $dateFrom = $request->filled('date_from') ? (string) $request->get('date_from') : $legacyDate;
        $dateTo = $request->filled('date_to') ? (string) $request->get('date_to') : $legacyDate;

        if ($dateFrom && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = null;
        }

        if ($dateTo && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = null;
        }

        $sortBy = (string) $request->get('sort_by', 'booking_date');
        $sortBy = array_key_exists($sortBy, $sortOptions) ? $sortBy : 'booking_date';

        $sortDirection = strtolower((string) $request->get('sort_direction', 'desc'));
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc';

        $filters = [
            'status' => $status,
            'search' => $search,
            'per_page' => $perPage,
            'payment_status' => $paymentStatus,
            'booking_type' => $bookingType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $query = $this->applyBookingFilters($this->providerBookings(), $filters)
            ->with($this->bookingFlow->bookingRelations());

        $summaryQuery = $this->applyBookingFilters($this->providerBookings(), $filters);

        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'paid' => (clone $summaryQuery)
                ->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('status', 'paid'))
                ->count(),
            'pending' => (clone $summaryQuery)->whereIn('status', ['pending', 'pending_hold', 'pending_payment', 'confirmed', 'waiting', 'checked_in', 'in_progress', 'inprogress'])->count(),
            'completed' => (clone $summaryQuery)->whereIn('status', ['completed', 'order_completed'])->count(),
            'amount' => (float) (clone $summaryQuery)->sum('total_price'),
        ];

        $sortExpressions = [
            'booking_date' => 'booking_date',
            'created_at' => 'created_at',
            'amount' => 'total_price',
            'status' => 'status',
            'booking_type' => 'booking_type',
            'booking_code' => 'booking_code',
        ];

        if ($sortBy === 'payment_status') {
            $query->orderBy(
                Payment::query()
                    ->select('status')
                    ->whereColumn('payments.booking_id', 'bookings.id')
                    ->limit(1),
                $sortDirection
            );
        } else {
            $query->orderByRaw($sortExpressions[$sortBy] . ' ' . $sortDirection);
        }

        $query
            ->orderByRaw('COALESCE(start_time, "23:59:59") asc')
            ->orderBy('queue_number')
            ->orderByDesc('id');

        $bookings = $query->paginate($perPage)->withQueryString();

        $tabs = [
            'all' => 'All Bookings',
            'pending_hold' => 'Hold',
            'pending_payment' => 'Pending Payment',
            'confirmed' => 'Confirmed',
            'waiting' => 'Waiting',
            'checked_in' => 'Checked In',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'expired_hold' => 'Hold Expired',
            'payment_expired' => 'Payment Expired',
            'no_show' => 'No-show',
        ];

        $hasActiveFilters = $search !== ''
            || $status !== 'all'
            || $paymentStatus !== 'all'
            || $bookingType !== 'all'
            || ! empty($dateFrom)
            || ! empty($dateTo)
            || $perPage !== 10
            || $sortBy !== 'booking_date'
            || $sortDirection !== 'desc';

        $date = $dateFrom ?: now()->toDateString();
        $stats = [
            'total' => $summary['total'],
            'waiting' => (clone $summaryQuery)->where('status', 'waiting')->count(),
            'in_progress' => (clone $summaryQuery)->whereIn('status', ['in_progress', 'inprogress'])->count(),
            'completed' => $summary['completed'],
        ];

        return view('provider.pages.bookings.index', compact(
            'bookings',
            'tabs',
            'status',
            'search',
            'perPage',
            'filters',
            'paymentStatuses',
            'bookingTypes',
            'sortOptions',
            'sortBy',
            'sortDirection',
            'summary',
            'hasActiveFilters',
            'date',
            'stats'
        ));
    }

    public function calendar(Request $request)
    {
        try {
            $activeDate = Carbon::parse((string) $request->get('date', now()->toDateString()));
        } catch (\Throwable $exception) {
            $activeDate = now();
        }

        $requestedView = strtolower((string) $request->get('view', 'today'));
        $calendarView = match ($requestedView) {
            'day' => 'today',
            '7days', 'week' => 'week',
            default => $requestedView,
        };
        $calendarView = in_array($calendarView, ['today', 'week', 'month', 'year'], true)
            ? $calendarView
            : 'today';

        [$rangeStart, $rangeEnd] = match ($calendarView) {
            'today' => [$activeDate->copy()->startOfDay(), $activeDate->copy()->endOfDay()],
            'week' => [$activeDate->copy()->startOfDay(), $activeDate->copy()->addDays(6)->endOfDay()],
            'year' => [$activeDate->copy()->startOfYear(), $activeDate->copy()->endOfYear()],
            default => [$activeDate->copy()->startOfMonth(), $activeDate->copy()->endOfMonth()],
        };
        $date = $activeDate->toDateString();

        $calendarBookings = $this->providerBookings()
            ->with($this->bookingFlow->bookingRelations())
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $query
                    ->whereBetween('booking_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                    ->orWhereHas('participants', fn ($participantQuery) => $participantQuery
                        ->whereBetween('booking_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()]));
            })
            ->get();

        $calendarEntries = $calendarBookings
            ->flatMap(fn (Booking $booking) => $booking->operationalEntries())
            ->filter(fn (object $entry) => $entry->booking_date
                && $entry->booking_date->betweenIncluded($rangeStart, $rangeEnd))
            ->sortBy(fn (object $entry) => sprintf(
                '%s-%s-%010d-%010d',
                $entry->booking_date->toDateString(),
                substr((string) $entry->start_time, 0, 8) ?: '23:59:59',
                (int) $entry->booking->id,
                (int) $entry->position
            ))
            ->values();

        $calendarEntriesByDate = $calendarEntries
            ->groupBy(fn (object $entry) => $entry->booking_date->toDateString());

        $calendarStaffQuery = ProviderStaff::query()
            ->where('provider_id', $this->providerId())
            ->where('status', 'active')
            ->with('branch')
            ->orderBy('first_name')
            ->orderBy('last_name');
        ProviderAccountScope::applyBranchScope($calendarStaffQuery, $this->branchId());

        $calendarStaffs = $calendarStaffQuery
            ->get()
            ->merge($calendarEntries->pluck('staff')->filter())
            ->unique('id')
            ->values();

        return view('provider.pages.calendar.index', compact(
            'calendarEntries',
            'calendarEntriesByDate',
            'calendarStaffs',
            'calendarView',
            'rangeStart',
            'rangeEnd',
            'date'
        ));
    }

    public function queue(Request $request)
    {
        $date = $request->get('date', now()->toDateString());

        $queueBookings = $this->providerBookings()
            ->with($this->bookingFlow->bookingRelations())
            ->whereDate('booking_date', $date)
            ->whereIn('booking_type', ['queue', 'walk_in'])
            ->whereIn('status', ['waiting', 'checked_in', 'in_progress', 'inprogress'])
            ->orderBy('queue_number')
            ->get();

        return view('provider.pages.queue.index', compact('queueBookings', 'date'));
    }

    public function walkIn(Request $request)
    {
        return view('provider.pages.walk-in.index', $this->formData());
    }

    public function walkInAvailability(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => [
                'required',
                Rule::exists('provider_branches', 'id')->where(function ($query) {
                    $query->where('provider_id', $this->providerId())->where('status', 'active');
                    ProviderAccountScope::applyBranchScope($query, $this->branchId(), 'id');
                }),
            ],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where(fn ($query) => $query
                    ->where('provider_id', $this->providerId())
                    ->where('status', 'active')),
            ],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'staff_id' => [
                'nullable',
                'integer',
                Rule::exists('provider_staffs', 'id')->where(function ($query) {
                    $query->where('provider_id', $this->providerId())->where('status', 'active');
                    ProviderAccountScope::applyBranchScope($query, $this->branchId());
                }),
            ],
        ]);

        $branchQuery = ProviderBranch::query()
            ->where('provider_id', $this->providerId())
            ->where('status', 'active');
        ProviderAccountScope::applyBranchModelScope($branchQuery, $this->branchId());
        $branch = $branchQuery->findOrFail($validated['branch_id']);

        $services = $this->bookingFlow->servicesForBooking(
            $branch,
            $this->bookingFlow->normalizedServiceIds($validated),
            'scheduled'
        );

        return response()->json([
            'success' => true,
            'data' => $this->bookingFlow->availabilityPayload(
                $branch,
                $services,
                $validated['booking_date'],
                filled($validated['staff_id'] ?? null) ? (int) $validated['staff_id'] : null,
                'scheduled'
            ),
        ]);
    }

    public function storeWalkIn(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'branch_id' => [
                'required',
                Rule::exists('provider_branches', 'id')->where(function ($query) {
                    $query->where('provider_id', $this->providerId());
                    ProviderAccountScope::applyBranchScope($query, $this->branchId(), 'id');
                }),
            ],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'integer', Rule::exists('services', 'id')->where(fn ($query) => $query->where('provider_id', $this->providerId()))],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'staff_id' => ['required', 'integer', Rule::exists('provider_staffs', 'id')->where(function ($query) {
                $query->where('provider_id', $this->providerId());
                ProviderAccountScope::applyBranchScope($query, $this->branchId());
            })],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_type' => ['nullable', Rule::in(['dp', 'full_payment', 'pay_at_salon'])],
        ]);

        $entitlement = null;
        $booking = null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, &$entitlement, &$booking) {
            $user = Auth::user();
            $providerOwnerId = \App\Modules\Provider\Application\Support\ProviderMenuAccess::providerOwnerId($user);

            \App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile::where('user_id', $providerOwnerId)->lockForUpdate()->first();

            $entitlement = app(ProviderEntitlementService::class)->checkResourceLimit($user, 'manual_bookings');
            if (!$entitlement['allowed']) {
                return;
            }

            $booking = $this->bookingFlow->createBooking(array_merge($validated, [
                'booking_type' => 'walk_in',
                'payment_type' => $validated['payment_type'] ?? 'pay_at_salon',
            ]), null, true);
        });

        if ($entitlement && !$entitlement['allowed']) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $entitlement['reason']
                ], 403);
            }
            return redirect()->back()->with('error', $entitlement['reason']);
        }

        return provider_route_redirect('provider.calendar.index', ['date' => $booking->booking_date->toDateString()])
            ->with('success', 'Offline appointment berhasil dijadwalkan tanpa bentrok.');
    }

    public function skills()
    {
        $staffs = ProviderStaff::query()
            ->where('provider_id', $this->providerId())
            ->with(['branch', 'skills.serviceCategory'])
            ->orderBy('first_name');
        ProviderAccountScope::applyBranchScope($staffs, $this->branchId());

        $staffs = $staffs->get();

        $services = Service::query()
            ->where('provider_id', $this->providerId())
            ->where('status', 'active')
            ->with('serviceCategory')
            ->orderBy('title');
        ProviderAccountScope::applyServiceBranchScope($services, $this->branchId());

        $services = $services->get();

        return view('provider.pages.staff.skills', compact('staffs', 'services'));
    }

    public function updateSkills(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => ['required', Rule::exists('provider_staffs', 'id')->where(function ($query) {
                $query->where('provider_id', $this->providerId());
                ProviderAccountScope::applyBranchScope($query, $this->branchId());
            })],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['nullable', 'array'],
            'skills.*.*' => ['integer', Rule::exists('services', 'id')->where(fn ($query) => $query->where('provider_id', $this->providerId()))],
        ]);

        $staffQuery = ProviderStaff::query()
            ->where('provider_id', $this->providerId())
            ->with('branch');
        ProviderAccountScope::applyBranchScope($staffQuery, $this->branchId());

        $staff = $staffQuery->findOrFail($validated['staff_id']);

        $serviceIds = collect(data_get($validated, 'skills.' . $staff->id, []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validServiceQuery = Service::query()
            ->where('provider_id', $this->providerId())
            ->where('status', 'active')
            ->whereIn('id', $serviceIds);
        ProviderAccountScope::applyServiceBranchScope($validServiceQuery, $this->branchId());

        $validServiceIds = $validServiceQuery->get()
            ->filter(fn (Service $service) => $this->serviceIsAvailableForStaffBranch($service, $staff))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($validServiceIds->count() !== $serviceIds->count()) {
            return back()
                ->withErrors(['skills' => 'Some services are not available at this staff branch.'])
                ->withInput();
        }

        $staff->skills()->sync($validServiceIds->all());

        return provider_route_redirect('provider.staff.skills')
            ->with('success', 'Skills for ' . ($staff->full_name ?: $staff->email) . ' have been updated.')
            ->with('updated_staff_id', $staff->id);
    }

    public function schedules()
    {
        $staffs = ProviderStaff::query()
            ->where('provider_id', $this->providerId())
            ->with(['branch', 'schedules'])
            ->orderBy('first_name');
        ProviderAccountScope::applyBranchScope($staffs, $this->branchId());

        $staffs = $staffs->get();

        return view('provider.pages.staff.schedules', compact('staffs'));
    }

    public function updateSchedules(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => ['required', Rule::exists('provider_staffs', 'id')->where(function ($query) {
                $query->where('provider_id', $this->providerId());
                ProviderAccountScope::applyBranchScope($query, $this->branchId());
            })],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', 'string', 'max:20'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        DB::transaction(function () use ($validated) {
            StaffSchedule::where('staff_id', $validated['staff_id'])->delete();

            foreach ($validated['days'] as $day) {
                StaffSchedule::create([
                    'staff_id' => $validated['staff_id'],
                    'day_of_week' => $day,
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                    'is_available' => true,
                ]);
            }
        });

        return provider_route_redirect('provider.staff.schedules')
            ->with('success', 'Staff schedule has been updated.');
    }

    public function payments(Request $request)
    {
        $paymentStatuses = [
            'all' => 'All Payments',
            'unpaid' => 'Unpaid',
            'pending' => 'Pending',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'expired' => 'Expired',
        ];
        $paymentTypes = [
            'all' => 'All Types',
            'dp' => 'DP',
            'full_payment' => 'Full Payment',
            'pay_at_salon' => 'Pay at Salon',
        ];
        $sortOptions = [
            'created_at' => 'Created Date',
            'paid_at' => 'Paid Date',
            'amount' => 'Amount',
            'status' => 'Status',
            'payment_type' => 'Type',
        ];
        $perPageOptions = [10, 25, 50, 100];

        $filters = [
            'status' => array_key_exists((string) $request->get('status', 'all'), $paymentStatuses)
                ? (string) $request->get('status', 'all')
                : 'all',
            'payment_type' => array_key_exists((string) $request->get('payment_type', 'all'), $paymentTypes)
                ? (string) $request->get('payment_type', 'all')
                : 'all',
            'search' => trim((string) $request->get('search', '')),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'per_page' => in_array((int) $request->get('per_page', 25), $perPageOptions, true)
                ? (int) $request->get('per_page', 25)
                : 25,
            'sort_by' => array_key_exists((string) $request->get('sort_by', 'created_at'), $sortOptions)
                ? (string) $request->get('sort_by', 'created_at')
                : 'created_at',
            'sort_direction' => $request->get('sort_direction') === 'asc' ? 'asc' : 'desc',
        ];

        $baseQuery = $this->providerPaymentsQuery();
        $filteredQuery = $this->applyPaymentFilters(clone $baseQuery, $filters);

        $summary = [
            'total' => (clone $filteredQuery)->count(),
            'amount' => (float) (clone $filteredQuery)->sum('amount'),
            'paid' => (clone $filteredQuery)->where('status', 'paid')->count(),
            'pending' => (clone $filteredQuery)->whereIn('status', ['unpaid', 'pending'])->count(),
        ];

        $statusBreakdownQuery = $this->applyPaymentFilters(clone $baseQuery, $filters, ['status']);
        $statusBreakdown = collect($paymentStatuses)
            ->reject(fn ($label, $status) => $status === 'all')
            ->map(fn ($label, $status) => [
                'key' => $status,
                'label' => $label,
                'count' => (clone $statusBreakdownQuery)->where('status', $status)->count(),
                'amount' => (float) (clone $statusBreakdownQuery)->where('status', $status)->sum('amount'),
            ])
            ->values()
            ->all();

        $typeBreakdownQuery = $this->applyPaymentFilters(clone $baseQuery, $filters, ['payment_type']);
        $typeBreakdown = collect($paymentTypes)
            ->reject(fn ($label, $type) => $type === 'all')
            ->map(fn ($label, $type) => [
                'key' => $type,
                'label' => $label,
                'count' => (clone $typeBreakdownQuery)->where('payment_type', $type)->count(),
                'amount' => (float) (clone $typeBreakdownQuery)->where('payment_type', $type)->sum('amount'),
            ])
            ->values()
            ->all();

        $tabCounts = collect($paymentStatuses)
            ->mapWithKeys(fn ($label, $status) => [
                $status => $status === 'all'
                    ? (clone $statusBreakdownQuery)->count()
                    : (clone $statusBreakdownQuery)->where('status', $status)->count(),
            ])
            ->all();

        $payments = $filteredQuery
            ->with(['booking.provider', 'booking.customer', 'booking.branch', 'booking.services', 'booking.staff'])
            ->orderBy($filters['sort_by'], $filters['sort_direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        $hasActiveFilters = $filters['status'] !== 'all'
            || $filters['payment_type'] !== 'all'
            || $filters['search'] !== ''
            || ! empty($filters['date_from'])
            || ! empty($filters['date_to'])
            || $filters['per_page'] !== 25
            || $filters['sort_by'] !== 'created_at'
            || $filters['sort_direction'] !== 'desc';

        return view('provider.pages.payments.index', compact(
            'payments',
            'filters',
            'paymentStatuses',
            'paymentTypes',
            'sortOptions',
            'summary',
            'statusBreakdown',
            'typeBreakdown',
            'tabCounts',
            'hasActiveFilters'
        ));
    }

    public function call(Booking $booking)
    {
        $this->authorizeBooking($booking);
        $booking = $this->bookingFlow->updateStatus($booking, 'checked_in');

        return back()->with('success', 'Queue #' . $booking->queue_number . ' has been called.');
    }

    public function checkIn(Booking $booking)
    {
        $this->authorizeBooking($booking);
        $this->bookingFlow->updateStatus($booking, 'checked_in');

        return back()->with('success', 'Customer checked in successfully.');
    }

    public function start(Booking $booking)
    {
        $this->authorizeBooking($booking);
        $booking->load(['branch', 'services']);

        if (! $booking->staff_id && $booking->branch && $booking->services->isNotEmpty()) {
            $staff = $this->bookingFlow->chooseStaffForQueue($booking->branch, $booking->services, null, optional($booking->booking_date)->toDateString() ?: now()->toDateString());
            $booking->update(['staff_id' => $staff?->id]);
        }

        $this->bookingFlow->updateStatus($booking->refresh(), 'in_progress');

        return back()->with('success', 'Service has started.');
    }

    public function complete(Booking $booking)
    {
        $this->authorizeBooking($booking);
        $this->bookingFlow->completeBooking($booking);

        return back()->with('success', 'Service completed. Staff is available again.');
    }

    public function cancel(Booking $booking)
    {
        $this->authorizeBooking($booking);
        $before = ['status' => $booking->status];
        $booking = $this->bookingFlow->updateStatus($booking, 'cancelled');

        $this->recordAuditEvent->execute(
            action: 'provider.booking.cancelled',
            resourceType: Booking::class,
            resourceId: $booking->id,
            before: $before,
            after: ['status' => $booking->status],
            actor: Auth::user(),
            providerId: $booking->provider_id,
            branchId: $booking->branch_id,
        );

        return back()->with('success', 'Booking has been cancelled.');
    }

    public function noShow(Booking $booking)
    {
        $this->authorizeBooking($booking);
        $this->bookingFlow->updateStatus($booking, 'no_show');

        return back()->with('success', 'Booking marked as no-show.');
    }

    private function formData(): array
    {
        $branches = ProviderBranch::query()
            ->where('provider_id', $this->providerId())
            ->where('status', 'active')
            ->orderBy('branch_name');
        ProviderAccountScope::applyBranchModelScope($branches, $this->branchId());

        $branches = $branches->get();

        $services = Service::query()
            ->where('provider_id', $this->providerId())
            ->where('status', 'active')
            ->where('is_scheduled_enabled', true)
            ->with('serviceCategory')
            ->orderBy('title');
        ProviderAccountScope::applyServiceBranchScope($services, $this->branchId());

        $services = $services->get();

        $staffs = ProviderStaff::query()
            ->where('provider_id', $this->providerId())
            ->where('status', 'active')
            ->where('current_status', '!=', 'offline')
            ->orderBy('first_name');
        ProviderAccountScope::applyBranchScope($staffs, $this->branchId());

        $staffs = $staffs->get();

        return compact('branches', 'services', 'staffs');
    }

    private function providerBookings()
    {
        $query = Booking::query()
            ->where('provider_id', $this->providerId())
            ->whereNotIn('status', [
                BookingFlowService::STATUS_PENDING_HOLD,
                BookingFlowService::STATUS_EXPIRED_HOLD,
            ])
            ->where(function ($query) {
                $query
                    ->where('status', '!=', 'pending')
                    ->orWhereNull('hold_expires_at');
            });
        ProviderAccountScope::applyBranchScope($query, $this->branchId());

        return $query;
    }

    private function applyBookingFilters($query, array $filters)
    {
        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (($filters['payment_status'] ?? 'all') !== 'all') {
            $paymentStatus = $filters['payment_status'];

            $query->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('status', $paymentStatus));
        }

        if (($filters['booking_type'] ?? 'all') !== 'all') {
            $query->where('booking_type', $filters['booking_type']);
        }

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($dateFrom || $dateTo) {
            $query->where(function ($dateQuery) use ($dateFrom, $dateTo) {
                $dateQuery
                    ->where(function ($bookingDateQuery) use ($dateFrom, $dateTo) {
                        $bookingDateQuery
                            ->when($dateFrom, fn ($rangeQuery) => $rangeQuery->whereDate('booking_date', '>=', $dateFrom))
                            ->when($dateTo, fn ($rangeQuery) => $rangeQuery->whereDate('booking_date', '<=', $dateTo));
                    })
                    ->orWhereHas('participants', function ($participantQuery) use ($dateFrom, $dateTo) {
                        $participantQuery
                            ->when($dateFrom, fn ($rangeQuery) => $rangeQuery->whereDate('booking_date', '>=', $dateFrom))
                            ->when($dateTo, fn ($rangeQuery) => $rangeQuery->whereDate('booking_date', '<=', $dateTo));
                    });
            });
        }

        if (($filters['search'] ?? '') !== '') {
            $search = '%' . $filters['search'] . '%';

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('booking_code', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('customer_phone', 'like', $search)
                    ->orWhere('booking_type', 'like', $search)
                    ->orWhere('status', 'like', $search)
                    ->orWhereHas('payment', fn ($paymentQuery) => $paymentQuery->where('status', 'like', $search))
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', $search)->orWhere('email', 'like', $search))
                    ->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('title', 'like', $search))
                    ->orWhereHas('participants', function ($participantQuery) use ($search) {
                        $participantQuery
                            ->where('name', 'like', $search)
                            ->orWhere('phone', 'like', $search)
                            ->orWhere('email', 'like', $search)
                            ->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('title', 'like', $search))
                            ->orWhereHas('staff', function ($staffQuery) use ($search) {
                                $staffQuery
                                    ->where('first_name', 'like', $search)
                                    ->orWhere('last_name', 'like', $search)
                                    ->orWhere('username', 'like', $search)
                                    ->orWhere('email', 'like', $search);
                            });
                    })
                    ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('branch_name', 'like', $search)->orWhere('city_id', 'like', $search))
                    ->orWhereHas('staff', function ($staffQuery) use ($search) {
                        $staffQuery
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('username', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
            });
        }

        return $query;
    }

    private function providerPaymentsQuery()
    {
        return Payment::query()
            ->whereHas('booking', function ($query) {
                $query->where('provider_id', $this->providerId());
                ProviderAccountScope::applyBranchScope($query, $this->branchId());
            });
    }

    private function applyPaymentFilters($query, array $filters, array $except = [])
    {
        if (! in_array('status', $except, true) && ($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! in_array('payment_type', $except, true) && ($filters['payment_type'] ?? 'all') !== 'all') {
            $query->where('payment_type', $filters['payment_type']);
        }

        if (! in_array('date_from', $except, true) && ! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! in_array('date_to', $except, true) && ! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! in_array('search', $except, true) && ($filters['search'] ?? '') !== '') {
            $search = '%' . $filters['search'] . '%';

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('payment_method', 'like', $search)
                    ->orWhereHas('gatewayTransaction', function ($gatewayQuery) use ($search) {
                        $gatewayQuery
                            ->where('payment_channel', 'like', $search)
                            ->orWhere('payment_code', 'like', $search)
                            ->orWhere('payment_code_label', 'like', $search)
                            ->orWhere('provider_order_id', 'like', $search)
                            ->orWhere('provider_transaction_id', 'like', $search);
                    })
                    ->orWhereHas('booking', function ($bookingQuery) use ($search) {
                        $bookingQuery
                            ->where('booking_code', 'like', $search)
                            ->orWhere('customer_name', 'like', $search)
                            ->orWhere('customer_phone', 'like', $search)
                            ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', $search)->orWhere('email', 'like', $search))
                            ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('branch_name', 'like', $search))
                            ->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('title', 'like', $search));
                    });
            });
        }

        return $query;
    }

    private function authorizeBooking(Booking $booking): void
    {
        abort_unless((int) $booking->provider_id === $this->providerId(), 403);
        abort_if($this->branchId() !== null && (int) $booking->branch_id !== $this->branchId(), 403);
    }

    private function serviceIsAvailableForStaffBranch(Service $service, ProviderStaff $staff): bool
    {
        if (! $staff->branch_id) {
            return true;
        }

        $branchIds = $service->branch_ids;

        if (empty($branchIds)) {
            return true;
        }

        return in_array((int) $staff->branch_id, array_map('intval', (array) $branchIds), true);
    }

    private function providerId(): int
    {
        return ProviderAccountScope::providerId(Auth::user());
    }

    private function branchId(): ?int
    {
        return ProviderAccountScope::branchId(Auth::user());
    }
}
