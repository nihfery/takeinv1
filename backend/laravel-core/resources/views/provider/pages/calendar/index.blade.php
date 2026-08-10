@extends('provider.layouts.dashboard')

@section('title', 'Appointment Calendar - JasaKu')
@section('page_title', 'Appointment Calendar')
@section('page_subtitle', 'See every appointment by date and open a day for complete order details.')

@section('content')
@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $calendarEntries = $calendarEntries ?? collect();
    $calendarEntriesByDate = $calendarEntriesByDate ?? collect();

    try {
        $activeDate = Carbon::parse($date);
    } catch (\Throwable $exception) {
        $activeDate = now();
    }

    $calendarView = in_array($calendarView ?? 'today', ['today', 'week', 'month', 'year'], true)
        ? ($calendarView ?? 'today')
        : 'today';
    $rangeStart = isset($rangeStart) ? Carbon::parse($rangeStart)->startOfDay() : $activeDate->copy()->startOfMonth();
    $rangeEnd = isset($rangeEnd) ? Carbon::parse($rangeEnd)->endOfDay() : $activeDate->copy()->endOfMonth();
    $monthStart = $activeDate->copy()->startOfMonth();
    $monthEnd = $activeDate->copy()->endOfMonth();
    $calendarStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
    $calendarEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);
    $today = now()->startOfDay();
    $todayDate = $today->toDateString();

    [$previousDate, $nextDate] = match ($calendarView) {
        'today' => [$activeDate->copy()->subDay(), $activeDate->copy()->addDay()],
        'week' => [$activeDate->copy()->subDays(7), $activeDate->copy()->addDays(7)],
        'year' => [$activeDate->copy()->subYear(), $activeDate->copy()->addYear()],
        default => [$activeDate->copy()->subMonth(), $activeDate->copy()->addMonth()],
    };

    $periodTitle = match ($calendarView) {
        'today' => $activeDate->format('l, d F Y'),
        'week' => $rangeStart->isSameMonth($rangeEnd)
            ? $rangeStart->format('d') . ' - ' . $rangeEnd->format('d F Y')
            : $rangeStart->format('d M') . ' - ' . $rangeEnd->format('d M Y'),
        'year' => $activeDate->format('Y'),
        default => $monthStart->format('F Y'),
    };

    $calendarDays = collect();
    for ($day = $calendarStart->copy(); $day->lte($calendarEnd); $day->addDay()) {
        $calendarDays->push($day->copy());
    }

    $agendaDays = collect();
    if (in_array($calendarView, ['today', 'week'], true)) {
        for ($day = $rangeStart->copy(); $day->lte($rangeEnd); $day->addDay()) {
            $agendaDays->push($day->copy());
        }
    }

    $detailDays = match ($calendarView) {
        'today', 'week' => $agendaDays,
        'month' => $calendarDays->filter(fn ($calendarDay) => $calendarDay->month === $monthStart->month && $calendarDay->year === $monthStart->year),
        default => collect(),
    };

    $calendarStaffs = collect($calendarStaffs ?? collect())->values();
    $calendarResourceTones = ['pink', 'amber', 'blue', 'teal', 'green', 'orange', 'red'];
    $hasUnassignedEntries = $calendarEntries->contains(fn ($entry) => empty($entry->staff));
    $schedulerResources = $calendarStaffs
        ->map(fn ($staff) => (object) [
            'key' => 'staff-' . $staff->id,
            'staff' => $staff,
            'name' => $staff->full_name ?: $staff->email ?: 'Staff',
        ]);
    if ($hasUnassignedEntries) {
        $schedulerResources->push((object) [
            'key' => 'unassigned',
            'staff' => null,
            'name' => 'Unassigned',
        ]);
    }
    if ($schedulerResources->isEmpty()) {
        $schedulerResources->push((object) [
            'key' => 'unassigned',
            'staff' => null,
            'name' => 'No staff',
        ]);
    }

    $minutesFromTime = function ($value): ?int {
        $formatted = $value ? substr((string) $value, 0, 5) : null;
        if (! $formatted || ! preg_match('/^(\d{2}):(\d{2})$/', $formatted, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    };
    $schedulerStartHour = 7;
    $schedulerEndHour = 22;
    $schedulerHourHeight = 64;
    $schedulerTopPadding = 20;
    $schedulerBottomPadding = 20;
    $schedulerIntervalMinutes = 30;
    $schedulerTimeMarkers = range($schedulerStartHour * 60, $schedulerEndHour * 60, $schedulerIntervalMinutes);
    $schedulerTimelineHeight = ($schedulerEndHour - $schedulerStartHour) * $schedulerHourHeight;
    $schedulerHeight = $schedulerTimelineHeight + $schedulerTopPadding + $schedulerBottomPadding;
    $schedulerResourceCount = max(1, $schedulerResources->count());

    $appointmentCount = $calendarEntries->count();

    $statusLabels = [
        'pending_payment' => 'Pending pay',
        'order_completed' => 'Completed',
        'refund_completed' => 'Refunded',
        'checked_in' => 'Checked in',
        'in_progress' => 'In progress',
        'inprogress' => 'In progress',
        'provider_cancelled' => 'Provider cancelled',
        'customer_cancelled' => 'Customer cancelled',
        'no_show' => 'No show',
        'walk_in' => 'Walk-in',
        'pay_at_salon' => 'Pay at salon',
        'full_payment' => 'Full payment',
    ];

    $statusLabel = fn ($value) => $statusLabels[$value ?: 'pending'] ?? ucfirst(str_replace('_', ' ', $value ?: 'pending'));

    $statusClass = function ($value) {
        return match ($value) {
            'completed', 'order_completed', 'refund_completed', 'paid', 'available', 'active' => 'success',
            'pending', 'pending_payment', 'waiting', 'confirmed', 'rescheduled', 'unpaid', 'dp' => 'warning',
            'checked_in', 'inprogress', 'in_progress', 'scheduled', 'queue', 'walk_in', 'pay_at_salon', 'full_payment' => 'info',
            'provider_cancelled', 'customer_cancelled', 'cancelled', 'no_show', 'rejected', 'failed', 'inactive' => 'danger',
            default => 'neutral',
        };
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

    $serviceNames = fn ($entry) => ($entry->services ?? collect())
        ->pluck('title')
        ->filter()
        ->join(', ') ?: '-';
    $staffName = fn ($entry) => $entry->staff?->full_name ?: 'Unassigned';
    $entryKey = fn ($entry) => 'booking-' . $entry->booking->id . '-participant-' . $entry->position;
    $initial = fn ($value, $fallback = 'A') => strtoupper(substr(trim((string) ($value ?: $fallback)), 0, 1));
    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
@endphp

<section class="admin-category-page provider-booking-category-page provider-appointment-calendar provider-calendar-consistent-page" data-appointment-calendar>
    <div class="provider-calendar-intro" hidden aria-hidden="true">
        <div>
            <span class="provider-calendar-eyebrow">APPOINTMENT OVERVIEW</span>
            <h2>Your monthly schedule, at a glance</h2>
            <p>Each date shows up to three appointments. Select any date to see every customer, service, staff member, and booking note.</p>
        </div>
        <div class="provider-calendar-intro-tip">
            <span>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20ZM12 10v6M12 7h.01"></path></svg>
            </span>
            <p><strong>Quick tip</strong>Click a date or “+ more” to open its full appointment list.</p>
        </div>
    </div>

    <div class="provider-month-calendar-card">
        <header class="provider-month-calendar-toolbar">
            <div class="provider-calendar-google-navigation">
                <a class="provider-calendar-today-button" href="{{ provider_route('provider.calendar.index', ['view' => 'today', 'date' => $todayDate]) }}">Today</a>
                <div class="provider-month-calendar-navigation">
                    <a href="{{ provider_route('provider.calendar.index', ['view' => $calendarView, 'date' => $previousDate->toDateString()]) }}" aria-label="Previous {{ $calendarView === 'week' ? '7 days' : $calendarView }}">
                        <svg viewBox="0 0 24 24" fill="none"><path d="m15 18-6-6 6-6"></path></svg>
                    </a>
                    <a href="{{ provider_route('provider.calendar.index', ['view' => $calendarView, 'date' => $nextDate->toDateString()]) }}" aria-label="Next {{ $calendarView === 'week' ? '7 days' : $calendarView }}">
                        <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                </div>
                <div class="provider-calendar-period-title">
                    <h3>{{ $periodTitle }}</h3>
                    <span>{{ number_format($appointmentCount) }} appointments</span>
                </div>
            </div>

            <div class="provider-month-calendar-tools">
                <form method="GET" action="{{ provider_route('provider.calendar.index') }}" class="provider-calendar-month-picker">
                    <input type="hidden" name="view" value="{{ $calendarView }}">
                    <label for="providerCalendarPeriod">Jump to {{ $calendarView === 'week' ? 'date' : $calendarView }}</label>
                    @if ($calendarView === 'year')
                        <select id="providerCalendarPeriod" name="date" aria-label="Select year">
                            @foreach (range($activeDate->year - 5, $activeDate->year + 5) as $yearOption)
                                <option value="{{ $yearOption }}-01-01" @selected($yearOption === $activeDate->year)>{{ $yearOption }}</option>
                            @endforeach
                        </select>
                    @elseif ($calendarView === 'month')
                        <input id="providerCalendarPeriod" type="month" name="date" value="{{ $monthStart->format('Y-m') }}">
                    @else
                        <input id="providerCalendarPeriod" type="date" name="date" value="{{ $activeDate->toDateString() }}">
                    @endif
                    <button type="submit" aria-label="Open selected period">Go</button>
                </form>
                <nav class="provider-calendar-view-tabs" aria-label="Calendar range">
                    <a href="{{ provider_route('provider.calendar.index', ['view' => 'today', 'date' => $todayDate]) }}" class="{{ $calendarView === 'today' ? 'active' : '' }}">Day</a>
                    <a href="{{ provider_route('provider.calendar.index', ['view' => 'week', 'date' => $activeDate->toDateString()]) }}" class="{{ $calendarView === 'week' ? 'active' : '' }}">7 Days</a>
                    <a href="{{ provider_route('provider.calendar.index', ['view' => 'month', 'date' => $activeDate->toDateString()]) }}" class="{{ $calendarView === 'month' ? 'active' : '' }}">Month</a>
                    <a href="{{ provider_route('provider.calendar.index', ['view' => 'year', 'date' => $activeDate->toDateString()]) }}" class="{{ $calendarView === 'year' ? 'active' : '' }}">Year</a>
                </nav>
            </div>
        </header>

        @if ($calendarView === 'month')
        <div class="provider-month-calendar-weekdays" aria-hidden="true">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                <span>{{ $weekday }}</span>
            @endforeach
        </div>

        <div class="provider-month-calendar-grid" role="grid" aria-label="Appointments for {{ $monthStart->format('F Y') }}">
            @foreach ($calendarDays as $calendarDay)
                @php
                    $dayDate = $calendarDay->toDateString();
                    $dayEntries = $calendarEntriesByDate->get($dayDate, collect());
                    $isCurrentMonth = $calendarDay->month === $monthStart->month && $calendarDay->year === $monthStart->year;
                    $isToday = $dayDate === $todayDate;
                    $otherMonthTarget = $calendarDay->copy()->startOfMonth()->toDateString();
                @endphp

                @if (!$isCurrentMonth)
                    <a
                        class="provider-month-calendar-day outside-month"
                        href="{{ provider_route('provider.calendar.index', ['view' => 'month', 'date' => $otherMonthTarget]) }}"
                        role="gridcell"
                        aria-label="Open {{ $calendarDay->format('F Y') }}"
                    >
                        <span class="provider-calendar-day-number">{{ $calendarDay->day }}</span>
                    </a>
                @else
                    <button
                        type="button"
                        class="provider-month-calendar-day {{ $isToday ? 'is-today' : '' }} {{ $dayEntries->isNotEmpty() ? 'has-appointments' : '' }}"
                        data-calendar-date="{{ $dayDate }}"
                        data-calendar-count="{{ $dayEntries->count() }}"
                        role="gridcell"
                        aria-label="{{ $calendarDay->format('l, d F Y') }}: {{ $dayEntries->count() }} appointments. Open details."
                    >
                        <span class="provider-calendar-day-head">
                            <span class="provider-calendar-day-number">{{ $calendarDay->day }}</span>
                            @if ($isToday)<small>Today</small>@endif
                            @if ($dayEntries->isNotEmpty())
                                <b>{{ $dayEntries->count() }}</b>
                            @endif
                        </span>

                        <span class="provider-calendar-day-events">
                            @foreach ($dayEntries->take(3) as $entry)
                                @php
                                    $entryStatus = $entry->booking->status ?? 'pending';
                                    $startTime = $formatTime($entry->start_time);
                                @endphp
                                <span class="provider-calendar-day-event {{ $statusClass($entryStatus) }}">
                                    <span><time>{{ $startTime ?: 'Any time' }}</time><strong>{{ Str::limit($entry->customer_name, 17) }}</strong></span>
                                    <small>{{ Str::limit($serviceNames($entry), 25) }}</small>
                                </span>
                            @endforeach

                            @if ($dayEntries->count() > 3)
                                <span class="provider-calendar-more">+{{ $dayEntries->count() - 3 }} more</span>
                            @endif

                            @if ($dayEntries->isEmpty() && $isToday)
                                <span class="provider-calendar-no-events">No appointments yet</span>
                            @endif
                        </span>
                    </button>
                @endif
            @endforeach
        </div>
        @elseif ($calendarView === 'today')
            @php
                $schedulerDate = $activeDate->toDateString();
                $schedulerDayEntries = $calendarEntriesByDate
                    ->get($schedulerDate, collect())
                    ->sortBy(fn ($entry) => sprintf('%05d-%s', $minutesFromTime($entry->start_time) ?? 99999, $entry->display_code))
                    ->values();

                $dayTimelineBounds = $schedulerDayEntries->map(function ($entry) use ($minutesFromTime) {
                    $start = $minutesFromTime($entry->start_time) ?? (7 * 60);
                    $estimatedEnd = $minutesFromTime($entry->estimated_end_time);
                    $durationEnd = $start + max(1, (int) ($entry->total_duration ?: 30));

                    return [
                        'start' => $start,
                        'end' => $estimatedEnd && $estimatedEnd > $start ? max($estimatedEnd, $durationEnd) : $durationEnd,
                    ];
                });
                $schedulerStartHour = max(0, min(7, (int) floor(($dayTimelineBounds->min('start') ?? (7 * 60)) / 60)));
                $schedulerEndHour = min(24, max(22, (int) ceil(($dayTimelineBounds->max('end') ?? (22 * 60)) / 60)));
                $schedulerTimeMarkers = range($schedulerStartHour * 60, $schedulerEndHour * 60, $schedulerIntervalMinutes);
                $schedulerTimelineHeight = ($schedulerEndHour - $schedulerStartHour) * $schedulerHourHeight;
                $schedulerHeight = $schedulerTimelineHeight + $schedulerTopPadding + $schedulerBottomPadding;
                $schedulerMarkers = collect($schedulerTimeMarkers)->map(function ($markerMinutes) use ($schedulerStartHour, $schedulerTopPadding, $schedulerHourHeight) {
                    $minute = $markerMinutes % 60;

                    return (object) [
                        'label' => sprintf('%02d:%02d', intdiv($markerMinutes, 60), $minute),
                        'top' => round($schedulerTopPadding + ((($markerMinutes - ($schedulerStartHour * 60)) / 60) * $schedulerHourHeight), 2),
                        'class' => $minute === 30 ? 'is-half-hour' : 'is-full-hour',
                    ];
                });

                $preparedDayEvents = $schedulerDayEntries->map(function ($entry) use ($minutesFromTime, $schedulerStartHour, $entryKey) {
                    $start = $minutesFromTime($entry->start_time) ?? ($schedulerStartHour * 60);
                    $parsedEnd = $minutesFromTime($entry->estimated_end_time);
                    $durationEnd = $start + max(1, (int) ($entry->total_duration ?: 30));
                    $end = $parsedEnd && $parsedEnd > $start
                        ? max($parsedEnd, $durationEnd)
                        : $durationEnd;

                    return [
                        'key' => $entryKey($entry),
                        'resource_key' => $entry->staff?->id ? 'staff-' . $entry->staff->id : 'unassigned',
                        'entry' => $entry,
                        'start' => $start,
                        'end' => $end,
                        'display_end' => sprintf('%02d:%02d', intdiv($end, 60), $end % 60),
                    ];
                })->sortBy('start')->values();

                $dayEventLayout = [];
                foreach ($preparedDayEvents->groupBy('resource_key') as $resourceEvents) {
                    $dayEventClusters = [];
                    $activeCluster = [];
                    $activeClusterEnd = null;

                    foreach ($resourceEvents as $preparedEvent) {
                        if ($activeCluster && $preparedEvent['start'] >= $activeClusterEnd) {
                            $dayEventClusters[] = $activeCluster;
                            $activeCluster = [];
                            $activeClusterEnd = null;
                        }

                        $activeCluster[] = $preparedEvent;
                        $activeClusterEnd = max($activeClusterEnd ?? $preparedEvent['end'], $preparedEvent['end']);
                    }

                    if ($activeCluster) {
                        $dayEventClusters[] = $activeCluster;
                    }

                    foreach ($dayEventClusters as $cluster) {
                        $laneEnds = [];
                        $clusterAssignments = [];

                        foreach ($cluster as $preparedEvent) {
                            $assignedLane = null;
                            foreach ($laneEnds as $lane => $laneEnd) {
                                if ($preparedEvent['start'] >= $laneEnd) {
                                    $assignedLane = $lane;
                                    break;
                                }
                            }

                            if ($assignedLane === null) {
                                $assignedLane = count($laneEnds);
                            }

                            $laneEnds[$assignedLane] = $preparedEvent['end'];
                            $clusterAssignments[$preparedEvent['key']] = $assignedLane;
                        }

                        $laneCount = max(1, count($laneEnds));
                        foreach ($clusterAssignments as $eventKey => $assignedLane) {
                            $dayEventLayout[$eventKey] = ['lane' => $assignedLane, 'lanes' => $laneCount];
                        }
                    }
                }
            @endphp
            <div class="provider-resource-scheduler is-day is-single-day is-staff-day" style="--resource-count: {{ $schedulerResourceCount }}; --scheduler-height: {{ $schedulerHeight }}px; --scheduler-top-padding: {{ $schedulerTopPadding }}px; --hour-height: {{ $schedulerHourHeight }}px; --half-hour-height: {{ $schedulerHourHeight / 2 }}px;">
                <div class="provider-resource-scheduler-scroll">
                    <div class="provider-resource-day-head">
                        <div class="provider-resource-corner">Time</div>
                        @foreach ($schedulerResources as $resource)
                            @php
                                $resourceEntries = $preparedDayEvents->where('resource_key', $resource->key);
                                $resourceRole = $resource->staff?->role;
                            @endphp
                            <div class="provider-resource-staff-head calendar-resource-tone-{{ $calendarResourceTones[$loop->index % count($calendarResourceTones)] }}">
                                <span>{{ $initial($resource->name, 'S') }}</span>
                                <strong>{{ $resource->name }}</strong>
                                <small>{{ $resourceRole ? $resourceRole . ' · ' : '' }}{{ $resourceEntries->count() }} {{ Str::plural('booking', $resourceEntries->count()) }}</small>
                            </div>
                        @endforeach
                    </div>

                    <div class="provider-resource-day-body">
                        <aside class="provider-resource-time-axis" aria-hidden="true">
                            @foreach ($schedulerMarkers as $marker)
                                <span class="{{ $marker->class }}" style="--time-top: {{ $marker->top }}px;">{{ $marker->label }}</span>
                            @endforeach
                        </aside>

                        <div class="provider-resource-day-grid">
                            @foreach ($schedulerMarkers as $marker)
                                <i class="provider-resource-grid-line {{ $marker->class }}" style="--time-top: {{ $marker->top }}px;"></i>
                            @endforeach
                            @foreach ($schedulerResources as $resource)
                            <div class="provider-resource-day-column calendar-resource-tone-{{ $calendarResourceTones[$loop->index % count($calendarResourceTones)] }}" data-staff-column="{{ $resource->key }}">
                                @foreach ($preparedDayEvents->where('resource_key', $resource->key) as $preparedEvent)
                                    @php
                                        $entry = $preparedEvent['entry'];
                                        $entryStatus = $entry->booking->status ?? 'pending';
                                        $entryDuration = max(1, $preparedEvent['end'] - $preparedEvent['start']);
                                        $eventTop = max($schedulerTopPadding, min($schedulerTopPadding + $schedulerTimelineHeight - 1, $schedulerTopPadding + ((($preparedEvent['start'] - ($schedulerStartHour * 60)) / 60) * $schedulerHourHeight)));
                                        $eventHeight = max(1, min(($schedulerTopPadding + $schedulerTimelineHeight) - $eventTop, ($entryDuration / 60) * $schedulerHourHeight));
                                        $eventLayout = $dayEventLayout[$preparedEvent['key']] ?? ['lane' => 0, 'lanes' => 1];
                                        $eventWidth = 100 / $eventLayout['lanes'];
                                        $eventLeft = $eventLayout['lane'] * $eventWidth;
                                    @endphp
                                    <button
                                        type="button"
                                        class="provider-resource-event {{ $statusClass($entryStatus) }} {{ $eventHeight < 34 ? 'is-compact' : '' }}"
                                        style="--event-top: {{ round($eventTop, 2) }}px; --event-height: {{ round($eventHeight, 2) }}px; --event-left: calc({{ round($eventLeft, 4) }}% + 6px); --event-width: calc({{ round($eventWidth, 4) }}% - 12px);"
                                        data-calendar-date="{{ $schedulerDate }}"
                                        data-calendar-entry="{{ $preparedEvent['key'] }}"
                                        title="{{ $formatTime($entry->start_time) }} - {{ $preparedEvent['display_end'] }} {{ $entry->customer_name }} - {{ $serviceNames($entry) }}"
                                        aria-label="Open {{ $entry->customer_name }} appointment details"
                                    >
                                        <strong>{{ $formatTime($entry->start_time) ?: 'Any time' }} - {{ $preparedEvent['display_end'] }} &middot; {{ $entry->customer_name }}</strong>
                                        <small>{{ $serviceNames($entry) }}</small>
                                    </button>
                                @endforeach
                            </div>
                            @endforeach

                        </div>
                    </div>
                </div>
            </div>
        @elseif ($calendarView === 'week')
            <div class="provider-resource-scheduler is-week" style="--week-columns: 7;">
                <div class="provider-resource-scheduler-scroll">
                    <div class="provider-resource-week-grid">
                        <div class="provider-resource-week-corner">Scheduled team</div>
                        @foreach ($agendaDays as $agendaDay)
                            <div class="provider-resource-week-day-head {{ $agendaDay->toDateString() === $todayDate ? 'is-today' : '' }}">
                                <span>{{ $agendaDay->day }}</span>
                                <strong>{{ $agendaDay->format('D') }}</strong>
                            </div>
                        @endforeach

                        @foreach ($schedulerResources as $resource)
                            <div class="provider-resource-week-staff calendar-resource-tone-{{ $calendarResourceTones[$loop->index % count($calendarResourceTones)] }}">
                                <span>{{ $initial($resource->name, 'S') }}</span>
                                <strong>{{ $resource->name }}</strong>
                            </div>

                            @foreach ($agendaDays as $agendaDay)
                                @php
                                    $agendaDate = $agendaDay->toDateString();
                                    $cellEntries = $calendarEntriesByDate
                                        ->get($agendaDate, collect())
                                        ->filter(fn ($entry) => $resource->staff ? (int) $entry->staff?->id === (int) $resource->staff->id : empty($entry->staff));
                                @endphp
                                <div class="provider-resource-week-cell {{ $agendaDate === $todayDate ? 'is-today' : '' }}">
                                    @foreach ($cellEntries as $entry)
                                        @php
                                            $entryStatus = $entry->booking->status ?? 'pending';
                                            $entryStartMinutes = $minutesFromTime($entry->start_time) ?? ($schedulerStartHour * 60);
                                            $weekEventTop = max(4, min(142, (($entryStartMinutes - ($schedulerStartHour * 60)) / (($schedulerEndHour - $schedulerStartHour) * 60)) * 150));
                                        @endphp
                                        <button
                                            type="button"
                                            class="provider-resource-week-event {{ $statusClass($entryStatus) }}"
                                            style="--week-event-top: {{ round($weekEventTop, 2) }}px;"
                                            data-calendar-date="{{ $agendaDate }}"
                                            data-calendar-entry="{{ $entryKey($entry) }}"
                                            title="{{ $formatTime($entry->start_time) }} {{ $entry->customer_name }} - {{ $serviceNames($entry) }}"
                                        >
                                            <strong>{{ $formatTime($entry->start_time) ?: '--:--' }} {{ Str::limit($entry->customer_name, 16) }}</strong>
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="provider-calendar-year-grid" aria-label="Calendar for {{ $activeDate->year }}">
                @foreach (range(1, 12) as $monthNumber)
                    @php
                        $yearMonth = Carbon::create($activeDate->year, $monthNumber, 1)->startOfDay();
                        $yearMonthEnd = $yearMonth->copy()->endOfMonth();
                        $miniStart = $yearMonth->copy()->startOfWeek(Carbon::MONDAY);
                        $miniEnd = $yearMonthEnd->copy()->endOfWeek(Carbon::SUNDAY);
                        $monthEntries = $calendarEntries->filter(fn ($entry) => $entry->booking_date->month === $monthNumber);
                        $miniDays = collect();
                        for ($miniDay = $miniStart->copy(); $miniDay->lte($miniEnd); $miniDay->addDay()) {
                            $miniDays->push($miniDay->copy());
                        }
                    @endphp
                    <section class="provider-calendar-year-month">
                        <header>
                            <a href="{{ provider_route('provider.calendar.index', ['view' => 'month', 'date' => $yearMonth->toDateString()]) }}">{{ $yearMonth->format('F') }}</a>
                            <span>{{ $monthEntries->count() }}</span>
                        </header>
                        <div class="provider-calendar-year-weekdays" aria-hidden="true">
                            @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $weekday)
                                <span>{{ $weekday }}</span>
                            @endforeach
                        </div>
                        <div class="provider-calendar-year-days">
                            @foreach ($miniDays as $miniDay)
                                @php
                                    $miniDate = $miniDay->toDateString();
                                    $miniCount = $calendarEntriesByDate->get($miniDate, collect())->count();
                                    $isMiniCurrentMonth = $miniDay->month === $monthNumber;
                                @endphp
                                @if ($isMiniCurrentMonth)
                                    <a
                                        href="{{ provider_route('provider.calendar.index', ['view' => 'month', 'date' => $miniDate]) }}"
                                        class="{{ $miniDate === $todayDate ? 'is-today' : '' }} {{ $miniCount > 0 ? 'has-appointments' : '' }}"
                                        title="{{ $miniDay->format('d F Y') }}: {{ $miniCount }} appointments"
                                    >{{ $miniDay->day }}</a>
                                @else
                                    <span aria-hidden="true"></span>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>

    @foreach ($detailDays as $calendarDay)
        @php
            $dayDate = $calendarDay->toDateString();
            $dayEntries = $calendarEntriesByDate->get($dayDate, collect());
            $groupedDayEntries = $dayEntries
                ->sortBy(fn ($entry) => sprintf('%s-%s', strtolower($staffName($entry)), $formatTime($entry->start_time) ?: '23:59'))
                ->groupBy(fn ($entry) => $entry->staff?->id ? 'staff-' . $entry->staff->id : 'unassigned');
        @endphp
        <div class="provider-calendar-modal" data-calendar-modal="{{ $dayDate }}" hidden>
            <div class="provider-calendar-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="calendarModalTitle{{ $dayDate }}">
                <header class="provider-calendar-modal-header">
                    <div class="provider-calendar-modal-date">
                        <span><strong>{{ $calendarDay->format('d') }}</strong><small>{{ strtoupper($calendarDay->format('M')) }}</small></span>
                        <div>
                            <p>{{ $calendarDay->format('l') }}</p>
                            <h3 id="calendarModalTitle{{ $dayDate }}">{{ $calendarDay->format('d F Y') }}</h3>
                            <small data-calendar-modal-count>{{ $dayEntries->count() }} {{ Str::plural('appointment', $dayEntries->count()) }}</small>
                        </div>
                    </div>
                    <button type="button" class="provider-calendar-modal-close" data-calendar-modal-close aria-label="Close appointment details">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                    </button>
                </header>

                <div class="provider-calendar-modal-body">
                    @forelse ($groupedDayEntries as $staffEntries)
                        @php
                            $firstEntry = $staffEntries->first();
                            $groupStaffName = $staffName($firstEntry);
                        @endphp
                        <section class="provider-calendar-staff-group" data-calendar-staff-group>
                            <header>
                                <span>{{ $initial($groupStaffName) }}</span>
                                <div><strong>{{ $groupStaffName }}</strong><small>{{ $staffEntries->count() }} {{ Str::plural('appointment', $staffEntries->count()) }}</small></div>
                                @if (empty($firstEntry->staff))<b>Assign staff</b>@endif
                            </header>

                            <div class="provider-calendar-modal-appointments">
                                @foreach ($staffEntries as $entry)
                                    @php
                                        $booking = $entry->booking;
                                        $bookingStatus = $booking->status ?? 'pending';
                                        $startTime = $formatTime($entry->start_time);
                                        $modalStartMinutes = $minutesFromTime($entry->start_time);
                                        $modalEstimatedEndMinutes = $minutesFromTime($entry->estimated_end_time);
                                        $modalDurationEndMinutes = $modalStartMinutes !== null
                                            ? $modalStartMinutes + max(1, (int) ($entry->total_duration ?: 30))
                                            : null;
                                        $modalEndMinutes = $modalEstimatedEndMinutes && $modalStartMinutes !== null && $modalEstimatedEndMinutes > $modalStartMinutes
                                            ? max($modalEstimatedEndMinutes, $modalDurationEndMinutes)
                                            : $modalDurationEndMinutes;
                                        $endTime = $modalEndMinutes !== null
                                            ? sprintf('%02d:%02d', intdiv($modalEndMinutes, 60), $modalEndMinutes % 60)
                                            : $formatTime($entry->estimated_end_time);
                                    @endphp
                                    <article class="provider-calendar-modal-appointment" data-calendar-entry-card="{{ $entryKey($entry) }}">
                                        <div class="provider-calendar-appointment-time">
                                            <strong>{{ $startTime ?: 'Any time' }}</strong>
                                            <span>{{ $endTime ? 'until ' . $endTime : ($entry->total_duration ? $entry->total_duration . ' min' : 'Flexible') }}</span>
                                        </div>
                                        <div class="provider-calendar-appointment-detail">
                                            <div class="provider-calendar-appointment-title">
                                                <span>{{ $initial($entry->customer_name, 'C') }}</span>
                                                <div>
                                                    <strong>{{ $entry->customer_name }}</strong>
                                                    <small>{{ $entry->display_code }}{{ $entry->is_group ? ' · ' . $entry->participant_label : '' }}</small>
                                                </div>
                                                <em class="admin-booking-status {{ $statusClass($bookingStatus) }}">{{ $statusLabel($bookingStatus) }}</em>
                                            </div>
                                            <div class="provider-calendar-appointment-services" aria-label="Booked services">
                                                @foreach ($entry->services as $service)
                                                    @php
                                                        $serviceDuration = (int) ($service->pivot?->estimated_duration ?: $service->estimated_duration ?: 0);
                                                    @endphp
                                                    <span><strong>{{ $service->title }}</strong>@if ($serviceDuration)<small>{{ $serviceDuration }} min</small>@endif</span>
                                                @endforeach
                                            </div>
                                            <div class="provider-calendar-appointment-meta">
                                                <span><svg viewBox="0 0 24 24" fill="none"><path d="M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1 1 18 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>{{ $booking->branch->branch_name ?? 'No location' }}</span>
                                                @if ($entry->customer_phone)
                                                    <span><svg viewBox="0 0 24 24" fill="none"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.4 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"></path></svg>{{ $entry->customer_phone }}</span>
                                                @endif
                                                <span><svg viewBox="0 0 24 24" fill="none"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>{{ $money($entry->total_price) }}</span>
                                            </div>

                                            <dl class="provider-calendar-customer-fields">
                                                <div><dt>Staff</dt><dd>{{ $staffName($entry) }}</dd></div>
                                                <div><dt>Duration</dt><dd>{{ $entry->total_duration ?: '—' }}{{ $entry->total_duration ? ' min' : '' }}</dd></div>
                                                <div><dt>Booking type</dt><dd>{{ $statusLabel($booking->booking_type) }}</dd></div>
                                                <div><dt>Gender</dt><dd>{{ $entry->customer_gender ? ucfirst($entry->customer_gender) : 'Not provided' }}</dd></div>
                                                <div><dt>Age group</dt><dd>{{ $entry->participant_age_group ? ucfirst($entry->participant_age_group) : 'Not provided' }}</dd></div>
                                                <div><dt>Email</dt><dd>{{ $entry->customer_email ?: 'Not provided' }}</dd></div>
                                                <div><dt>Date of birth</dt><dd>{{ $entry->customer_date_of_birth ? Carbon::parse($entry->customer_date_of_birth)->format('d M Y') : 'Not provided' }}</dd></div>
                                                <div><dt>Religion</dt><dd>{{ $entry->customer_religion ?: 'Not provided' }}</dd></div>
                                                <div><dt>Allergies</dt><dd class="{{ $entry->customer_allergies ? 'has-alert' : '' }}">{{ $entry->customer_allergies ?: 'None provided' }}</dd></div>
                                                @if ($entry->customer_address)
                                                    <div class="is-wide"><dt>Address</dt><dd>{{ $entry->customer_address }}</dd></div>
                                                @endif
                                                <div><dt>Payment</dt><dd>{{ $statusLabel($booking->payment?->payment_type ?: 'unpaid') }} · {{ $statusLabel($booking->payment?->status ?: 'unpaid') }}</dd></div>
                                                <div><dt>Booking total</dt><dd>{{ $money($booking->total_price) }}</dd></div>
                                            </dl>

                                            @if ($entry->participant_description)
                                                <div class="provider-calendar-appointment-note"><strong>Customer / participant information</strong><p>{{ $entry->participant_description }}</p></div>
                                            @endif
                                            @if ($booking->notes)
                                                <div class="provider-calendar-appointment-note"><strong>Booking note</strong><p>{{ $booking->notes }}</p></div>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <div class="provider-calendar-modal-empty">
                            <span><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M8 2v4M16 2v4M3 10h18M9 15h6"></path></svg></span>
                            <strong>No appointments on this date</strong>
                            <p>The day is clear. New bookings will appear here automatically.</p>
                        </div>
                    @endforelse
                </div>

                <footer class="provider-calendar-modal-footer">
                    <button type="button" data-calendar-modal-close>Close</button>
                    <a href="{{ provider_route('provider.bookings.index', ['date_from' => $dayDate, 'date_to' => $dayDate]) }}">Open booking list <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg></a>
                </footer>
            </div>
        </div>
    @endforeach

    <script>
        (function initializeAppointmentCalendar() {
            const calendar = document.querySelector('[data-appointment-calendar]');
            if (!calendar || calendar.dataset.calendarReady === 'true') return;

            calendar.dataset.calendarReady = 'true';
            let activeModal = null;
            let activeTrigger = null;

            const filterModalAppointments = (modal, entryKey = '') => {
                const cards = [...modal.querySelectorAll('[data-calendar-entry-card]')];
                const countLabel = modal.querySelector('[data-calendar-modal-count]');
                const singleAppointment = Boolean(entryKey);

                modal.classList.toggle('is-single-appointment', singleAppointment);

                cards.forEach((card) => {
                    const shouldHide = singleAppointment && card.dataset.calendarEntryCard !== entryKey;
                    card.hidden = shouldHide;
                    card.style.display = shouldHide ? 'none' : '';
                });

                modal.querySelectorAll('[data-calendar-staff-group]').forEach((group) => {
                    const hasSelectedEntry = [...group.querySelectorAll('[data-calendar-entry-card]')]
                        .some((card) => card.dataset.calendarEntryCard === entryKey);
                    const shouldHide = singleAppointment && !hasSelectedEntry;
                    group.hidden = shouldHide;
                    group.style.display = shouldHide ? 'none' : '';
                });

                if (countLabel) {
                    if (!countLabel.dataset.fullCount) {
                        countLabel.dataset.fullCount = countLabel.textContent.trim();
                    }
                    countLabel.textContent = singleAppointment ? 'Appointment detail' : countLabel.dataset.fullCount;
                }

                const modalBody = modal.querySelector('.provider-calendar-modal-body');
                if (modalBody) modalBody.scrollTop = 0;
            };

            const closeModal = () => {
                if (!activeModal) return;
                const modalToClose = activeModal;
                modalToClose.classList.remove('is-open');
                document.body.classList.remove('provider-calendar-modal-open');
                activeModal = null;
                window.setTimeout(() => {
                    modalToClose.hidden = true;
                    filterModalAppointments(modalToClose);
                    if (activeTrigger) activeTrigger.focus();
                    activeTrigger = null;
                }, 180);
            };

            calendar.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-calendar-date]');
                if (trigger) {
                    const modal = calendar.querySelector(`[data-calendar-modal="${trigger.dataset.calendarDate}"]`);
                    if (!modal) return;

                    activeTrigger = trigger;
                    activeModal = modal;
                    filterModalAppointments(modal, trigger.dataset.calendarEntry || '');
                    modal.hidden = false;
                    document.body.classList.add('provider-calendar-modal-open');
                    window.requestAnimationFrame(() => {
                        modal.classList.add('is-open');
                        modal.querySelector('[data-calendar-modal-close]')?.focus();
                    });
                    return;
                }

                if (event.target.closest('[data-calendar-modal-close]') || (activeModal && event.target === activeModal)) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && activeModal) closeModal();
            });
        })();
    </script>
</section>
@endsection
