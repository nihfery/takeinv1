@extends('provider.layouts.dashboard')

@section('title', 'Staff Skills - Provider Dashboard')
@section('page_title', 'Staff Skills')
@section('page_subtitle', 'Manage staff skills based on active services available in each branch.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/staff-skills.css') }}?v=staff-skills-balanced-3">
@endpush

@section('content')
@php
    use Illuminate\Support\Str;

    $staffCollection = $staffs ?? collect();
    $serviceCollection = $services ?? collect();

    $totalStaff = $staffCollection->count();
    $configuredStaff = $staffCollection->filter(fn ($staff) => $staff->skills->isNotEmpty())->count();
    $emptyStaff = max($totalStaff - $configuredStaff, 0);
    $totalSkillLinks = $staffCollection->sum(fn ($staff) => $staff->skills->count());
    $totalServices = $serviceCollection->count();

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
    $serviceCategoryFor = fn ($service) => $service->serviceCategory->name ?? $service->category ?? 'Uncategorized';

    $formatDuration = function ($service) {
        $minimum = (int) ($service->minimum_duration ?? 0);
        $estimated = (int) ($service->estimated_duration ?? 0);
        $maximum = (int) ($service->maximum_duration ?? 0);

        if ($minimum > 0 && $maximum > 0 && $minimum !== $maximum) {
            return "{$minimum}-{$maximum} min";
        }

        if ($estimated > 0) {
            return "{$estimated} min";
        }

        if ($minimum > 0) {
            return "{$minimum} min";
        }

        return 'Duration -';
    };

    $branchCoverageFor = function ($service) {
        $branchIds = collect($service->branch_ids ?? [])->filter()->values();

        if ($branchIds->isEmpty()) {
            return 'All branch';
        }

        return $branchIds->count() . ' branch';
    };

    $availableServicesFor = function ($staff) use ($serviceCollection) {
        return $serviceCollection->filter(function ($service) use ($staff) {
            if (! $staff->branch_id || empty($service->branch_ids)) {
                return true;
            }

            return in_array((int) $staff->branch_id, array_map('intval', (array) $service->branch_ids), true);
        })->values();
    };
@endphp

<section class="admin-category-page admin-booking-page provider-staff-skill-page" data-staff-skill-page>
    <div class="admin-booking-route admin-category-route provider-staff-skill-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <a href="{{ provider_route('provider.staffs.index') }}">Staff</a>
            <span>&rsaquo;</span>
            <strong>Staff Skills</strong>
        </div>

        <div class="provider-staff-skill-actions provider-staff-skill-actions-desktop">
            <a href="{{ provider_route('provider.staffs.index') }}" class="admin-category-add-button provider-staff-skill-action secondary">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Manage Staff
            </a>

            <a href="{{ provider_route('provider.staff.schedules') }}" class="admin-category-add-button provider-staff-skill-action">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M8 2v4M16 2v4"></path>
                    <path d="M3 10h18"></path>
                    <path d="M5 5h14v16H5z"></path>
                    <path d="M8 14h4"></path>
                </svg>
                Staff Schedule
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

    <div class="admin-booking-summary-grid provider-staff-skill-summary">
        <div class="admin-booking-summary-card pink">
            <span>Total Staff</span>
            <strong>{{ number_format($totalStaff) }}</strong>
            <small>Staff within provider access</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Configured</span>
            <strong>{{ number_format($configuredStaff) }}</strong>
            <small>Staff with at least 1 skill</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Active Skills</span>
            <strong>{{ number_format($totalSkillLinks) }}</strong>
            <small>Total staff and service pairings</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Active Services</span>
            <strong>{{ number_format($totalServices) }}</strong>
            <small>Services available for mapping</small>
        </div>
    </div>

    <div class="admin-booking-card category-card provider-staff-skill-card">
        <div class="admin-booking-tabs provider-staff-skill-tabs" role="tablist" aria-label="Filter skill staff">
            <button type="button" class="admin-booking-tab active" data-skill-tab="all">
                All Staff
                <span>{{ number_format($totalStaff) }}</span>
            </button>
            <button type="button" class="admin-booking-tab" data-skill-tab="configured">
                Configured
                <span>{{ number_format($configuredStaff) }}</span>
            </button>
            <button type="button" class="admin-booking-tab" data-skill-tab="empty">
                Not Configured
                <span>{{ number_format($emptyStaff) }}</span>
            </button>
        </div>

        <div class="admin-booking-filter-panel compact provider-staff-skill-filter-panel">
            <div class="admin-booking-filter-row provider-staff-skill-filter-row" id="staffSkillFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="staffSkillSearchInput"
                               type="text"
                               autocomplete="off"
                               placeholder="Search staff or service">
                    </div>
                </label>

                <button type="button" class="admin-booking-mobile-search-submit" data-staff-skill-search aria-label="Search skill staff">
                    Search
                </button>

                <button type="button"
                        class="admin-booking-mobile-filter-toggle"
                        data-staff-skill-filter-toggle
                        aria-controls="staffSkillFilterRow"
                        aria-expanded="false">
                    Filter
                </button>

                <label class="admin-booking-field mini">
                    <select id="staffSkillBranchFilter" aria-label="Branch" title="Branch">
                        <option value="all">All Branch</option>
                        @foreach ($branchOptions as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->branch_name }}</option>
                        @endforeach
                        <option value="none">No Branch Yet</option>
                    </select>
                </label>

                <label class="admin-booking-field mini">
                    <select id="staffSkillCoverageFilter" aria-label="Coverage" title="Coverage">
                        <option value="all">All Coverage</option>
                        <option value="configured">Configured</option>
                        <option value="empty">Not Configured</option>
                    </select>
                </label>

                <div class="admin-booking-filter-buttons">
                    <button type="button" data-staff-skill-apply>Filter</button>
                    <a href="#" data-staff-skill-reset>Reset</a>
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count" id="staffSkillFilterCount">{{ number_format($totalStaff) }} staff</span>
                <span>{{ number_format($branchOptions->count()) }} branch</span>
                <span>{{ number_format($totalSkillLinks) }} active skills</span>
                <span>{{ number_format($totalServices) }} active services</span>
            </div>
        </div>

        <div class="admin-category-add-row provider-staff-skill-actions provider-staff-skill-actions-mobile">
            <a href="{{ provider_route('provider.staffs.index') }}" class="admin-category-add-button provider-staff-skill-action secondary">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Manage Staff
            </a>

            <a href="{{ provider_route('provider.staff.schedules') }}" class="admin-category-add-button provider-staff-skill-action">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M8 2v4M16 2v4"></path>
                    <path d="M3 10h18"></path>
                    <path d="M5 5h14v16H5z"></path>
                    <path d="M8 14h4"></path>
                </svg>
                Staff Schedule
            </a>
        </div>

        <div class="admin-category-mobile-list admin-booking-mobile-list provider-staff-skill-mobile-list" id="staffSkillMobileList">
            @forelse ($staffCollection as $staff)
                @php
                    $staffName = $staffNameFor($staff);
                    $initial = $staffInitial($staff, $staffName);
                    $imageUrl = $staffImageUrl($staff);
                    $branchName = $staff->branch->branch_name ?? 'No branch yet';
                    $branchFilterValue = $staff->branch_id ? (string) $staff->branch_id : 'none';
                    $status = $staff->status ?? 'active';
                    $selectedSkillIds = collect(old('skills.' . $staff->id, $staff->skills->pluck('id')->all()))
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $availableServices = $availableServicesFor($staff);
                    $availableIds = $availableServices->pluck('id')->map(fn ($id) => (string) $id)->all();
                    $selectedAvailableCount = collect($selectedSkillIds)->intersect($availableIds)->count();
                    $availableCount = $availableServices->count();
                    $coverage = $availableCount > 0 ? (int) round(($selectedAvailableCount / $availableCount) * 100) : 0;
                    $coverageState = $selectedAvailableCount > 0 ? 'configured' : 'empty';
                    $searchText = Str::lower(collect([
                        $staffName,
                        $staff->email,
                        $staff->username,
                        $branchName,
                        $status,
                    ])->merge($availableServices->pluck('title'))->filter()->implode(' '));
                    $mobileFormId = 'staff-skill-form-mobile-' . $staff->id;
                @endphp

                <article class="admin-category-mobile-card admin-booking-mobile-card provider-staff-skill-mobile-card {{ (int) session('updated_staff_id') === (int) $staff->id ? 'is-updated' : '' }}"
                         data-staff-skill-card
                         data-search="{{ $searchText }}"
                         data-branch="{{ $branchFilterValue }}"
                         data-status="{{ $coverageState }}">
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

                        <b data-skill-selected-label>{{ $selectedAvailableCount }}/{{ $availableCount }}</b>
                    </header>

                    <div class="admin-category-mobile-main admin-booking-mobile-main provider-staff-skill-mobile-main">
                        <div>
                            <span>Branch</span>
                            <strong>{{ Str::limit($branchName, 28) }}</strong>
                        </div>

                        <div>
                            <span>Status</span>
                            <strong>{{ $statusLabel($status) }}</strong>
                        </div>

                        <div>
                            <span>Skill</span>
                            <strong data-skill-selected-count>{{ number_format($selectedAvailableCount) }}</strong>
                        </div>

                        <div>
                            <span>Coverage</span>
                            <strong data-skill-coverage-label>{{ $coverage }}%</strong>
                        </div>
                    </div>

                    <form class="provider-staff-skill-form"
                          id="{{ $mobileFormId }}"
                          method="POST"
                          action="{{ provider_route('provider.staff.skills.update') }}"
                          data-skill-form
                          data-total-services="{{ $availableCount }}">
                        @csrf
                        <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                        <div class="provider-staff-skill-progress" aria-hidden="true">
                            <span style="width: {{ $coverage }}%" data-skill-progress-bar></span>
                        </div>

                        <div class="provider-staff-skill-service-grid">
                            @forelse ($availableServices as $service)
                                @php
                                    $inputId = 'skill-mobile-' . $staff->id . '-' . $service->id;
                                    $checked = in_array((string) $service->id, $selectedSkillIds, true);
                                @endphp

                                <label class="provider-staff-skill-option {{ $checked ? 'is-checked' : '' }}" for="{{ $inputId }}">
                                    <input
                                        id="{{ $inputId }}"
                                        type="checkbox"
                                        name="skills[{{ $staff->id }}][]"
                                        value="{{ $service->id }}"
                                        data-skill-checkbox
                                        @checked($checked)
                                    >
                                    <span>
                                        <strong>{{ $service->title }}</strong>
                                        <small>{{ $serviceCategoryFor($service) }} &middot; {{ $formatDuration($service) }} &middot; {{ $branchCoverageFor($service) }}</small>
                                    </span>
                                </label>
                            @empty
                                <div class="provider-staff-skill-inline-empty">
                                    No active services for this staff branch yet.
                                </div>
                            @endforelse
                        </div>

                        <footer class="provider-staff-skill-form-actions">
                            <span class="admin-booking-status {{ $coverageState === 'configured' ? 'success' : 'warning' }}" data-skill-status-badge>
                                {{ $coverageState === 'configured' ? 'Configured' : 'Not configured' }}
                            </span>

                            <div>
                                <button type="button" class="provider-staff-skill-mini-btn" data-skill-select-all title="Select all skills" aria-label="Select all skills" @disabled($availableCount === 0)>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="m4 12 3 3 5-6"></path>
                                        <path d="m4 19 3 3 5-6"></path>
                                        <path d="M14 12h6"></path>
                                        <path d="M14 19h6"></path>
                                    </svg>
                                </button>
                                <button type="button" class="provider-staff-skill-mini-btn" data-skill-clear title="Clear selection" aria-label="Clear selection" @disabled($availableCount === 0)>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M18 6 6 18"></path>
                                        <path d="m6 6 12 12"></path>
                                    </svg>
                                </button>
                                <button type="submit" class="provider-staff-skill-save" title="Save skills" aria-label="Save skills" @disabled($availableCount === 0)>
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
                <div class="admin-category-mobile-empty admin-booking-mobile-empty provider-staff-skill-mobile-empty">
                    <strong>No staff yet.</strong>
                    <p>Add staff first before configuring skills.</p>
                </div>
            @endforelse

            @if ($staffCollection->isNotEmpty())
                <div class="admin-category-mobile-empty admin-booking-mobile-empty provider-staff-skill-mobile-empty" data-staff-skill-mobile-empty hidden>
                    <strong>Staff not found.</strong>
                    <p>Try changing the keyword, branch, or skill configuration status.</p>
                </div>
            @endif
        </div>

        <div class="admin-booking-table-wrap category-table-wrap provider-staff-skill-table-wrap" data-setup-skill-list>
            <table class="admin-booking-table detailed category-table provider-staff-skill-table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Branch</th>
                        <th>Coverage</th>
                        <th>Service Skill</th>
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
                            $selectedSkillIds = collect(old('skills.' . $staff->id, $staff->skills->pluck('id')->all()))
                                ->map(fn ($id) => (string) $id)
                                ->all();
                            $availableServices = $availableServicesFor($staff);
                            $availableIds = $availableServices->pluck('id')->map(fn ($id) => (string) $id)->all();
                            $selectedAvailableCount = collect($selectedSkillIds)->intersect($availableIds)->count();
                            $availableCount = $availableServices->count();
                            $coverage = $availableCount > 0 ? (int) round(($selectedAvailableCount / $availableCount) * 100) : 0;
                            $coverageState = $selectedAvailableCount > 0 ? 'configured' : 'empty';
                            $searchText = Str::lower(collect([
                                $staffName,
                                $staff->email,
                                $staff->username,
                                $branchName,
                                $status,
                            ])->merge($availableServices->pluck('title'))->filter()->implode(' '));
                            $desktopFormId = 'staff-skill-form-desktop-' . $staff->id;
                        @endphp

                        <tr class="{{ (int) session('updated_staff_id') === (int) $staff->id ? 'is-updated' : '' }}"
                            data-staff-skill-row
                            data-search="{{ $searchText }}"
                            data-branch="{{ $branchFilterValue }}"
                            data-status="{{ $coverageState }}">
                            <td>
                                <div class="category-name-box provider-staff-skill-name-box">
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
                                <div class="admin-booking-date provider-staff-skill-branch">
                                    <strong>{{ $branchName }}</strong>
                                    <small>{{ $staff->username ?: 'Username -' }}</small>
                                    <span class="admin-booking-status {{ $statusClass($status) }}">{{ $statusLabel($status) }}</span>
                                </div>
                            </td>

                            <td>
                                <div class="provider-staff-skill-coverage">
                                    <strong data-skill-selected-label>{{ $selectedAvailableCount }}/{{ $availableCount }}</strong>
                                    <small data-skill-coverage-label>{{ $coverage }}% active services</small>
                                    <div class="provider-staff-skill-progress" aria-hidden="true">
                                        <span style="width: {{ $coverage }}%" data-skill-progress-bar></span>
                                    </div>
                                    <span class="admin-booking-status {{ $coverageState === 'configured' ? 'success' : 'warning' }}" data-skill-status-badge>
                                        {{ $coverageState === 'configured' ? 'Configured' : 'Not configured' }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <form class="provider-staff-skill-form"
                                      id="{{ $desktopFormId }}"
                                      method="POST"
                                      action="{{ provider_route('provider.staff.skills.update') }}"
                                      data-skill-form
                                      data-total-services="{{ $availableCount }}">
                                    @csrf
                                    <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                                    <div class="provider-staff-skill-service-grid">
                                        @forelse ($availableServices as $service)
                                            @php
                                                $inputId = 'skill-desktop-' . $staff->id . '-' . $service->id;
                                                $checked = in_array((string) $service->id, $selectedSkillIds, true);
                                            @endphp

                                            <label class="provider-staff-skill-option {{ $checked ? 'is-checked' : '' }}" for="{{ $inputId }}">
                                                <input
                                                    id="{{ $inputId }}"
                                                    type="checkbox"
                                                    name="skills[{{ $staff->id }}][]"
                                                    value="{{ $service->id }}"
                                                    data-skill-checkbox
                                                    @checked($checked)
                                                >
                                                <span>
                                                    <strong>{{ $service->title }}</strong>
                                                    <small>{{ $serviceCategoryFor($service) }} &middot; {{ $formatDuration($service) }} &middot; {{ $branchCoverageFor($service) }}</small>
                                                </span>
                                            </label>
                                        @empty
                                            <div class="provider-staff-skill-inline-empty">
                                                No active services for this staff branch yet.
                                            </div>
                                        @endforelse
                                    </div>
                                </form>
                            </td>

                            <td>
                                <div class="provider-staff-skill-row-actions">
                                    <button type="button" class="provider-staff-skill-mini-btn" data-skill-select-all form="{{ $desktopFormId }}" title="Select all skills" aria-label="Select all skills" @disabled($availableCount === 0)>
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="m4 12 3 3 5-6"></path>
                                            <path d="m4 19 3 3 5-6"></path>
                                            <path d="M14 12h6"></path>
                                            <path d="M14 19h6"></path>
                                        </svg>
                                    </button>
                                    <button type="button" class="provider-staff-skill-mini-btn" data-skill-clear form="{{ $desktopFormId }}" title="Clear selection" aria-label="Clear selection" @disabled($availableCount === 0)>
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M18 6 6 18"></path>
                                            <path d="m6 6 12 12"></path>
                                        </svg>
                                    </button>
                                    <button type="submit" class="provider-staff-skill-save" form="{{ $desktopFormId }}" title="Save skills" aria-label="Save skills" @disabled($availableCount === 0)>
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
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                    </span>

                                    <strong>No staff yet.</strong>
                                    <p>Add staff first before configuring skills.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse

                    @if ($staffCollection->isNotEmpty())
                        <tr data-staff-skill-empty hidden>
                            <td colspan="5" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <circle cx="11" cy="11" r="7"></circle>
                                            <path d="m21 21-4.3-4.3"></path>
                                        </svg>
                                    </span>

                                    <strong>Staff not found.</strong>
                                    <p>Try changing the keyword, branch, or skill configuration status.</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer category-footer provider-staff-skill-footer">
            <p class="admin-booking-showing">
                <strong id="staffSkillShowing">{{ number_format($totalStaff) }}</strong>
                <span>/ {{ number_format($totalStaff) }} staff</span>
            </p>

            <div class="admin-booking-pagination category-pagination static">
                <span class="active">1</span>
            </div>
        </div>
    </div>
</section>

<script src="{{ asset('provider/js/staff-skills.js') }}"></script>
@endsection


