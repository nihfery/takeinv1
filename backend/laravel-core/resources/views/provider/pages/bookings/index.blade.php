@extends('provider.layouts.dashboard')

@section('title', 'Bookings - JasaKu')
@section('page_title', 'Bookings')
@section('page_subtitle', 'Manage check-in, service start, completion, cancellation, and no-show handling from one workspace.')

@section('content')
@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Auth;

    $filters = $filters ?? [
        'status' => request('status', $status ?? 'all'),
        'search' => request('search', $search ?? ''),
        'per_page' => request('per_page', $perPage ?? 10),
        'payment_status' => request('payment_status', 'all'),
        'booking_type' => request('booking_type', 'all'),
        'date_from' => request('date_from'),
        'date_to' => request('date_to'),
        'sort_by' => request('sort_by', 'booking_date'),
        'sort_direction' => request('sort_direction', 'desc'),
    ];

    $statusTabs = $tabs ?? [
        'all' => 'All Bookings',
        'pending_payment' => 'Pending Payment',
        'confirmed' => 'Confirmed',
        'waiting' => 'Waiting',
        'checked_in' => 'Checked In',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-show',
    ];

    $paymentStatuses = $paymentStatuses ?? [
        'all' => 'All Payments',
        'unpaid' => 'Unpaid',
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'expired' => 'Expired',
    ];

    $bookingTypes = $bookingTypes ?? [
        'all' => 'All Modes',
        'scheduled' => 'Scheduled',
        'queue' => 'Queue',
        'walk_in' => 'Walk In',
    ];

    $bookingCollection = $bookings ?? collect();
    $hasPaginator = is_object($bookingCollection)
        && method_exists($bookingCollection, 'links')
        && method_exists($bookingCollection, 'firstItem');
    $firstItem = $hasPaginator ? ($bookingCollection->firstItem() ?? 0) : 0;
    $lastItem = $hasPaginator ? ($bookingCollection->lastItem() ?? 0) : (is_countable($bookingCollection) ? count($bookingCollection) : 0);
    $totalItem = $hasPaginator ? $bookingCollection->total() : (is_countable($bookingCollection) ? count($bookingCollection) : 0);
    $bookingModels = $hasPaginator ? collect($bookingCollection->items()) : collect($bookingCollection);
    $bookingEntries = $bookingModels
        ->flatMap(fn ($booking) => $booking->operationalEntries())
        ->filter(function ($entry) use ($filters) {
            if (! $entry->booking_date) {
                return empty($filters['date_from']) && empty($filters['date_to']);
            }

            $entryDate = $entry->booking_date->toDateString();

            return (empty($filters['date_from']) || $entryDate >= $filters['date_from'])
                && (empty($filters['date_to']) || $entryDate <= $filters['date_to']);
        })
        ->values();

    $currentStatus = $filters['status'] ?? 'all';
    $sortBy = $sortBy ?? ($filters['sort_by'] ?? 'booking_date');
    $sortDirection = $sortDirection ?? ($filters['sort_direction'] ?? 'desc');

    $cleanQuery = function (array $query) {
        return collect($query)
            ->reject(function ($value, $key) {
                if ($value === null || $value === '') {
                    return true;
                }

                if (in_array($key, ['status', 'payment_status', 'booking_type'], true) && $value === 'all') {
                    return true;
                }

                if ($key === 'sort_by' && $value === 'booking_date') {
                    return true;
                }

                if ($key === 'sort_direction' && $value === 'desc') {
                    return true;
                }

                if ($key === 'per_page' && (int) $value === 10) {
                    return true;
                }

                return false;
            })
            ->all();
    };

    $queryFor = fn (array $overrides = []) => $cleanQuery(array_merge($filters, $overrides));
    $sortQueryFor = function (string $key) use ($queryFor, $sortBy, $sortDirection) {
        $nextDirection = $sortBy === $key && $sortDirection === 'asc' ? 'desc' : 'asc';

        return $queryFor([
            'sort_by' => $key,
            'sort_direction' => $nextDirection,
        ]);
    };
    $sortIconClass = fn (string $key, string $direction) => $sortBy === $key && $sortDirection === $direction
        ? 'active'
        : '';
    $sortAriaLabel = fn (string $key, string $label) => 'Sort ' . $label . ' '
        . ($sortBy === $key && $sortDirection === 'asc' ? 'descending' : 'ascending');

    $statusLabels = [
        'pending_payment' => 'Pending Pay',
        'order_completed' => 'Completed',
        'refund_completed' => 'Refunded',
        'checked_in' => 'Checked In',
        'in_progress' => 'In Progress',
        'inprogress' => 'In Progress',
        'provider_cancelled' => 'Provider Cancel',
        'customer_cancelled' => 'Customer Cancel',
        'no_show' => 'No Show',
        'walk_in' => 'Walk In',
        'pay_at_salon' => 'Pay at Salon',
        'full_payment' => 'Full Payment',
    ];

    $statusLabel = fn ($value) => $statusLabels[$value ?: 'pending'] ?? ucwords(str_replace('_', ' ', $value ?: 'pending'));

    $statusClass = function ($value) {
        return match ($value) {
            'completed', 'order_completed', 'refund_completed', 'paid' => 'success',
            'pending', 'pending_payment', 'waiting', 'confirmed', 'rescheduled', 'unpaid', 'dp' => 'warning',
            'checked_in', 'inprogress', 'in_progress', 'scheduled', 'queue', 'walk_in', 'pay_at_salon', 'full_payment' => 'info',
            'provider_cancelled', 'customer_cancelled', 'cancelled', 'no_show', 'rejected', 'failed', 'expired' => 'danger',
            default => 'neutral',
        };
    };

    $formatTime = function ($value) {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Throwable $exception) {
            return substr((string) $value, 0, 5) ?: null;
        }
    };

    $formatDate = function ($value) {
        if (empty($value)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable $exception) {
            return '-';
        }
    };

    $formatMoney = fn ($value) => 'Rp' . number_format((float) ($value ?? 0), 0, ',', '.');

    $bookingInitial = fn ($booking, $customerName) => strtoupper(substr((string) ($customerName ?: $booking->booking_code ?: 'B'), 0, 1));

    $summary = $summary ?? [
        'total' => $totalItem,
        'paid' => 0,
        'pending' => 0,
        'completed' => 0,
        'amount' => 0,
    ];

    $hasMobileAdvancedFilters = (($filters['payment_status'] ?? 'all') !== 'all')
        || (($filters['booking_type'] ?? 'all') !== 'all')
        || ! empty($filters['date_from'])
        || ! empty($filters['date_to'])
        || ((int) ($filters['per_page'] ?? 10) !== 10);
@endphp

<section class="admin-category-page admin-booking-page provider-booking-category-page">
    <div class="provider-order-page-header">
        <div class="provider-order-page-title-group">
            <h1 class="provider-order-page-title">Bookings</h1>
        </div>
        <div class="provider-order-page-actions">
            <span class="provider-order-page-date">{{ \Carbon\Carbon::now()->format('l, d F Y') }}</span>
            <a class="provider-order-btn-action secondary" href="{{ provider_route('provider.queue.index') }}">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h10"></path></svg>
                Queue
            </a>
            <a class="provider-order-btn-action primary" href="{{ provider_route('provider.walk-in.index') }}">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
                Add Walk-in
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="admin-booking-alert success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="admin-booking-alert danger">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="admin-booking-alert danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="provider-order-filter-bar">
        <div class="admin-booking-tabs provider-booking-tabs">
            @foreach (['all' => 'All', 'in_progress' => 'On Process', 'completed' => 'Completed'] as $key => $label)
                <a href="{{ provider_route('provider.bookings.index', $queryFor(['status' => $key])) }}"
                   class="admin-booking-tab {{ ($currentStatus === $key || ($key === 'all' && empty($currentStatus))) ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="provider-order-filter-actions">
            <div class="provider-order-view-toggle">
                <button type="button" id="btnGridView" class="active" data-booking-view="grid" title="Card view" aria-label="Show booking cards" aria-controls="gridViewContainer" aria-pressed="true">
                    <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </button>
                <button type="button" id="btnListView" data-booking-view="list" title="List view" aria-label="Show booking list" aria-controls="listViewContainer" aria-pressed="false">
                    <svg viewBox="0 0 24 24" fill="none"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                </button>
            </div>

            <button type="button" class="provider-order-btn-action secondary" id="btnOpenFilterModal">
                <svg viewBox="0 0 24 24" fill="none"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filter
            </button>

            <form method="GET" action="{{ provider_route('provider.bookings.index') }}" class="provider-order-search">
                @if (! empty($currentStatus) && $currentStatus !== 'all')
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or order code" aria-label="Search bookings" autocomplete="off">
            </form>
        </div>
    </div>

    <div id="gridViewContainer">
        <div class="provider-booking-card-grid {{ $bookingEntries->count() === 1 ? 'is-single' : '' }}">
            @forelse ($bookingEntries as $entry)
                @php
                    $booking = $entry->booking;
                    $bookingStatus = $booking->status ?? 'pending';
                    $customerName = $entry->customer_name;
                    $customerPhone = $entry->customer_phone;
                    $branchName = $booking->branch->branch_name ?? '-';
                    $staffName = $entry->staff?->full_name ?: 'Any Available';
                    $bookingType = $booking->booking_type ?? 'scheduled';
                    $paymentStatus = optional($booking->payment)->status ?? 'unpaid';
                    $paymentMethod = optional($booking->payment)->payment_method
                        ?? optional($booking->payment)->payment_channel
                        ?? optional($booking->payment)->payment_type
                        ?? null;
                    $amount = $entry->total_price;
                    $dateValue = $entry->booking_date ?? $booking->created_at ?? null;
                    $startTime = $formatTime($entry->start_time);
                    $endTime = $formatTime($entry->estimated_end_time);
                    $canCheckIn = in_array($bookingStatus, ['confirmed', 'waiting'], true);
                    $canStart = in_array($bookingStatus, ['confirmed', 'waiting', 'checked_in'], true);
                    $canComplete = in_array($bookingStatus, ['in_progress', 'inprogress'], true);
                    $canCancel = ! in_array($bookingStatus, ['completed', 'order_completed', 'refund_completed', 'cancelled', 'provider_cancelled', 'customer_cancelled', 'no_show'], true);
                    
                    $initial = $bookingInitial($booking, $customerName);
                    
                    $colors = ['#14b8a6', '#0f766e', '#eab308', '#f59e0b', '#3b82f6'];
                    $avatarColor = $colors[$booking->id % count($colors)];
                @endphp

                <article class="provider-order-card">
                    <header class="provider-order-card-header">
                        <div class="provider-order-card-customer">
                            <span class="provider-order-card-avatar" style="background-color: {{ $avatarColor }};">{{ $initial }}</span>
                            <div class="provider-order-card-customer-info">
                                <strong>{{ $customerName }}</strong>
                                <small>Order {{ $entry->display_code }} &middot; {{ $statusLabel($bookingType) }}</small>
                                @if ($entry->is_group)
                                    <small>{{ $entry->participant_label }} &middot; dipesan oleh {{ $booking->customer?->name ?: $booking->customer_name }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="provider-order-card-status">
                            <span class="admin-booking-status {{ $statusClass($bookingStatus) }}">{{ $statusLabel($bookingStatus) }}</span>
                        </div>
                    </header>

                    <div class="provider-order-card-meta">
                        <div>
                            <span>{{ $formatDate($dateValue) }}</span>
                        </div>
                        <div>
                            <span>@if ($startTime) {{ $startTime }}{{ $endTime ? ' - ' . $endTime : '' }} @else Time not set @endif</span>
                        </div>
                        <div>
                            <span>{{ $staffName }} &middot; {{ $branchName }}</span>
                        </div>
                    </div>

                    <div class="provider-order-card-items">
                        <div class="provider-order-card-items-header">
                            <span>Items</span>
                            <span>Qty</span>
                            <span>Price</span>
                        </div>
                        @php
                            $services = $entry->services ?? collect();
                        @endphp
                        
                        @if ($services->isNotEmpty())
                            @foreach ($services as $svc)
                                <div class="provider-order-card-item">
                                    <span>{{ $svc->title }}</span>
                                    <span>1</span>
                                    <span>{{ $formatMoney($svc->pivot?->price ?? $svc->price ?? 0) }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="provider-order-card-item">
                                <span>No service details</span>
                                <span></span>
                                <span></span>
                            </div>
                        @endif
                    </div>

                    <div class="provider-order-card-footer">
                        <div class="provider-order-card-total">
                            <div class="provider-order-card-total-copy">
                                <span>{{ $entry->is_group ? 'Nilai peserta' : 'Total' }}</span>
                                @if ($entry->is_group)
                                    <small>Total transaksi {{ $formatMoney($entry->booking_total_price) }}</small>
                                @endif
                            </div>
                            <strong>{{ $formatMoney($amount) }}</strong>
                        </div>
                        <div class="provider-order-card-actions">
                            @if ($canCheckIn)
                                <form method="POST" action="{{ provider_route('provider.bookings.check-in', $booking) }}">
                                    @csrf
                                    <button class="primary" type="submit" aria-label="Check-in">Check-in</button>
                                </form>
                            @endif

                            @if ($canStart)
                                <form method="POST" action="{{ provider_route('provider.bookings.start', $booking) }}">
                                    @csrf
                                    <button class="primary" type="submit" aria-label="Start process">Start</button>
                                </form>
                            @endif

                            @if ($canComplete)
                                <form method="POST" action="{{ provider_route('provider.bookings.complete', $booking) }}">
                                    @csrf
                                    <button class="success" type="submit" aria-label="Complete order">Complete</button>
                                </form>
                            @endif

                            @if ($canCancel)
                                <form method="POST" action="{{ provider_route('provider.bookings.cancel', $booking) }}">
                                    @csrf
                                    <button class="secondary" type="submit" aria-label="Cancel">Cancel</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="provider-booking-card-empty">
                    <svg viewBox="0 0 24 24">
                        <path d="M8 2v4"></path>
                        <path d="M16 2v4"></path>
                        <path d="M5 5h14v16H5z"></path>
                        <path d="M3 10h18"></path>
                    </svg>
                    <strong>No booking data found.</strong>
                    <p>Try changing the keyword, date filter, payment, mode, or booking status.</p>
                </div>
            @endforelse
        </div>
    </div>

    <div id="listViewContainer" style="display: none;">
        <div class="admin-booking-table-wrap category-table-wrap provider-booking-category-table-wrap">
            <table class="admin-booking-table detailed category-table provider-booking-category-table">
                <colgroup class="provider-booking-column-layout">
                    <col class="provider-booking-col-code">
                    <col class="provider-booking-col-appointment">
                    <col class="provider-booking-col-customer">
                    <col class="provider-booking-col-service">
                    <col class="provider-booking-col-payment">
                    <col class="provider-booking-col-status">
                    <col class="provider-booking-col-action">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col" class="provider-booking-th-code">
                            <a href="{{ provider_route('provider.bookings.index', $sortQueryFor('booking_code')) }}" class="admin-booking-sort {{ $sortBy === 'booking_code' ? 'active' : '' }}" aria-label="{{ $sortAriaLabel('booking_code', 'booking') }}">
                                Booking
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <svg viewBox="0 0 10 12" fill="none">
                                        <path class="sort-chevron sort-chevron-up {{ $sortIconClass('booking_code', 'asc') }}" d="M2.75 4.25 5 2l2.25 2.25"></path>
                                        <path class="sort-chevron sort-chevron-down {{ $sortIconClass('booking_code', 'desc') }}" d="M2.75 7.75 5 10l2.25-2.25"></path>
                                    </svg>
                                </span>
                            </a>
                        </th>
                        <th scope="col" class="provider-booking-th-appointment">
                            <a href="{{ provider_route('provider.bookings.index', $sortQueryFor('booking_date')) }}" class="admin-booking-sort {{ $sortBy === 'booking_date' ? 'active' : '' }}" aria-label="{{ $sortAriaLabel('booking_date', 'appointment') }}">
                                Appointment
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <svg viewBox="0 0 10 12" fill="none">
                                        <path class="sort-chevron sort-chevron-up {{ $sortIconClass('booking_date', 'asc') }}" d="M2.75 4.25 5 2l2.25 2.25"></path>
                                        <path class="sort-chevron sort-chevron-down {{ $sortIconClass('booking_date', 'desc') }}" d="M2.75 7.75 5 10l2.25-2.25"></path>
                                    </svg>
                                </span>
                            </a>
                        </th>
                        <th scope="col" class="provider-booking-th-customer">Customer</th>
                        <th scope="col" class="provider-booking-th-service">Service</th>
                        <th scope="col" class="provider-booking-th-payment">
                            <a href="{{ provider_route('provider.bookings.index', $sortQueryFor('payment_status')) }}" class="admin-booking-sort {{ $sortBy === 'payment_status' ? 'active' : '' }}" aria-label="{{ $sortAriaLabel('payment_status', 'payment') }}">
                                Payment
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <svg viewBox="0 0 10 12" fill="none">
                                        <path class="sort-chevron sort-chevron-up {{ $sortIconClass('payment_status', 'asc') }}" d="M2.75 4.25 5 2l2.25 2.25"></path>
                                        <path class="sort-chevron sort-chevron-down {{ $sortIconClass('payment_status', 'desc') }}" d="M2.75 7.75 5 10l2.25-2.25"></path>
                                    </svg>
                                </span>
                            </a>
                        </th>
                        <th scope="col" class="provider-booking-th-status">
                            <a href="{{ provider_route('provider.bookings.index', $sortQueryFor('status')) }}" class="admin-booking-sort {{ $sortBy === 'status' ? 'active' : '' }}" aria-label="{{ $sortAriaLabel('status', 'status') }}">
                                Status
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <svg viewBox="0 0 10 12" fill="none">
                                        <path class="sort-chevron sort-chevron-up {{ $sortIconClass('status', 'asc') }}" d="M2.75 4.25 5 2l2.25 2.25"></path>
                                        <path class="sort-chevron sort-chevron-down {{ $sortIconClass('status', 'desc') }}" d="M2.75 7.75 5 10l2.25-2.25"></path>
                                    </svg>
                                </span>
                            </a>
                        </th>
                        <th scope="col" class="provider-booking-th-action">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($bookingEntries as $entry)
                        @php
                            $booking = $entry->booking;
                            $bookingStatus = $booking->status ?? 'pending';
                            $customerName = $entry->customer_name;
                            $customerPhone = $entry->customer_phone;
                            $serviceName = ($entry->services ?? collect())->pluck('title')->filter()->join(', ') ?: '-';
                            $serviceCount = ($entry->services ?? collect())->count();
                            $branchName = $booking->branch->branch_name ?? '-';
                            $branchLocation = $booking->branch ? collect([$booking->branch->city_id, $booking->branch->state_id])->filter()->implode(', ') : '';
                            $staffName = $entry->staff?->full_name ?: 'Any Available';
                            $bookingType = $booking->booking_type ?? 'scheduled';
                            $paymentStatus = optional($booking->payment)->status ?? 'unpaid';
                            $paymentMethod = optional($booking->payment)->payment_method
                                ?? optional($booking->payment)->payment_channel
                                ?? optional($booking->payment)->payment_type
                                ?? null;
                            $amount = $entry->total_price;
                            $dateValue = $entry->booking_date ?? $booking->created_at ?? null;
                            $startTime = $formatTime($entry->start_time);
                            $endTime = $formatTime($entry->estimated_end_time);
                            $canCheckIn = in_array($bookingStatus, ['confirmed', 'waiting'], true);
                            $canStart = in_array($bookingStatus, ['confirmed', 'waiting', 'checked_in'], true);
                            $canComplete = in_array($bookingStatus, ['in_progress', 'inprogress'], true);
                            $canCancel = ! in_array($bookingStatus, ['completed', 'order_completed', 'refund_completed', 'cancelled', 'provider_cancelled', 'customer_cancelled', 'no_show'], true);
                        @endphp

                        <tr>
                            <td class="provider-booking-td-code" data-label="Booking">
                                <div class="category-name-box provider-booking-code-box">

                                    <div class="category-name-text">
                                        <strong title="{{ $entry->display_code }}">{{ $entry->display_code }}</strong>
                                        <small>ID #{{ $booking->id }}</small>
                                        @if ($entry->is_group)
                                            <small>{{ $entry->participant_label }}</small>
                                        @endif
                                        @if ($booking->queue_number)
                                            <small>Queue #{{ $booking->queue_number }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td class="provider-booking-td-appointment" data-label="Appointment">
                                <div class="admin-booking-date">
                                    <strong>{{ $formatDate($dateValue) }}</strong>
                                    @if ($startTime)
                                        <small>{{ $startTime }}{{ $endTime ? ' - ' . $endTime : '' }}</small>
                                    @else
                                        <small>Time not set</small>
                                    @endif
                                    @if ($booking->queue_number)
                                        <small>Queue #{{ $booking->queue_number }}</small>
                                    @endif
                                    <span class="provider-booking-appointment-mode admin-booking-status {{ $statusClass($bookingType) }}">
                                        {{ $statusLabel($bookingType) }}
                                    </span>
                                </div>
                            </td>

                            <td class="provider-booking-td-customer" data-label="Customer">
                                <div class="admin-booking-person">
                                    <span>{{ $bookingInitial($booking, $customerName) }}</span>
                                    <div>
                                        <strong title="{{ $customerName }}">{{ $customerName }}</strong>
                                        <small title="{{ $customerPhone ?: 'No phone' }}">{{ $customerPhone ?: 'No phone' }}</small>
                                        @if ($entry->is_group)
                                            <small>Dipesan oleh {{ $booking->customer?->name ?: $booking->customer_name }}</small>
                                        @else
                                            <small>Booking personal</small>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td class="provider-booking-td-service" data-label="Service">
                                <p class="category-description-text" title="{{ $serviceName }}">{{ Str::limit($serviceName, 92) }}</p>
                                <small class="provider-booking-description-meta">
                                    {{ $serviceCount > 1 ? $serviceCount . ' services' : 'Single service' }} &middot;
                                    {{ (int) $entry->total_duration > 0 ? $entry->total_duration . ' min' : 'Duration -' }}
                                </small>
                                <small class="provider-booking-description-meta">
                                    {{ $staffName }} &middot; {{ $branchName }}{{ $branchLocation ? ', ' . $branchLocation : '' }}
                                </small>
                            </td>

                            <td class="provider-booking-td-payment" data-label="Payment">
                                <div class="admin-booking-mode-stack">
                                    <span class="admin-booking-status {{ $statusClass($paymentStatus) }}">
                                        {{ $statusLabel($paymentStatus) }}
                                    </span>
                                    <strong class="provider-booking-payment-amount">{{ $formatMoney($amount) }}</strong>
                                    @if ($entry->is_group)
                                        <small class="provider-booking-payment-total">Total booking {{ $formatMoney($entry->booking_total_price) }}</small>
                                    @endif
                                    @if ($paymentMethod)
                                        <small class="provider-booking-payment-method">{{ $statusLabel($paymentMethod) }}</small>
                                    @endif
                                </div>
                            </td>

                            <td class="provider-booking-td-status" data-label="Status">
                                <div class="provider-booking-status-wrap">
                                    <span class="admin-booking-status {{ $statusClass($bookingStatus) }}">
                                        {{ $statusLabel($bookingStatus) }}
                                    </span>
                                </div>
                            </td>

                            <td class="provider-booking-td-action" data-label="Action">
                                @if ($canCheckIn || $canStart || $canComplete || $canCancel)
                                    <div class="category-actions provider-booking-action-icons provider-booking-row-actions">
                                        @if ($canCheckIn)
                                            <form method="POST" action="{{ provider_route('provider.bookings.check-in', $booking) }}">
                                                @csrf
                                                <button class="category-action-btn info" type="submit" title="Check-in" aria-label="Check-in {{ $booking->booking_code ?? ('#' . $booking->id) }}">
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M20 6 9 17l-5-5"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif

                                        @if ($canStart)
                                            <form method="POST" action="{{ provider_route('provider.bookings.start', $booking) }}">
                                                @csrf
                                                <button class="category-action-btn success" type="submit" title="Start" aria-label="Start {{ $booking->booking_code ?? ('#' . $booking->id) }}">
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M8 5v14l11-7-11-7Z"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif

                                        @if ($canComplete)
                                            <form method="POST" action="{{ provider_route('provider.bookings.complete', $booking) }}">
                                                @csrf
                                                <button class="category-action-btn dark" type="submit" title="Complete" aria-label="Complete {{ $booking->booking_code ?? ('#' . $booking->id) }}">
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M4 12l5 5L20 6"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif

                                        @if ($canCancel)
                                            <form method="POST" action="{{ provider_route('provider.bookings.cancel', $booking) }}">
                                                @csrf
                                                <button class="category-action-btn danger" type="submit" title="Cancel" aria-label="Cancel {{ $booking->booking_code ?? ('#' . $booking->id) }}">
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M18 6 6 18"></path>
                                                        <path d="m6 6 12 12"></path>
                                                    </svg>
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ provider_route('provider.bookings.no-show', $booking) }}">
                                                @csrf
                                                <button class="category-action-btn danger" type="submit" title="No-show" aria-label="No-show {{ $booking->booking_code ?? ('#' . $booking->id) }}">
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                                        <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                                                        <path d="m17 8 5 5"></path>
                                                        <path d="m22 8-5 5"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <span class="provider-booking-no-actions">No action</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M8 2v4"></path>
                                            <path d="M16 2v4"></path>
                                            <path d="M5 5h14v16H5z"></path>
                                            <path d="M3 10h18"></path>
                                        </svg>
                                    </span>

                                    <strong>No booking data found.</strong>
                                    <p>Try changing the keyword, date filter, payment, mode, or booking status.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    <div class="admin-booking-footer category-footer">
        <p class="admin-booking-showing">
            <strong>{{ number_format($firstItem) }}-{{ number_format($lastItem) }}</strong>
            <span>/ {{ number_format($totalItem) }}</span>
        </p>

        @if ($hasPaginator)
            <div class="admin-booking-pagination category-pagination">
                @if ($bookingCollection->onFirstPage())
                    <span class="disabled">&lsaquo;</span>
                @else
                    <a href="{{ $bookingCollection->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
                @endif

                <span class="active">{{ $bookingCollection->currentPage() }}</span>

                @if ($bookingCollection->hasMorePages())
                    <a href="{{ $bookingCollection->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
                @else
                    <span class="disabled">&rsaquo;</span>
                @endif
            </div>
        @else
            <div class="admin-booking-pagination category-pagination static">
                <span class="active">1</span>
            </div>
        @endif
    </div>
</section>



<div class="category-modal" id="filterModalOverlay" aria-hidden="true">
    <div class="category-modal-dialog" id="filterModal" role="dialog" aria-modal="true" aria-labelledby="filterModalTitle">
        <h3 id="filterModalTitle" style="margin-top:0; margin-bottom: 20px; font-size:1.25rem;">Advanced Filters</h3>
        <form method="GET" action="{{ provider_route('provider.bookings.index') }}">
            @if (! empty($currentStatus) && $currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <label class="admin-booking-field">
                    <span style="display:block; margin-bottom:4px; font-size:12px; font-weight:600; color:var(--admin-muted);">Date From</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px;">
                </label>
                <label class="admin-booking-field">
                    <span style="display:block; margin-bottom:4px; font-size:12px; font-weight:600; color:var(--admin-muted);">Date To</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px;">
                </label>
                <label class="admin-booking-field">
                    <span style="display:block; margin-bottom:4px; font-size:12px; font-weight:600; color:var(--admin-muted);">Payment Status</span>
                    <select name="payment_status" style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px;">
                        @foreach ($paymentStatuses as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['payment_status'] ?? 'all') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-booking-field">
                    <span style="display:block; margin-bottom:4px; font-size:12px; font-weight:600; color:var(--admin-muted);">Booking Mode</span>
                    <select name="booking_type" style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px;">
                        @foreach ($bookingTypes as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['booking_type'] ?? 'all') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-booking-field">
                    <span style="display:block; margin-bottom:4px; font-size:12px; font-weight:600; color:var(--admin-muted);">Rows per page</span>
                    <select name="per_page" style="width:100%; border:1px solid #e2e8f0; border-radius:8px; padding:8px;">
                        <option value="10" {{ (int) ($filters['per_page'] ?? 10) === 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ (int) ($filters['per_page'] ?? 10) === 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ (int) ($filters['per_page'] ?? 10) === 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ (int) ($filters['per_page'] ?? 10) === 100 ? 'selected' : '' }}>100</option>
                    </select>
                </label>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
                <button type="button" class="modal-cancel-btn" id="filterModalClose" style="padding:10px 16px; border:none; background:#f1f5f9; border-radius:8px; cursor:pointer; font-weight:600;">Cancel</button>
                <a href="{{ provider_route('provider.bookings.index') }}" style="padding:10px 16px; border:none; background:#fee2e2; color:#b91c1c; border-radius:8px; cursor:pointer; font-weight:600; text-decoration:none;">Reset</a>
                <button type="submit" style="padding:10px 16px; border:none; background:var(--admin-primary); color:#fff; border-radius:8px; cursor:pointer; font-weight:600;">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

@endsection
