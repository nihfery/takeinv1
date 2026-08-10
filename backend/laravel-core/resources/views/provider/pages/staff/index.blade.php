@extends('provider.layouts.dashboard')

@section('title', 'Staff - Provider Dashboard')
@section('page_title', 'Staff')
@section('page_subtitle', 'Manage staff, branches, roles, contact details, and operational status.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/staff.css') }}">
@endpush

@section('content')
@php
    use Illuminate\Support\Str;

    $staffCollection = $staffs ?? collect();
    $categories = $categories ?? collect();
    $branches = $branches ?? collect();

    $hasPaginator = is_object($staffCollection)
        && method_exists($staffCollection, 'links')
        && method_exists($staffCollection, 'firstItem');
    $firstItem = $hasPaginator ? ($staffCollection->firstItem() ?? 0) : 0;
    $lastItem = $hasPaginator ? ($staffCollection->lastItem() ?? 0) : (is_countable($staffCollection) ? count($staffCollection) : 0);
    $totalItem = $hasPaginator ? $staffCollection->total() : (is_countable($staffCollection) ? count($staffCollection) : 0);

    $filters = $filters ?? [
        'status' => request('status', 'all'),
        'search' => request('search', $search ?? ''),
        'per_page' => request('per_page', $perPage ?? 10),
        'branch_id' => request('branch_id', 'all'),
        'category_id' => request('category_id', 'all'),
        'sort_by' => request('sort_by', 'created_at'),
        'sort_direction' => request('sort_direction', 'desc'),
    ];

    $summary = $summary ?? [
        'total' => $totalItem,
        'active' => 0,
        'inactive' => 0,
        'branches' => 0,
    ];

    $statusCounts = $statusCounts ?? [
        'all' => $summary['total'] ?? $totalItem,
        'active' => $summary['active'] ?? 0,
        'inactive' => $summary['inactive'] ?? 0,
    ];

    $statusTabs = [
        'all' => 'All Staff',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    $currentStatus = $filters['status'] ?? 'all';
    $sortBy = $sortBy ?? ($filters['sort_by'] ?? 'created_at');
    $sortDirection = $sortDirection ?? ($filters['sort_direction'] ?? 'desc');

    $cleanQuery = function (array $query) {
        return collect($query)
            ->reject(function ($value, $key) {
                if ($value === null || $value === '') {
                    return true;
                }

                if (in_array($key, ['status', 'branch_id', 'category_id'], true) && $value === 'all') {
                    return true;
                }

                if ($key === 'sort_by' && $value === 'created_at') {
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
    $sortIconClass = fn (string $key, string $direction) => $sortBy === $key && $sortDirection === $direction ? 'active' : '';

    $statusLabel = fn ($value) => ucfirst($value ?: 'active');
    $statusClass = fn ($value) => ($value ?? 'active') === 'active' ? 'success' : 'danger';

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

    $staffNameFor = fn ($staff) => $staff->full_name ?: trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
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

    $phoneText = fn ($staff) => trim(($staff->country_code ?? '') . ($staff->phone_number ?? '')) ?: '-';
    $locationText = fn ($staff) => collect([
        $staff->city_id ?? null,
        $staff->state_id ?? null,
        $staff->country_id ?? null,
    ])->filter()->values()->implode(', ');

    $branchNameFor = fn ($staff) => $staff->branch->branch_name
        ?? optional($branches->firstWhere('id', $staff->branch_id))->branch_name
        ?? '-';
    $categoryNameFor = fn ($staff) => $staff->category->name
        ?? optional($categories->firstWhere('id', $staff->category_id))->name
        ?? '-';
    $roleNameFor = fn ($staff) => $staff->providerRole->role_name ?? 'Default staff';
    $genderLabel = fn ($value) => $value ? ucfirst($value) : 'Gender -';

    $selectedBranchName = function ($id) use ($branches) {
        if ($id === 'all' || $id === null || $id === '') {
            return null;
        }

        return optional($branches->firstWhere('id', (int) $id))->branch_name;
    };

    $selectedCategoryName = function ($id) use ($categories) {
        if ($id === 'all' || $id === null || $id === '') {
            return null;
        }

        return optional($categories->firstWhere('id', (int) $id))->name;
    };

    $hasMobileAdvancedFilters = (($filters['branch_id'] ?? 'all') !== 'all')
        || (($filters['category_id'] ?? 'all') !== 'all')
        || ((int) ($filters['per_page'] ?? 10) !== 10);

@endphp

<section class="admin-category-page admin-booking-page provider-staff-category-page">
    <div class="admin-booking-route admin-category-route provider-staff-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Staff</strong>
        </div>

        <div class="provider-staff-actions provider-staff-actions-desktop" style="display: flex; align-items: center; gap: 15px;">
            <button type="button" class="admin-category-add-button" data-staff-add data-setup-add-staff>
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Add Staff
            </button>
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
            <span>Total Staff</span>
            <strong>{{ number_format((int) ($summary['total'] ?? 0)) }}</strong>
            <small>Data based on the active filters</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Active</span>
            <strong>{{ number_format((int) ($summary['active'] ?? 0)) }}</strong>
            <small>Staff ready to receive bookings</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Assigned Branches</span>
            <strong>{{ number_format((int) ($summary['branches'] ?? 0)) }}</strong>
            <small>Branches with active staff in this list</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Inactive</span>
            <strong>{{ number_format((int) ($summary['inactive'] ?? 0)) }}</strong>
            <small>Temporarily inactive staff</small>
        </div>
    </div>

    <div class="admin-booking-card category-card provider-staff-category-card">
        <div class="admin-booking-tabs provider-staff-tabs">
            @foreach ($statusTabs as $key => $label)
                <a href="{{ provider_route('provider.staffs.index', $queryFor(['status' => $key])) }}"
                   class="admin-booking-tab {{ ($currentStatus === $key || ($key === 'all' && empty($currentStatus))) ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ provider_route('provider.staffs.index') }}" class="admin-booking-filter-panel compact {{ $hasMobileAdvancedFilters ? 'is-expanded' : '' }}">
            @if (! empty($currentStatus) && $currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif

            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
            <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">

            <div class="admin-booking-filter-row provider-staff-filter-row" id="staffFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="staffSearchInput"
                               type="text"
                               name="search"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Search staff">
                    </div>
                </label>

                <button type="submit" class="admin-booking-mobile-search-submit" aria-label="Search staff">
                    Search
                </button>

                <button type="button"
                        class="admin-booking-mobile-filter-toggle {{ $hasMobileAdvancedFilters ? 'active' : '' }}"
                        aria-controls="staffFilterRow"
                        aria-expanded="{{ $hasMobileAdvancedFilters ? 'true' : 'false' }}">
                    Filter
                </button>

                <label class="admin-booking-field mini">
                    <select name="branch_id" aria-label="Branch" title="Branch">
                        <option value="all">All Branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ (string) ($filters['branch_id'] ?? 'all') === (string) $branch->id ? 'selected' : '' }}>
                                {{ $branch->branch_name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-booking-field mini">
                    <select name="category_id" aria-label="Category" title="Category">
                        <option value="all">All Category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) ($filters['category_id'] ?? 'all') === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-booking-field count">
                    <select id="staffEntriesSelect" name="per_page" aria-label="Rows per page" title="Rows per page">
                        <option value="10" {{ (int) ($filters['per_page'] ?? 10) === 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ (int) ($filters['per_page'] ?? 10) === 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ (int) ($filters['per_page'] ?? 10) === 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ (int) ($filters['per_page'] ?? 10) === 100 ? 'selected' : '' }}>100</option>
                    </select>
                </label>

                <div class="admin-booking-filter-buttons">
                    <button type="submit">Filter</button>
                    @if ($hasActiveFilters ?? false)
                        <a href="{{ provider_route('provider.staffs.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} staff</span>

                @if (($filters['search'] ?? '') !== '')
                    <span>Search: {{ $filters['search'] }}</span>
                @endif

                @if (($filters['status'] ?? 'all') !== 'all')
                    <span>Status: {{ $statusLabel($filters['status']) }}</span>
                @endif

                @if ($selectedBranchName($filters['branch_id'] ?? 'all'))
                    <span>Branch: {{ $selectedBranchName($filters['branch_id']) }}</span>
                @endif

                @if ($selectedCategoryName($filters['category_id'] ?? 'all'))
                    <span>Category: {{ $selectedCategoryName($filters['category_id']) }}</span>
                @endif
            </div>
        </form>

        <div class="admin-category-add-row provider-staff-actions provider-staff-actions-mobile" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
            <button type="button" class="admin-category-add-button admin-category-add-button-mobile" data-staff-add data-setup-add-staff style="width: 100%;">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Add Staff
            </button>
        </div>

        <div class="admin-category-mobile-list admin-booking-mobile-list provider-staff-mobile-list">
            @forelse ($staffCollection as $staff)
                @php
                    $staffName = $staffNameFor($staff);
                    $initial = $staffInitial($staff, $staffName);
                    $imageUrl = $staffImageUrl($staff);
                    $phone = $phoneText($staff);
                    $location = $locationText($staff);
                    $branchName = $branchNameFor($staff);
                    $categoryName = $categoryNameFor($staff);
                    $roleName = $roleNameFor($staff);
                    $status = $staff->status ?? 'active';
                    $dateOfBirth = $staff->date_of_birth instanceof \Carbon\CarbonInterface
                        ? $staff->date_of_birth->format('Y-m-d')
                        : $staff->date_of_birth;
                    $staffPayload = [
                        'id' => $staff->id,
                        'image' => $staff->image,
                        'image_url' => $imageUrl ?: '',
                        'first_name' => $staff->first_name,
                        'last_name' => $staff->last_name,
                        'email' => $staff->email,
                        'username' => $staff->username,
                        'country_code' => $staff->country_code,
                        'phone_number' => $staff->phone_number,
                        'gender' => $staff->gender,
                        'date_of_birth' => $dateOfBirth,
                        'address' => $staff->address,
                        'country_id' => $staff->country_id,
                        'state_id' => $staff->state_id,
                        'city_id' => $staff->city_id,
                        'postal_code' => $staff->postal_code,
                        'bio' => $staff->bio,
                        'category_id' => $staff->category_id,
                        'branch_id' => $staff->branch_id,
                        'provider_role_id' => $staff->provider_role_id,
                        'status' => $status,
                        'update_url' => provider_route('provider.staffs.update', $staff->id),
                        'delete_url' => provider_route('provider.staffs.destroy', $staff->id),
                    ];
                @endphp

                <article class="admin-category-mobile-card admin-booking-mobile-card provider-staff-mobile-card">
                    <header class="admin-category-mobile-head">
                        <div class="admin-category-mobile-title">
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $staffName }}">
                            @else
                                <span>{{ $initial }}</span>
                            @endif

                            <div>
                                <strong>{{ $staffName ?: '-' }}</strong>
                                <span>{{ $staff->email ?? 'No email' }}</span>
                            </div>
                        </div>

                        <b>{{ $roleName }}</b>
                    </header>

                    <div class="admin-category-mobile-main admin-booking-mobile-main provider-staff-mobile-main">
                        <div>
                            <span>Phone</span>
                            <strong>{{ $phone }}</strong>
                        </div>

                        <div>
                            <span>Branch</span>
                            <strong>{{ Str::limit($branchName, 28) }}</strong>
                        </div>

                        <div>
                            <span>Category</span>
                            <strong>{{ Str::limit($categoryName, 28) }}</strong>
                        </div>

                        <div>
                            <span>Location</span>
                            <strong>{{ Str::limit($location ?: '-', 28) }}</strong>
                        </div>
                    </div>

                    <p>{{ Str::limit($staff->bio ?: $staff->address ?: 'No profile note', 120) }}</p>

                    <footer class="admin-category-mobile-footer provider-staff-mobile-footer">
                        <form action="{{ provider_route('provider.staffs.toggle-status', $staff->id) }}" method="POST" class="inline-form provider-service-status-form" data-status="{{ $status }}" data-has-ongoing="{{ $staff->has_ongoing_bookings ? 'true' : 'false' }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="provider-status-toggle status-{{ $statusClass($status) }}" title="Click to change status">
                                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                {{ $statusLabel($status) }}
                            </button>
                        </form>

                        <div class="category-actions provider-staff-action-icons provider-staff-mobile-action-icons">
                            <button
                                type="button"
                                class="category-action-btn info staff-edit-btn"
                                title="Edit"
                                aria-label="Edit {{ $staffName ?: 'staff' }}"
                                data-staff='@json($staffPayload)'
                            >
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                </svg>
                            </button>

                            @if ($staff->can_be_deleted)
                            <button
                                type="button"
                                class="category-action-btn danger staff-delete-trigger"
                                title="Delete"
                                aria-label="Delete {{ $staffName ?: 'staff' }}"
                                data-delete-url="{{ provider_route('provider.staffs.destroy', $staff->id) }}"
                            >
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M3 6h18"></path>
                                    <path d="M8 6V4h8v2"></path>
                                    <path d="M19 6l-1 14H6L5 6"></path>
                                    <path d="M10 11v6"></path>
                                    <path d="M14 11v6"></path>
                                </svg>
                            </button>
                            @endif
                        </div>
                    </footer>
                </article>
            @empty
                <div class="admin-category-mobile-empty admin-booking-mobile-empty provider-staff-mobile-empty">
                    <strong>No staff available.</strong>
                    <p>Try changing the keyword, branch, category, or staff status.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap category-table-wrap provider-staff-category-table-wrap" data-setup-staff-list>
            <table class="admin-booking-table detailed category-table provider-staff-category-table">
                <thead>
                    <tr>
                        <th>
                            <a href="{{ provider_route('provider.staffs.index', $sortQueryFor('full_name')) }}" class="admin-booking-sort {{ $sortBy === 'full_name' ? 'active' : '' }}">
                                Staff
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('full_name', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('full_name', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ provider_route('provider.staffs.index', $sortQueryFor('email')) }}" class="admin-booking-sort {{ $sortBy === 'email' ? 'active' : '' }}">
                                Contact
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('email', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('email', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Location</th>
                        <th>Branch</th>
                        <th>Category</th>
                        <th>Role</th>
                        <th>
                            <a href="{{ provider_route('provider.staffs.index', $sortQueryFor('status')) }}" class="admin-booking-sort {{ $sortBy === 'status' ? 'active' : '' }}">
                                Status
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('status', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('status', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($staffCollection as $staff)
                        @php
                            $staffName = $staffNameFor($staff);
                            $initial = $staffInitial($staff, $staffName);
                            $imageUrl = $staffImageUrl($staff);
                            $phone = $phoneText($staff);
                            $location = $locationText($staff);
                            $branchName = $branchNameFor($staff);
                            $categoryName = $categoryNameFor($staff);
                            $roleName = $roleNameFor($staff);
                            $status = $staff->status ?? 'active';
                            $dateOfBirth = $staff->date_of_birth instanceof \Carbon\CarbonInterface
                                ? $staff->date_of_birth->format('Y-m-d')
                                : $staff->date_of_birth;
                            $staffPayload = [
                                'id' => $staff->id,
                                'image' => $staff->image,
                                'image_url' => $imageUrl ?: '',
                                'first_name' => $staff->first_name,
                                'last_name' => $staff->last_name,
                                'email' => $staff->email,
                                'username' => $staff->username,
                                'country_code' => $staff->country_code,
                                'phone_number' => $staff->phone_number,
                                'gender' => $staff->gender,
                                'date_of_birth' => $dateOfBirth,
                                'address' => $staff->address,
                                'country_id' => $staff->country_id,
                                'state_id' => $staff->state_id,
                                'city_id' => $staff->city_id,
                                'postal_code' => $staff->postal_code,
                                'bio' => $staff->bio,
                                'category_id' => $staff->category_id,
                                'branch_id' => $staff->branch_id,
                                'provider_role_id' => $staff->provider_role_id,
                                'status' => $status,
                                'update_url' => provider_route('provider.staffs.update', $staff->id),
                                'delete_url' => provider_route('provider.staffs.destroy', $staff->id),
                            ];
                        @endphp

                        <tr>
                            <td>
                                <div class="category-name-box provider-staff-name-box">
                                    @if ($imageUrl)
                                        <img class="category-thumb" src="{{ $imageUrl }}" alt="{{ $staffName }}">
                                    @else
                                        <span class="category-thumb-placeholder">{{ $initial }}</span>
                                    @endif

                                    <div class="category-name-text">
                                        <strong>{{ $staffName ?: '-' }}</strong>
                                        <small>ID #{{ $staff->id }}</small>
                                        @if ($staff->username)
                                            <small>{{ $staff->username }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date">
                                    <strong>{{ $phone }}</strong>
                                    <small>{{ $staff->email ?? 'No email' }}</small>
                                    <small>{{ $genderLabel($staff->gender) }}{{ $dateOfBirth ? ' - ' . $formatDate($dateOfBirth) : '' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date provider-staff-location-stack">
                                    <strong>{{ $location ?: '-' }}</strong>
                                    <small>{{ Str::limit($staff->address ?: '-', 64) }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date provider-staff-assignment-stack">
                                    <strong>{{ $branchName }}</strong>
                                    <small>{{ optional($staff->branch)->status ? $statusLabel($staff->branch->status) : 'Branch assigned' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date provider-staff-assignment-stack">
                                    <strong>{{ $categoryName }}</strong>
                                    <small>{{ optional($staff->category)->status ? $statusLabel($staff->category->status) : 'Service category' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-mode-stack provider-staff-role-stack">
                                    <span class="admin-booking-status info">
                                        {{ Str::limit($roleName, 18) }}
                                    </span>
                                    <small>{{ $staff->role ?? 'staff' }}</small>
                                </div>
                            </td>

                            <td>
                                <form action="{{ provider_route('provider.staffs.toggle-status', $staff->id) }}" method="POST" class="inline-form provider-service-status-form" data-status="{{ $status }}" data-has-ongoing="{{ $staff->has_ongoing_bookings ? 'true' : 'false' }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="provider-status-toggle status-{{ $statusClass($status) }}" title="Click to change status">
                                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                        {{ $statusLabel($status) }}
                                    </button>
                                </form>
                            </td>

                            <td>
                                <div class="category-actions provider-staff-action-icons provider-staff-row-actions">
                                    <button
                                        type="button"
                                        class="category-action-btn info staff-edit-btn"
                                        title="Edit"
                                        aria-label="Edit {{ $staffName ?: 'staff' }}"
                                        data-staff='@json($staffPayload)'
                                    >
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                        </svg>
                                    </button>

                                    @if ($staff->can_be_deleted)
                                    <button
                                        type="button"
                                        class="category-action-btn danger staff-delete-trigger"
                                        title="Delete"
                                        aria-label="Delete {{ $staffName ?: 'staff' }}"
                                        data-delete-url="{{ provider_route('provider.staffs.destroy', $staff->id) }}"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M3 6h18"></path>
                                            <path d="M8 6V4h8v2"></path>
                                            <path d="M19 6l-1 14H6L5 6"></path>
                                            <path d="M10 11v6"></path>
                                            <path d="M14 11v6"></path>
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                    </span>

                                    <strong>No staff available.</strong>
                                    <p>Try changing the keyword, branch, category, or staff status.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer category-footer provider-staff-footer">
            <p class="admin-booking-showing">
                <strong>{{ number_format($firstItem) }}-{{ number_format($lastItem) }}</strong>
                <span>/ {{ number_format($totalItem) }}</span>
            </p>

            @if ($hasPaginator)
                <div class="admin-booking-pagination category-pagination">
                    @if ($staffCollection->onFirstPage())
                        <span class="disabled">&lsaquo;</span>
                    @else
                        <a href="{{ $staffCollection->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
                    @endif

                    <span class="active">{{ $staffCollection->currentPage() }}</span>

                    @if ($staffCollection->hasMorePages())
                        <a href="{{ $staffCollection->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
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
    </div>
</section>

<div class="staff-modal-overlay" id="staffModal">
    <div class="staff-modal">
        <div class="staff-modal-header">
            <div>
                <h2 id="staffModalTitle">Add Staff</h2>
                <p>Complete the provider staff details.</p>
            </div>

            <button type="button" class="staff-modal-close" id="staffModalClose" aria-label="Close modal">
                &times;
            </button>
        </div>

        <form action="{{ provider_route('provider.staffs.store') }}" method="POST" enctype="multipart/form-data" id="staffForm" data-setup-staff-form>
            @csrf
            <input type="hidden" name="_method" id="staffFormMethod" value="">
            <input type="hidden" name="role" value="staff">

            <div class="staff-form-body">
                <div class="staff-image-row">
                    <label for="staffImageInput" class="staff-image-upload">
                        <img src="" alt="Staff Preview" id="staffImagePreview" class="hidden">

                        <span id="staffImagePlaceholder">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M4 5h16v14H4z"></path>
                                <path d="m4 15 4-4 4 4 3-3 5 5"></path>
                                <circle cx="9" cy="9" r="1.5"></circle>
                            </svg>
                            <small>Image</small>
                        </span>
                    </label>

                    <input type="file" name="image" id="staffImageInput" accept="image/*" hidden>

                    <div>
                        <strong>Profile Image</strong>
                        <p>Upload a staff photo. Supported formats: JPG, PNG, WEBP.</p>
                    </div>
                </div>

                <div class="staff-form-grid two">
                    <div class="staff-form-group">
                        <label>First Name <span>*</span></label>
                        <input type="text" name="first_name" placeholder="Enter First Name">
                    </div>

                    <div class="staff-form-group">
                        <label>Last Name <span>*</span></label>
                        <input type="text" name="last_name" placeholder="Enter Last Name">
                    </div>

                    <div class="staff-form-group">
                        <label>Email <span>*</span></label>
                        <input type="email" name="email" placeholder="Enter Email">
                    </div>

                    <div class="staff-form-group">
                        <label>Username</label>
                        <input type="text" name="username" placeholder="Enter Username">
                    </div>

                    <div class="staff-form-group">
                        <label>Phone Number</label>

                        <div class="staff-phone-row">
                            <select name="country_code" id="staffPhoneCodeSelect" data-selected="+62">
                                <option value="">Loading codes...</option>
                            </select>

                            <input type="text" name="phone_number" placeholder="Enter Phone Number">
                        </div>
                    </div>

                    <div class="staff-form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="staff-form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth">
                    </div>

                    <div class="staff-form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="staff-form-group">
                        <label>Country</label>
                        <select name="country_id" id="staffCountrySelect" data-selected="Indonesia">
                            <option value="">Loading countries...</option>
                        </select>
                    </div>

                    <div class="staff-form-group">
                        <label>State</label>
                        <select name="state_id" id="staffStateSelect" data-selected="">
                            <option value="">Select Country First</option>
                        </select>
                    </div>

                    <div class="staff-form-group">
                        <label>City</label>
                        <select name="city_id" id="staffCitySelect" data-selected="">
                            <option value="">Select State First</option>
                        </select>
                    </div>

                    <div class="staff-form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" placeholder="Enter Postal Code">
                    </div>

                    <div class="staff-form-group">
                        <label>Category</label>
                        <select name="category_id" id="staffCategorySelect" required>
                            <option value="">Select Category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="staff-form-group">
                        <label>Work Location (Branch) <span>*</span></label>
                        <select name="branch_id" id="staffBranchSelect" required>
                            <option value="">Select Work Location</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">
                                    {{ $branch->branch_name }}
                                </option>
                            @endforeach
                        </select>
                        <small>This staff member will work and receive bookings at the selected branch.</small>
                    </div>
                </div>

                <div class="staff-form-group full">
                    <label>Address</label>
                    <textarea name="address" placeholder="Enter Address"></textarea>
                </div>

                <div class="staff-form-group full">
                    <label>Bio</label>
                    <textarea name="bio" placeholder="Enter Bio"></textarea>
                </div>
            </div>

            <div class="staff-form-actions" data-setup-staff-save>
                <button type="button" class="staff-back-btn" id="staffModalCancel">
                    Cancel
                </button>

                <button type="submit" class="staff-submit-btn">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

<div class="staff-delete-modal-overlay" id="staffDeleteModal">
    <div class="staff-delete-modal">
        <div class="staff-delete-icon">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M3 6h18"></path>
                <path d="M8 6V4h8v2"></path>
                <path d="M19 6l-1 14H6L5 6"></path>
                <path d="M10 11v6"></path>
                <path d="M14 11v6"></path>
            </svg>
        </div>

        <h2>Delete Staff?</h2>

        <p>
            This staff member will be removed from the provider list. Data cannot be restored after confirmation.
        </p>

        <div class="staff-delete-modal-actions">
            <button type="button" class="staff-modal-cancel" id="staffDeleteCancel">
                Cancel
            </button>

            <form method="POST" id="staffDeleteConfirmForm">
                @csrf
                @method('DELETE')

                <button type="submit" class="staff-modal-delete">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('provider/js/staff.js') }}?v={{ filemtime(public_path('provider/js/staff.js')) }}"></script>
@endsection


