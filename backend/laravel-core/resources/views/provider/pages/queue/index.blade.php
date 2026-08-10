@extends('provider.layouts.dashboard')

@section('title', 'Queue - JasaKu')
@section('page_title', 'Queue')
@section('page_subtitle', 'Call customers, start services, and monitor estimated waiting times for queue and walk-in bookings.')

@section('content')
@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Auth;

    $queueCollection = $queueBookings ?? collect();

    try {
        $activeDate = Carbon::parse($date ?? now()->toDateString());
    } catch (\Throwable $exception) {
        $activeDate = now();
    }

    $dateValue = $activeDate->toDateString();
    $todayDate = now()->toDateString();
    $previousDate = $activeDate->copy()->subDay()->toDateString();
    $nextDate = $activeDate->copy()->addDay()->toDateString();
    $totalItem = $queueCollection->count();
    $firstItem = $totalItem > 0 ? 1 : 0;
    $lastItem = $totalItem;

    $queueWithPosition = $queueCollection->values();
    $waitingCount = $queueCollection->where('status', 'waiting')->count();
    $checkedInCount = $queueCollection->where('status', 'checked_in')->count();
    $inProgressCount = $queueCollection->filter(fn ($booking) => in_array($booking->status, ['in_progress', 'inprogress'], true))->count();
    $estimatedLastMinutes = $queueWithPosition
        ->map(fn ($booking, $index) => max(20, ($index + 1) * (int) ($booking->total_duration ?: 30)))
        ->max() ?? 0;

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
            'provider_cancelled', 'customer_cancelled', 'cancelled', 'no_show', 'rejected', 'failed' => 'danger',
            default => 'neutral',
        };
    };

    $formatDate = function ($value) {
        if (empty($value)) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (\Throwable $exception) {
            return '-';
        }
    };

    $formatTime = function ($value) {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $exception) {
            return substr((string) $value, 0, 5) ?: null;
        }
    };

    $serviceNames = function ($booking) {
        $services = $booking->services ?? collect();

        if ($services->isNotEmpty()) {
            return $services->pluck('title')->join(', ');
        }

        return $booking->service->title ?? '-';
    };

    $serviceCount = function ($booking) {
        $services = $booking->services ?? collect();

        return $services->isNotEmpty() ? $services->count() : ($booking->service ? 1 : 0);
    };

    $bookingInitial = fn ($booking, $customerName) => strtoupper(substr((string) ($customerName ?: $booking->booking_code ?: 'A'), 0, 1));
    $estimateRange = fn ($booking, $index) => max(10, $index * (int) ($booking->total_duration ?: 30)) . ' - ' . max(20, ($index + 1) * (int) ($booking->total_duration ?: 30)) . ' minutes';
@endphp

<section class="admin-category-page admin-booking-page provider-booking-category-page provider-queue-category-page">
    <div class="admin-booking-route admin-category-route provider-booking-route provider-queue-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Queue</strong>
        </div>

        <div class="provider-booking-category-actions provider-queue-actions provider-queue-actions-desktop">
            <a class="admin-category-add-button secondary" href="{{ provider_route('provider.bookings.index', ['date_from' => $dateValue, 'date_to' => $dateValue]) }}">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M8 2v4"></path>
                    <path d="M16 2v4"></path>
                    <path d="M5 5h14v16H5z"></path>
                    <path d="M3 10h18"></path>
                </svg>
                Bookings
            </a>

            <a class="admin-category-add-button secondary" href="{{ provider_route('provider.calendar.index', ['date' => $dateValue]) }}">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M3 8h18"></path>
                    <path d="M8 3v3"></path>
                    <path d="M16 3v3"></path>
                    <path d="M5 6h14v15H5z"></path>
                </svg>
                Calendar
            </a>

            <a class="admin-category-add-button" href="{{ provider_route('provider.walk-in.index') }}">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
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

    <div class="admin-booking-summary-grid">
        <div class="admin-booking-summary-card pink">
            <span>Active Queue</span>
            <strong>{{ number_format($totalItem) }}</strong>
            <small>Queue and walk-in items for this date</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Waiting</span>
            <strong>{{ number_format($waitingCount) }}</strong>
            <small>Customers ready to be called</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Checked In</span>
            <strong>{{ number_format($checkedInCount) }}</strong>
            <small>Customers already called</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>In Progress</span>
            <strong>{{ number_format($inProgressCount) }}</strong>
            <small>{{ $estimatedLastMinutes > 0 ? 'Estimated finish in ' . $estimatedLastMinutes . ' minutes' : 'No estimate yet' }}</small>
        </div>
    </div>

    <div class="admin-booking-card category-card provider-booking-category-card provider-queue-category-card">
        <div class="admin-booking-tabs provider-queue-tabs">
            <a href="{{ provider_route('provider.queue.index', ['date' => $dateValue]) }}" class="admin-booking-tab active">
                Active Queue
            </a>
            <a href="{{ provider_route('provider.bookings.index', ['date_from' => $dateValue, 'date_to' => $dateValue, 'booking_type' => 'queue']) }}" class="admin-booking-tab">
                Queue Bookings
            </a>
            <a href="{{ provider_route('provider.bookings.index', ['date_from' => $dateValue, 'date_to' => $dateValue, 'booking_type' => 'walk_in']) }}" class="admin-booking-tab">
                Walk-in
            </a>
            <a href="{{ provider_route('provider.calendar.index', ['date' => $dateValue]) }}" class="admin-booking-tab">
                Staff Calendar
            </a>
        </div>

        <form method="GET" action="{{ provider_route('provider.queue.index') }}" class="admin-booking-filter-panel compact provider-queue-filter-panel">
            <div class="admin-booking-filter-row provider-queue-filter-row">
                <label class="admin-booking-field provider-queue-date-field">
                    <input type="date" name="date" value="{{ $dateValue }}" aria-label="Queue date" title="Queue date">
                </label>

                <div class="admin-booking-filter-buttons">
                    <button type="submit">View</button>
                    @if ($dateValue !== $todayDate)
                        <a href="{{ provider_route('provider.queue.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} active queue items</span>
                <span>Date: {{ $formatDate($dateValue) }}</span>
                <span>{{ number_format($waitingCount) }} waiting</span>
                <span>{{ number_format($checkedInCount + $inProgressCount) }} being handled</span>
            </div>
        </form>

        <div class="admin-category-add-row provider-booking-category-actions provider-queue-actions provider-queue-actions-mobile">
            <a class="admin-category-add-button secondary" href="{{ provider_route('provider.queue.index', ['date' => $previousDate]) }}">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="m15 18-6-6 6-6"></path>
                </svg>
                Previous
            </a>

            <a class="admin-category-add-button secondary" href="{{ provider_route('provider.queue.index', ['date' => $nextDate]) }}">
                Next
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="m9 18 6-6-6-6"></path>
                </svg>
            </a>

            <a class="admin-category-add-button" href="{{ provider_route('provider.walk-in.index') }}">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Walk-in
            </a>
        </div>

        <div class="admin-category-mobile-list admin-booking-mobile-list provider-queue-mobile-list">
            @forelse ($queueWithPosition as $index => $booking)
                @php
                    $bookingStatus = $booking->status ?? 'waiting';
                    $customerName = $booking->customer->name ?? $booking->customer_name ?? 'Walk-in';
                    $customerPhone = $booking->customer_phone ?? optional($booking->customer?->customerProfile)->phone_number ?? null;
                    $bookingType = $booking->booking_type ?? 'queue';
                    $branchName = $booking->branch->branch_name ?? '-';
                    $staffName = $booking->staff?->full_name ?: 'Any Available';
                    $serviceName = $serviceNames($booking);
                    $duration = (int) ($booking->total_duration ?: 30);
                    $startTime = $formatTime($booking->start_time);
                    $endTime = $formatTime($booking->estimated_end_time ?? null);
                    $canCall = $bookingStatus === 'waiting';
                    $canStart = in_array($bookingStatus, ['waiting', 'checked_in'], true);
                    $canComplete = in_array($bookingStatus, ['in_progress', 'inprogress'], true);
                @endphp

                <article class="admin-category-mobile-card admin-booking-mobile-card provider-queue-mobile-card">
                    <header class="admin-category-mobile-head">
                        <div class="admin-category-mobile-title">
                            <span>{{ $bookingInitial($booking, $customerName) }}</span>

                            <div>
                                <strong>{{ $customerName }}</strong>
                                <span>{{ $booking->booking_code ?? ('ID #' . $booking->id) }}</span>
                            </div>
                        </div>

                        <b>#{{ $booking->queue_number ?: '-' }}</b>
                    </header>

                    <div class="admin-category-mobile-main admin-booking-mobile-main provider-queue-mobile-main">
                        <div>
                            <span>Service</span>
                            <strong>{{ Str::limit($serviceName, 32) }}</strong>
                            <small>{{ $serviceCount($booking) }} service &middot; {{ $duration }} minutes</small>
                        </div>

                        <div>
                            <span>Estimate</span>
                            <strong>{{ $estimateRange($booking, $index) }}</strong>
                            <small>{{ $startTime ? 'Started ' . $startTime : 'Not started yet' }}{{ $endTime ? ' - ' . $endTime : '' }}</small>
                        </div>

                        <div>
                            <span>Staff</span>
                            <strong>{{ $staffName }}</strong>
                            <small>{{ $branchName }}</small>
                        </div>

                        <div>
                            <span>Contact</span>
                            <strong>{{ $customerPhone ?: 'No phone' }}</strong>
                            <small>{{ $statusLabel($bookingType) }}</small>
                        </div>
                    </div>

                    <p>{{ Str::limit($serviceName, 120) }}</p>

                    <footer class="admin-category-mobile-footer provider-booking-mobile-footer provider-queue-mobile-footer">
                        <span class="admin-booking-status {{ $statusClass($bookingType) }}">
                            {{ $statusLabel($bookingType) }}
                        </span>

                        <span class="admin-booking-status {{ $statusClass($bookingStatus) }}">
                            {{ $statusLabel($bookingStatus) }}
                        </span>

                        @if ($canCall || $canStart || $canComplete)
                            <div class="category-actions provider-booking-action-icons provider-booking-mobile-action-icons provider-queue-mobile-action-icons">
                                @if ($canCall)
                                    <form method="POST" action="{{ provider_route('provider.queue.call', $booking) }}">
                                        @csrf
                                        <button class="category-action-btn info" type="submit" title="Call" aria-label="Call {{ $booking->booking_code ?? ('#' . $booking->id) }}">
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"></path>
                                                <path d="M9 17a3 3 0 0 0 6 0"></path>
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
                            </div>
                        @endif
                    </footer>
                </article>
            @empty
                <div class="admin-category-mobile-empty admin-booking-mobile-empty">
                    <strong>The queue is still empty.</strong>
                    <p>No active queue or walk-in items on {{ $formatDate($dateValue) }} yet.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap category-table-wrap provider-queue-category-table-wrap">
            <table class="admin-booking-table detailed category-table provider-queue-category-table">
                <thead>
                    <tr>
                        <th>Queue No.</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Staff & Branch</th>
                        <th>Estimate</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($queueWithPosition as $index => $booking)
                        @php
                            $bookingStatus = $booking->status ?? 'waiting';
                            $customerName = $booking->customer->name ?? $booking->customer_name ?? 'Walk-in';
                            $customerPhone = $booking->customer_phone ?? optional($booking->customer?->customerProfile)->phone_number ?? null;
                            $bookingType = $booking->booking_type ?? 'queue';
                            $branchName = $booking->branch->branch_name ?? '-';
                            $staffName = $booking->staff?->full_name ?: 'Any Available';
                            $serviceName = $serviceNames($booking);
                            $duration = (int) ($booking->total_duration ?: 30);
                            $startTime = $formatTime($booking->start_time);
                            $endTime = $formatTime($booking->estimated_end_time ?? null);
                            $canCall = $bookingStatus === 'waiting';
                            $canStart = in_array($bookingStatus, ['waiting', 'checked_in'], true);
                            $canComplete = in_array($bookingStatus, ['in_progress', 'inprogress'], true);
                        @endphp

                        <tr>
                            <td>
                                <div class="category-name-box provider-booking-code-box provider-queue-code-box">
                                    <span class="category-thumb-placeholder">{{ $bookingInitial($booking, $customerName) }}</span>

                                    <div class="category-name-text">
                                        <strong>#{{ $booking->queue_number ?: '-' }}</strong>
                                        <small>{{ $booking->booking_code ?? ('ID #' . $booking->id) }}</small>
                                        <small>{{ $formatDate($booking->booking_date ?? $dateValue) }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-person">
                                    <span>{{ $bookingInitial($booking, $customerName) }}</span>
                                    <div>
                                        <strong>{{ $customerName }}</strong>
                                        <small>{{ $customerPhone ?: 'No phone' }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <p class="category-description-text">{{ Str::limit($serviceName, 92) }}</p>
                                <small class="provider-booking-description-meta">
                                    {{ $serviceCount($booking) }} service &middot; {{ $duration }} minutes
                                </small>
                                @if (! empty($booking->notes))
                                    <small class="provider-booking-description-meta">{{ Str::limit($booking->notes, 58) }}</small>
                                @endif
                            </td>

                            <td>
                                <div class="admin-booking-date provider-queue-branch-stack">
                                    <strong>{{ $staffName }}</strong>
                                    <small>{{ $branchName }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date provider-queue-estimate">
                                    <strong>{{ $estimateRange($booking, $index) }}</strong>
                                    @if ($startTime)
                                        <small>{{ $startTime }}{{ $endTime ? ' - ' . $endTime : '' }}</small>
                                    @else
                                        <small>Not started yet</small>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <span class="admin-booking-status {{ $statusClass($bookingType) }}">
                                    {{ $statusLabel($bookingType) }}
                                </span>
                            </td>

                            <td>
                                <span class="admin-booking-status {{ $statusClass($bookingStatus) }}">
                                    {{ $statusLabel($bookingStatus) }}
                                </span>
                            </td>

                            <td>
                                @if ($canCall || $canStart || $canComplete)
                                    <div class="category-actions provider-booking-action-icons provider-booking-row-actions provider-queue-row-actions">
                                        @if ($canCall)
                                            <form method="POST" action="{{ provider_route('provider.queue.call', $booking) }}">
                                                @csrf
                                                <button class="category-action-btn info" type="submit" title="Call" aria-label="Call {{ $booking->booking_code ?? ('#' . $booking->id) }}">
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"></path>
                                                        <path d="M9 17a3 3 0 0 0 6 0"></path>
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
                                    </div>
                                @else
                                    <span class="admin-booking-status neutral">Locked</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 6h16"></path>
                                            <path d="M4 12h16"></path>
                                            <path d="M4 18h10"></path>
                                        </svg>
                                    </span>

                                    <strong>The queue is still empty.</strong>
                                    <p>No active queue or walk-in items on {{ $formatDate($dateValue) }} yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer category-footer">
            <p class="admin-booking-showing">
                <strong>{{ number_format($firstItem) }}-{{ number_format($lastItem) }}</strong>
                <span>/ {{ number_format($totalItem) }}</span>
            </p>

            <div class="admin-booking-pagination category-pagination static">
                <span class="active">1</span>
            </div>
        </div>
    </div>
</section>
@endsection
