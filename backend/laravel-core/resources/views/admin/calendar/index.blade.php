@extends('admin.layouts.app')

@section('title', 'Calendar - JasaKu')
@section('page_title', 'Calendar')

@section('content')
@php
    $statusLabels = [
        'open' => 'Open',
        'pending' => 'Pending',
        'pending_payment' => 'Pending Pay',
        'confirmed' => 'Confirmed',
        'waiting' => 'Waiting',
        'checked_in' => 'Checked In',
        'in_progress' => 'In Progress',
        'inprogress' => 'In Progress',
        'completed' => 'Completed',
        'order_completed' => 'Completed',
        'refund_completed' => 'Refunded',
        'provider_cancelled' => 'Provider Cancel',
        'customer_cancelled' => 'Customer Cancel',
        'cancelled' => 'Cancelled',
        'rescheduled' => 'Rescheduled',
        'no_show' => 'No Show',
    ];

    $viewLabels = [
        'month' => 'Month',
        'week' => 'Week',
        'day' => 'Day',
    ];

    $calendarHelp = [
        'month' => 'View all bookings in one month. Click a date to open the daily agenda.',
        'week' => 'View bookings by day and hour in one week.',
        'day' => 'View hourly booking details for one day.',
    ];

    $statusLabel = fn ($value) => $statusLabels[$value ?: 'open'] ?? ucwords(str_replace('_', ' ', $value ?: 'open'));

    $statusClass = function ($value) {
        return match ($value) {
            'completed', 'order_completed', 'refund_completed', 'paid' => 'success',
            'pending', 'pending_payment', 'waiting', 'confirmed', 'rescheduled', 'unpaid' => 'warning',
            'checked_in', 'inprogress', 'in_progress', 'scheduled', 'queue', 'walk_in', 'open' => 'info',
            'provider_cancelled', 'customer_cancelled', 'cancelled', 'no_show', 'rejected', 'failed' => 'danger',
            default => 'neutral',
        };
    };

    $bookingTime = function ($booking) {
        $time = $booking->start_time;

        if (empty($time)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($time)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    };

    $bookingTimeRange = function ($booking) use ($bookingTime) {
        $start = $bookingTime($booking);
        $end = null;

        if (! empty($booking->estimated_end_time)) {
            try {
                $end = \Carbon\Carbon::parse($booking->estimated_end_time)->format('H:i');
            } catch (\Throwable) {
                $end = null;
            }
        }

        if ($start && $end) {
            return $start . ' - ' . $end;
        }

        return $start;
    };

    $bookingHour = function ($booking) {
        $time = $booking->start_time;

        if (empty($time)) {
            return null;
        }

        try {
            return (int) \Carbon\Carbon::parse($time)->format('G');
        } catch (\Throwable) {
            return null;
        }
    };

    $bookingDate = function ($booking) {
        try {
            return \Carbon\Carbon::parse($booking->booking_date)->format('d M');
        } catch (\Throwable) {
            return '-';
        }
    };

    $serviceName = function ($booking) {
        $services = $booking->services ?? collect();

        return $services->isNotEmpty()
            ? $services->pluck('title')->join(', ')
            : ($booking->service->title
                ?? $booking->service->name
                ?? $booking->service_name
                ?? 'Service');
    };

    $customerName = fn ($booking) => $booking->customer_name
        ?? $booking->customer->name
        ?? $booking->user_name
        ?? 'Customer';

    $providerName = fn ($booking) => $booking->provider->name
        ?? $booking->provider_name
        ?? 'Provider';

    $bookingTotal = fn ($booking) => (float) $booking->total_price;

    $bookingUrl = fn ($booking) => route('admin.bookings.index', [
        'search' => $booking->booking_code ?? $booking->id,
    ]);
@endphp

<section class="calendar-page">
    <div class="calendar-route">
        <div class="calendar-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Calendar</strong>
        </div>
    </div>

    <div class="calendar-card">
        <form method="GET" action="{{ route('admin.calendar.index') }}" class="calendar-filter-panel">
            <input type="hidden" name="view" value="{{ $view }}">

            <label class="calendar-filter-field">
                <span>Date</span>
                <input type="date" name="date" value="{{ $baseDate->format('Y-m-d') }}">
            </label>

            <button type="submit" class="calendar-filter-submit">Apply</button>
        </form>

        <div class="calendar-toolbar">
            <div class="calendar-left-actions" aria-label="Calendar navigation">
                <a href="{{ route('admin.calendar.index', ['view' => $view, 'date' => now()->format('Y-m-d')]) }}"
                   class="calendar-today-btn">
                    Today
                </a>
            </div>

            <div class="calendar-title-nav">
                <a href="{{ route('admin.calendar.index', ['view' => $view, 'date' => $prevDate->format('Y-m-d')]) }}"
                   class="calendar-nav-btn"
                   aria-label="Previous {{ $view }}">
                    <span>&lsaquo;</span>
                </a>

                <div class="calendar-title-block">
                    <span>{{ $viewLabels[$view] ?? ucfirst($view) }} View</span>
                    <h2 class="calendar-title">{{ $calendarTitle }}</h2>
                    <small>{{ number_format($calendarAgenda->count()) }} bookings in this range</small>
                </div>

                <a href="{{ route('admin.calendar.index', ['view' => $view, 'date' => $nextDate->format('Y-m-d')]) }}"
                   class="calendar-nav-btn"
                   aria-label="Next {{ $view }}">
                    <span>&rsaquo;</span>
                </a>
            </div>

            <div class="calendar-view-actions" aria-label="Calendar view mode">
                <a href="{{ route('admin.calendar.index', ['view' => 'month', 'date' => $baseDate->format('Y-m-d')]) }}"
                   class="calendar-view-btn {{ $view === 'month' ? 'active' : '' }}">
                    Month
                </a>

                <a href="{{ route('admin.calendar.index', ['view' => 'week', 'date' => $baseDate->format('Y-m-d')]) }}"
                   class="calendar-view-btn {{ $view === 'week' ? 'active' : '' }}">
                    Week
                </a>

                <a href="{{ route('admin.calendar.index', ['view' => 'day', 'date' => $baseDate->format('Y-m-d')]) }}"
                   class="calendar-view-btn {{ $view === 'day' ? 'active' : '' }}">
                    Day
                </a>
            </div>
        </div>

        <div class="calendar-guide">
            {{ $calendarHelp[$view] ?? 'Choose a date and calendar view to see bookings.' }}
        </div>

        @if ($view === 'month')
            <div class="calendar-table-wrap">
                <table class="calendar-table">
                    <thead>
                        <tr>
                            <th>Min</th>
                            <th>Sen</th>
                            <th>Sel</th>
                            <th>Rab</th>
                            <th>Kam</th>
                            <th>Jum</th>
                            <th>Sab</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($monthWeeks as $week)
                            <tr>
                                @foreach ($week as $day)
                                    @php
                                        $dateKey = $day['date']->format('Y-m-d');
                                        $dayBookings = $eventsByDate[$dateKey] ?? collect();
                                    @endphp

                                    <td class="calendar-day-cell {{ !$day['is_current_month'] ? 'calendar-day-muted' : '' }} {{ $day['is_today'] ? 'calendar-day-today' : '' }} {{ $dayBookings->isNotEmpty() ? 'calendar-day-has-events' : '' }}">
                                        <a href="{{ route('admin.calendar.index', ['view' => 'day', 'date' => $day['date']->format('Y-m-d')]) }}"
                                           class="calendar-day-number"
                                           aria-label="Open {{ $day['date']->format('d M Y') }}">
                                            {{ $day['day'] }}
                                        </a>

                                        @if ($dayBookings->isNotEmpty())
                                            <div class="calendar-events">
                                                @foreach ($dayBookings->take(3) as $booking)
                                                   <a href="{{ $bookingUrl($booking) }}"
                                                       class="calendar-event status-{{ $statusClass($booking->status) }}">
                                                        <span>{{ $bookingTimeRange($booking) ?: 'No time' }}</span>
                                                        <strong>{{ $booking->booking_code ?? ('#' . $booking->id) }}</strong>
                                                    </a>
                                                @endforeach

                                                @if ($dayBookings->count() > 3)
                                                    <a href="{{ route('admin.calendar.index', ['view' => 'day', 'date' => $day['date']->format('Y-m-d')]) }}"
                                                       class="calendar-more-events">
                                                        +{{ $dayBookings->count() - 3 }} more
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($view === 'week')
            <div class="calendar-schedule-wrap">
                <table class="calendar-schedule-table">
                    <thead>
                        <tr>
                            <th class="time-col-head"></th>
                            @foreach ($weekDays as $day)
                                <th class="{{ $day['is_today'] ? 'schedule-head-today' : '' }}">
                                    <span>{{ $day['label'] }}</span>
                                    <strong>{{ $day['date']->format('j M') }}</strong>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($timeSlots as $slot)
                            <tr>
                                <td class="time-label">{{ $slot['label'] }}</td>

                                @foreach ($weekDays as $day)
                                    @php
                                        $dateKey = $day['date']->format('Y-m-d');
                                        $dayBookings = $eventsByDate[$dateKey] ?? collect();
                                        $slotBookings = $dayBookings->filter(fn ($booking) => $bookingHour($booking) === (int) $slot['hour']);
                                    @endphp

                                    <td class="schedule-cell {{ $day['is_today'] ? 'schedule-today-col' : '' }}">
                                        @foreach ($slotBookings->take(2) as $booking)
                                            <a href="{{ $bookingUrl($booking) }}"
                                               class="schedule-event status-{{ $statusClass($booking->status) }}">
                                                <strong>{{ $bookingTimeRange($booking) }} {{ $booking->booking_code ?? ('#' . $booking->id) }}</strong>
                                                <span>{{ $serviceName($booking) }}</span>
                                            </a>
                                        @endforeach

                                        @if ($slotBookings->count() > 2)
                                            <span class="schedule-more">+{{ $slotBookings->count() - 2 }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($view === 'day')
            <div class="calendar-schedule-wrap">
                <table class="calendar-schedule-table day-view-table">
                    <thead>
                        <tr>
                            <th class="time-col-head"></th>
                            <th class="{{ $dayData['is_today'] ? 'schedule-head-today' : '' }}">
                                <span>{{ $dayData['label'] }}</span>
                                <strong>{{ $dayData['date']->format('j M Y') }}</strong>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @php
                            $dateKey = $dayData['date']->format('Y-m-d');
                            $dayBookings = $eventsByDate[$dateKey] ?? collect();
                        @endphp

                        @foreach ($timeSlots as $slot)
                            @php
                                $slotBookings = $dayBookings->filter(fn ($booking) => $bookingHour($booking) === (int) $slot['hour']);
                            @endphp

                            <tr>
                                <td class="time-label">{{ $slot['label'] }}</td>
                                <td class="schedule-cell {{ $dayData['is_today'] ? 'schedule-today-col' : '' }}">
                                    @foreach ($slotBookings as $booking)
                                        <a href="{{ $bookingUrl($booking) }}"
                                           class="schedule-event status-{{ $statusClass($booking->status) }}">
                                            <strong>{{ $bookingTimeRange($booking) }} {{ $booking->booking_code ?? ('#' . $booking->id) }}</strong>
                                            <span>{{ $serviceName($booking) }}</span>
                                        </a>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="calendar-mobile-panel">
            <div class="calendar-mobile-strip" aria-label="Calendar days">
                @foreach ($calendarDays as $day)
                    <a href="{{ route('admin.calendar.index', ['view' => 'day', 'date' => $day['date']->format('Y-m-d')]) }}"
                       class="{{ $day['is_today'] ? 'today' : '' }} {{ $day['count'] > 0 ? 'has-events' : '' }}">
                        <span>{{ $day['label'] }}</span>
                        <strong>{{ $day['day'] }}</strong>
                        <small>{{ $day['count'] }}</small>
                    </a>
                @endforeach
            </div>

            <div class="calendar-mobile-agenda">
                <div class="calendar-mobile-agenda-head">
                    <strong>Agenda</strong>
                    <span>{{ number_format($calendarAgenda->count()) }} booking</span>
                </div>

                @forelse ($calendarAgenda->take(12) as $booking)
                    <a href="{{ $bookingUrl($booking) }}"
                       class="calendar-mobile-event status-{{ $statusClass($booking->status) }}">
                        <div class="calendar-mobile-date">
                            <strong>{{ $bookingDate($booking) }}</strong>
                            <span>{{ $bookingTimeRange($booking) ?: 'No time' }}</span>
                        </div>

                        <div class="calendar-mobile-copy">
                            <strong>{{ $booking->booking_code ?? ('#' . $booking->id) }} - {{ $serviceName($booking) }}</strong>
                            <span>{{ $customerName($booking) }} / {{ $providerName($booking) }}</span>
                            <small>Rp{{ number_format($bookingTotal($booking), 0, ',', '.') }}</small>
                        </div>

                        <em>{{ $statusLabel($booking->status) }}</em>
                    </a>
                @empty
                    <div class="calendar-mobile-empty">
                        <strong>No bookings in this range.</strong>
                        <span>Try choosing another day, week, or month.</span>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
