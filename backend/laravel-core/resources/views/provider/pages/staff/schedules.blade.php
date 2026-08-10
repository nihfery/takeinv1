@extends('provider.layouts.dashboard')

@section('title', 'Staff Schedule - Provider Dashboard')
@section('page_title', 'Staff Schedule')
@section('page_subtitle', 'Set staff working days and operating hours for booking availability.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/staff-schedules.css') }}">
@endpush

@section('content')
@php
    use Illuminate\Support\Str;

    $days = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    $dayShortLabels = [
        'monday' => 'Mon',
        'tuesday' => 'Tue',
        'wednesday' => 'Wed',
        'thursday' => 'Thu',
        'friday' => 'Fri',
        'saturday' => 'Sat',
        'sunday' => 'Sun',
    ];

    $staffCollection = $staffs ?? collect();
    $totalStaff = $staffCollection->count();
    $scheduledStaff = $staffCollection->filter(fn ($staff) => $staff->schedules->isNotEmpty())->count();
    $emptyStaff = max($totalStaff - $scheduledStaff, 0);
    $totalScheduleDays = $staffCollection->sum(fn ($staff) => $staff->schedules->count());

    $branchOptions = $staffCollection
        ->map(fn ($staff) => $staff->branch)
        ->filter()
        ->unique('id')
        ->sortBy('branch_name')
        ->values();

    $staffNameFor = fn ($staff) => $staff->full_name ?: trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')) ?: ($staff->email ?? 'Staff');
    $staffInitial = fn ($staff, $name) => strtoupper(substr((string) ($name ?: $staff->email ?: 'S'), 0, 1));

    $staffImageUrl = function ($staff) {
        $image = $staff->image ?? null;

        if (empty($image)) {
            return null;
        }

        $image = ltrim((string) $image, '/');

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        if (Str::startsWith($image, 'storage/')) {
            return asset($image);
        }

        return asset('storage/' . $image);
    };

    $statusLabel = fn ($value) => ucfirst($value ?: 'active');
    $statusClass = fn ($value) => ($value ?? 'active') === 'active' ? 'success' : 'danger';
    $formatTime = fn ($value, $fallback = '-') => $value ? substr((string) $value, 0, 5) : $fallback;
    $dayLabel = fn ($value) => $days[strtolower((string) $value)] ?? ucfirst((string) $value);

    $scheduleSummary = function ($dayKeys) use ($dayShortLabels) {
        $labels = collect($dayKeys)
            ->map(fn ($day) => $dayShortLabels[strtolower((string) $day)] ?? ucfirst((string) $day))
            ->values();

        if ($labels->isEmpty()) {
            return 'No schedule yet';
        }

        if ($labels->count() > 4) {
            return $labels->take(4)->join(', ') . ' +' . ($labels->count() - 4);
        }

        return $labels->join(', ');
    };
@endphp

<section class="admin-category-page admin-booking-page provider-staff-schedule-page" data-staff-schedule-page>
    <div class="admin-booking-route admin-category-route provider-staff-schedule-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <a href="{{ provider_route('provider.staffs.index') }}">Staff</a>
            <span>&rsaquo;</span>
            <strong>Staff Schedule</strong>
        </div>

        <div class="provider-staff-schedule-actions provider-staff-schedule-actions-desktop">
            <a href="{{ provider_route('provider.staffs.index') }}" class="admin-category-add-button provider-staff-schedule-action secondary">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Manage Staff
            </a>

            <a href="{{ provider_route('provider.staff.skills') }}" class="admin-category-add-button provider-staff-schedule-action">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M20 7 9 18l-5-5"></path>
                    <path d="M15 7h5v5"></path>
                </svg>
                Staff Skills
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="admin-booking-alert success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="admin-booking-alert danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="admin-booking-summary-grid provider-staff-schedule-summary">
        <div class="admin-booking-summary-card pink">
            <span>Total Staff</span>
            <strong>{{ number_format($totalStaff) }}</strong>
            <small>Staff within provider access</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Scheduled</span>
            <strong>{{ number_format($scheduledStaff) }}</strong>
            <small>Staff with at least 1 working day</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Total Active Days</span>
            <strong>{{ number_format($totalScheduleDays) }}</strong>
            <small>Accumulated working days across all staff</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>No Schedule Yet</span>
            <strong>{{ number_format($emptyStaff) }}</strong>
            <small>Required before fixed-time bookings</small>
        </div>
    </div>

    <div class="admin-booking-card category-card provider-staff-schedule-card">
        <div class="admin-booking-tabs provider-staff-schedule-tabs" role="tablist" aria-label="Filter staff schedule">
            <button type="button" class="admin-booking-tab active" data-schedule-tab="all">
                All Staff
                <span>{{ number_format($totalStaff) }}</span>
            </button>
            <button type="button" class="admin-booking-tab" data-schedule-tab="scheduled">
                Scheduled
                <span>{{ number_format($scheduledStaff) }}</span>
            </button>
            <button type="button" class="admin-booking-tab" data-schedule-tab="empty">
                No Schedule Yet
                <span>{{ number_format($emptyStaff) }}</span>
            </button>
        </div>

        <div class="admin-booking-filter-panel compact provider-staff-schedule-filter-panel">
            <div class="admin-booking-filter-row provider-staff-schedule-filter-row" id="staffScheduleFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="staffScheduleSearchInput"
                               type="text"
                               autocomplete="off"
                               placeholder="Search staff or branch">
                    </div>
                </label>

                <button type="button" class="admin-booking-mobile-search-submit" data-staff-schedule-search aria-label="Search staff schedule">
                    Search
                </button>

                <button type="button"
                        class="admin-booking-mobile-filter-toggle"
                        data-staff-schedule-filter-toggle
                        aria-controls="staffScheduleFilterRow"
                        aria-expanded="false">
                    Filter
                </button>

                <label class="admin-booking-field mini">
                    <select id="staffScheduleBranchFilter" aria-label="Branch" title="Branch">
                        <option value="all">All Branch</option>
                        @foreach ($branchOptions as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->branch_name }}</option>
                        @endforeach
                        <option value="none">No Branch Yet</option>
                    </select>
                </label>

                <label class="admin-booking-field mini">
                    <select id="staffScheduleStatusFilter" aria-label="Schedule status" title="Schedule status">
                        <option value="all">All Schedule</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="empty">No Schedule Yet</option>
                    </select>
                </label>

                <div class="admin-booking-filter-buttons">
                    <button type="button" data-staff-schedule-apply>Filter</button>
                    <a href="#" data-staff-schedule-reset>Reset</a>
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count" id="staffScheduleFilterCount">{{ number_format($totalStaff) }} staff</span>
                <span>{{ number_format($branchOptions->count()) }} branch</span>
                <span>{{ number_format($totalScheduleDays) }} active days</span>
                <span>{{ number_format($scheduledStaff) }} scheduled</span>
            </div>
        </div>

        <div class="admin-category-add-row provider-staff-schedule-actions provider-staff-schedule-actions-mobile">
            <a href="{{ provider_route('provider.staffs.index') }}" class="admin-category-add-button provider-staff-schedule-action secondary">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Manage Staff
            </a>

            <a href="{{ provider_route('provider.staff.skills') }}" class="admin-category-add-button provider-staff-schedule-action">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M20 7 9 18l-5-5"></path>
                    <path d="M15 7h5v5"></path>
                </svg>
                Staff Skills
            </a>
        </div>

        <div class="admin-category-mobile-list admin-booking-mobile-list provider-staff-schedule-mobile-list" id="staffScheduleMobileList">
            @forelse ($staffCollection as $staff)
                @php
                    $staffName = $staffNameFor($staff);
                    $initial = $staffInitial($staff, $staffName);
                    $imageUrl = $staffImageUrl($staff);
                    $branchName = $staff->branch->branch_name ?? 'No branch yet';
                    $branchFilterValue = $staff->branch_id ? (string) $staff->branch_id : 'none';
                    $status = $staff->status ?? 'active';
                    $currentDays = $staff->schedules->pluck('day_of_week')->map(fn ($day) => strtolower((string) $day))->all();
                    $firstSchedule = $staff->schedules->first();
                    $isOldTarget = (string) old('staff_id') === (string) $staff->id;
                    $selectedDays = collect($isOldTarget ? old('days', $currentDays) : $currentDays)->map(fn ($day) => strtolower((string) $day))->all();
                    $startTime = $isOldTarget ? old('start_time', $formatTime($firstSchedule?->start_time, '09:00')) : $formatTime($firstSchedule?->start_time, '09:00');
                    $endTime = $isOldTarget ? old('end_time', $formatTime($firstSchedule?->end_time, '18:00')) : $formatTime($firstSchedule?->end_time, '18:00');
                    $selectedCount = count($selectedDays);
                    $coverage = (int) round(($selectedCount / 7) * 100);
                    $scheduleState = $selectedCount > 0 ? 'scheduled' : 'empty';
                    $summaryText = $scheduleSummary($selectedDays);
                    $searchText = Str::lower(collect([
                        $staffName,
                        $staff->email,
                        $staff->username,
                        $branchName,
                        $status,
                        $summaryText,
                        $startTime,
                        $endTime,
                    ])->filter()->implode(' '));
                    $mobileFormId = 'staff-schedule-form-mobile-' . $staff->id;
                @endphp

                <article class="admin-category-mobile-card admin-booking-mobile-card provider-staff-schedule-mobile-card"
                         data-staff-schedule-card
                         data-search="{{ $searchText }}"
                         data-branch="{{ $branchFilterValue }}"
                         data-status="{{ $scheduleState }}">
                    <header class="admin-category-mobile-head">
                        <div class="admin-category-mobile-title">
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $staffName }}">
                            @else
                                <span>{{ $initial }}</span>
                            @endif

                            <div>
                                <strong>{{ $staffName }}</strong>
                                <span>{{ $staff->email ?? 'No email' }}</span>
                            </div>
                        </div>

                        <b data-schedule-count-label>{{ $selectedCount }}/7</b>
                    </header>

                    <div class="admin-category-mobile-main admin-booking-mobile-main provider-staff-schedule-mobile-main">
                        <div>
                            <span>Branch</span>
                            <strong>{{ Str::limit($branchName, 28) }}</strong>
                        </div>

                        <div>
                            <span>Status</span>
                            <strong>{{ $statusLabel($status) }}</strong>
                        </div>

                        <div>
                            <span>Days</span>
                            <strong data-schedule-summary>{{ $summaryText }}</strong>
                        </div>

                        <div>
                            <span>Hours</span>
                            <strong data-schedule-time-label>{{ $startTime }} - {{ $endTime }}</strong>
                        </div>
                    </div>

                    <form class="provider-staff-schedule-form"
                          id="{{ $mobileFormId }}"
                          method="POST"
                          action="{{ provider_route('provider.staff.schedules.update') }}"
                          data-schedule-form>
                        @csrf
                        <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                        <div class="provider-staff-schedule-progress" aria-hidden="true">
                            <span style="width: {{ $coverage }}%" data-schedule-progress-bar></span>
                        </div>

                        <div class="provider-staff-schedule-day-grid">
                            @foreach ($days as $key => $label)
                                @php
                                    $inputId = 'schedule-mobile-' . $staff->id . '-' . $key;
                                    $checked = in_array($key, $selectedDays, true);
                                @endphp

                                <label class="provider-staff-schedule-day {{ $checked ? 'is-checked' : '' }}" for="{{ $inputId }}">
                                    <input
                                        id="{{ $inputId }}"
                                        type="checkbox"
                                        name="days[]"
                                        value="{{ $key }}"
                                        data-schedule-day
                                        @checked($checked)
                                    >
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="provider-staff-schedule-time-grid">
                            <label>
                                <span>Start</span>
                                <input type="time" name="start_time" value="{{ $startTime }}" data-schedule-start required>
                            </label>
                            <label>
                                <span>End</span>
                                <input type="time" name="end_time" value="{{ $endTime }}" data-schedule-end required>
                            </label>
                        </div>

                        <footer class="provider-staff-schedule-form-actions">
                            <span class="admin-booking-status {{ $scheduleState === 'scheduled' ? 'success' : 'warning' }}" data-schedule-status-badge>
                                {{ $scheduleState === 'scheduled' ? 'Scheduled' : 'No schedule yet' }}
                            </span>

                            <div>
                                <button type="button" class="provider-staff-schedule-mini-btn text" data-schedule-preset="weekdays" title="Select Monday to Friday" aria-label="Select Monday to Friday">
                                    Weekdays
                                </button>
                                <button type="button" class="provider-staff-schedule-mini-btn text" data-schedule-preset="weekend" title="Select Saturday and Sunday" aria-label="Select Saturday and Sunday">
                                    Weekend
                                </button>
                                <button type="button" class="provider-staff-schedule-mini-btn text" data-schedule-preset="all" title="Select all days" aria-label="Select all days">
                                    All
                                </button>
                                <button type="button" class="provider-staff-schedule-mini-btn" data-schedule-preset="clear" title="Clear days" aria-label="Clear days">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M18 6 6 18"></path>
                                        <path d="m6 6 12 12"></path>
                                    </svg>
                                </button>
                                <button type="submit" class="provider-staff-schedule-save" title="Save schedule" aria-label="Save schedule">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"></path>
                                        <path d="M17 21v-8H7v8"></path>
                                        <path d="M7 3v5h8"></path>
                                    </svg>
                                </button>
                            </div>
                        </footer>
                    </form>
                </article>
            @empty
                <div class="admin-category-mobile-empty admin-booking-mobile-empty provider-staff-schedule-mobile-empty">
                    <strong>No staff yet.</strong>
                    <p>Add staff first before setting schedules.</p>
                </div>
            @endforelse

            @if ($staffCollection->isNotEmpty())
                <div class="admin-category-mobile-empty admin-booking-mobile-empty provider-staff-schedule-mobile-empty" data-staff-schedule-mobile-empty hidden>
                    <strong>Staff not found.</strong>
                    <p>Try changing the keyword, branch, or schedule status.</p>
                </div>
            @endif
        </div>

        <div class="admin-booking-table-wrap category-table-wrap provider-staff-schedule-table-wrap" data-setup-schedule-list>
            <table class="admin-booking-table detailed category-table provider-staff-schedule-table">
                <colgroup>
                    <col class="provider-staff-schedule-col-staff">
                    <col class="provider-staff-schedule-col-branch">
                    <col class="provider-staff-schedule-col-days">
                    <col class="provider-staff-schedule-col-time">
                    <col class="provider-staff-schedule-col-action">
                </colgroup>
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Branch</th>
                        <th>Working Days</th>
                        <th>Working Hours</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($staffCollection as $staff)
                        @php
                            $staffName = $staffNameFor($staff);
                            $initial = $staffInitial($staff, $staffName);
                            $imageUrl = $staffImageUrl($staff);
                            $branchName = $staff->branch->branch_name ?? 'No branch yet';
                            $branchFilterValue = $staff->branch_id ? (string) $staff->branch_id : 'none';
                            $status = $staff->status ?? 'active';
                            $currentDays = $staff->schedules->pluck('day_of_week')->map(fn ($day) => strtolower((string) $day))->all();
                            $firstSchedule = $staff->schedules->first();
                            $isOldTarget = (string) old('staff_id') === (string) $staff->id;
                            $selectedDays = collect($isOldTarget ? old('days', $currentDays) : $currentDays)->map(fn ($day) => strtolower((string) $day))->all();
                            $startTime = $isOldTarget ? old('start_time', $formatTime($firstSchedule?->start_time, '09:00')) : $formatTime($firstSchedule?->start_time, '09:00');
                            $endTime = $isOldTarget ? old('end_time', $formatTime($firstSchedule?->end_time, '18:00')) : $formatTime($firstSchedule?->end_time, '18:00');
                            $selectedCount = count($selectedDays);
                            $coverage = (int) round(($selectedCount / 7) * 100);
                            $scheduleState = $selectedCount > 0 ? 'scheduled' : 'empty';
                            $summaryText = $scheduleSummary($selectedDays);
                            $searchText = Str::lower(collect([
                                $staffName,
                                $staff->email,
                                $staff->username,
                                $branchName,
                                $status,
                                $summaryText,
                                $startTime,
                                $endTime,
                            ])->filter()->implode(' '));
                            $desktopFormId = 'staff-schedule-form-desktop-' . $staff->id;
                        @endphp

                        <tr data-staff-schedule-row
                            data-search="{{ $searchText }}"
                            data-branch="{{ $branchFilterValue }}"
                            data-status="{{ $scheduleState }}">
                            <td>
                                <div class="category-name-box provider-staff-schedule-name-box">
                                    @if ($imageUrl)
                                        <img class="category-thumb" src="{{ $imageUrl }}" alt="{{ $staffName }}">
                                    @else
                                        <span class="category-thumb-placeholder">{{ $initial }}</span>
                                    @endif

                                    <div class="category-name-text">
                                        <strong>{{ $staffName }}</strong>
                                        <small>{{ $staff->email ?? 'No email' }}</small>
                                        <small>ID #{{ $staff->id }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date provider-staff-schedule-branch">
                                    <strong>{{ $branchName }}</strong>
                                    <small>{{ $staff->username ?: 'Username -' }}</small>
                                    <span class="admin-booking-status {{ $statusClass($status) }}">{{ $statusLabel($status) }}</span>
                                </div>
                            </td>

                            <td>
                                <form class="provider-staff-schedule-form"
                                      id="{{ $desktopFormId }}"
                                      method="POST"
                                      action="{{ provider_route('provider.staff.schedules.update') }}"
                                      data-schedule-form>
                                    @csrf
                                    <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                                    <div class="provider-staff-schedule-day-head">
                                        <div>
                                            <strong data-schedule-count-label>{{ $selectedCount }}/7</strong>
                                            <small data-schedule-summary>{{ $summaryText }}</small>
                                        </div>

                                        <div class="provider-staff-schedule-day-tools">
                                            <button type="button" class="provider-staff-schedule-mini-btn text" data-schedule-preset="weekdays" title="Select Monday to Friday" aria-label="Select Monday to Friday">Weekdays</button>
                                            <button type="button" class="provider-staff-schedule-mini-btn text" data-schedule-preset="weekend" title="Select Saturday and Sunday" aria-label="Select Saturday and Sunday">Weekend</button>
                                            <button type="button" class="provider-staff-schedule-mini-btn text" data-schedule-preset="all" title="Select all days" aria-label="Select all days">All</button>
                                            <button type="button" class="provider-staff-schedule-mini-btn" data-schedule-preset="clear" title="Clear days" aria-label="Clear days">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M18 6 6 18"></path>
                                                    <path d="m6 6 12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="provider-staff-schedule-progress" aria-hidden="true">
                                        <span style="width: {{ $coverage }}%" data-schedule-progress-bar></span>
                                    </div>

                                    <div class="provider-staff-schedule-day-grid">
                                        @foreach ($days as $key => $label)
                                            @php
                                                $inputId = 'schedule-desktop-' . $staff->id . '-' . $key;
                                                $checked = in_array($key, $selectedDays, true);
                                            @endphp

                                            <label class="provider-staff-schedule-day {{ $checked ? 'is-checked' : '' }}" for="{{ $inputId }}">
                                                <input
                                                    id="{{ $inputId }}"
                                                    type="checkbox"
                                                    name="days[]"
                                                    value="{{ $key }}"
                                                    data-schedule-day
                                                    @checked($checked)
                                                >
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </form>
                            </td>

                            <td>
                                <div class="provider-staff-schedule-time-cell">
                                    <div class="provider-staff-schedule-time-grid">
                                        <label>
                                            <span>Start</span>
                                            <input type="time" name="start_time" value="{{ $startTime }}" form="{{ $desktopFormId }}" data-schedule-start required>
                                        </label>
                                        <label>
                                            <span>End</span>
                                            <input type="time" name="end_time" value="{{ $endTime }}" form="{{ $desktopFormId }}" data-schedule-end required>
                                        </label>
                                    </div>

                                    <span class="admin-booking-status {{ $scheduleState === 'scheduled' ? 'success' : 'warning' }}" data-schedule-status-badge>
                                        {{ $scheduleState === 'scheduled' ? 'Scheduled' : 'No schedule yet' }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="provider-staff-schedule-row-actions">
                                    <button type="submit" class="provider-staff-schedule-save" form="{{ $desktopFormId }}" title="Save schedule" aria-label="Save schedule">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"></path>
                                            <path d="M17 21v-8H7v8"></path>
                                            <path d="M7 3v5h8"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M8 2v4M16 2v4"></path>
                                            <path d="M3 10h18"></path>
                                            <path d="M5 5h14v16H5z"></path>
                                            <path d="M8 14h4"></path>
                                        </svg>
                                    </span>

                                    <strong>No staff yet.</strong>
                                    <p>Add staff first before setting schedules.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse

                    @if ($staffCollection->isNotEmpty())
                        <tr data-staff-schedule-empty hidden>
                            <td colspan="5" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <circle cx="11" cy="11" r="7"></circle>
                                            <path d="m21 21-4.3-4.3"></path>
                                        </svg>
                                    </span>

                                    <strong>Staff not found.</strong>
                                    <p>Try changing the keyword, branch, or schedule status.</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer category-footer provider-staff-schedule-footer">
            <p class="admin-booking-showing">
                <strong id="staffScheduleShowing">{{ number_format($totalStaff) }}</strong>
                <span>/ {{ number_format($totalStaff) }} staff</span>
            </p>

            <div class="admin-booking-pagination category-pagination static">
                <span class="active">1</span>
            </div>
        </div>
    </div>
</section>

<script src="{{ asset('provider/js/staff-schedules.js') }}"></script>
@endsection


