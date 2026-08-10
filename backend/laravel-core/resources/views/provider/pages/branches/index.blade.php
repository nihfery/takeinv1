@extends('provider.layouts.dashboard')

@section('title', 'Branch - Provider Dashboard')
@section('page_title', 'Branch')
@section('page_subtitle', 'Manage branch locations, contact details, photos, and operating schedules.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/branch.css') }}">
@endpush

@section('content')
@php
    use Illuminate\Support\Str;

    $isBranchAccount = $isBranchAccount ?? false;
    $branchCollection = $branches ?? collect();
    $totalBranches = $branchCollection->count();
    $activeBranches = $branchCollection->where('status', 'active')->count();
    $inactiveBranches = $branchCollection->where('status', 'inactive')->count();
    $totalStaffs = $branchCollection->sum(fn ($branch) => (int) ($branch->staffs_count ?? optional($branch->staffs)->count() ?? 0));

    $statusTabs = [
        'all' => ['label' => 'All Branch', 'count' => $totalBranches],
        'active' => ['label' => 'Active', 'count' => $activeBranches],
        'inactive' => ['label' => 'Inactive', 'count' => $inactiveBranches],
    ];

    $statusClass = fn ($status) => ($status ?? 'active') === 'active' ? 'success' : 'danger';
    $statusLabel = fn ($status) => ucfirst($status ?: 'active');
    $branchInitial = fn ($branch) => strtoupper(substr((string) ($branch->branch_name ?: 'B'), 0, 1));
    $formatTime = fn ($value) => $value ? substr((string) $value, 0, 5) : '-';

    $locationText = function ($branch) {
        return collect([
            $branch->city_id ?? null,
            $branch->state_id ?? null,
            $branch->country_id ?? null,
        ])->filter()->values()->implode(', ');
    };

    $phoneText = fn ($branch) => trim(($branch->phone_code ?? '') . ($branch->phone_number ?? '')) ?: '-';
    $workingDayText = function ($branch) {
        $days = collect($branch->working_days ?? [])->filter()->values();

        if ($days->isEmpty()) {
            return 'Working day -';
        }

        return $days->count() > 3
            ? $days->take(3)->implode(', ') . ' +' . ($days->count() - 3)
            : $days->implode(', ');
    };

@endphp

<section class="admin-category-page admin-booking-page provider-branch-category-page">
    <div class="admin-booking-route admin-category-route provider-branch-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Branch</strong>
        </div>

        @unless ($isBranchAccount)
            <div class="provider-branch-actions provider-branch-actions-desktop" style="display: flex; align-items: center; gap: 15px;">
                <a href="{{ provider_route('provider.branch.create') }}" class="admin-category-add-button" data-setup-add-branch>
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>
                    Add Branch
                </a>
            </div>
        @endunless
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

    <div class="admin-booking-summary-grid">
        <div class="admin-booking-summary-card pink">
            <span>Total Branch</span>
            <strong>{{ number_format($totalBranches) }}</strong>
            <small>{{ $isBranchAccount ? 'Access is limited to this branch' : 'All provider branches' }}</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Active</span>
            <strong>{{ number_format($activeBranches) }}</strong>
            <small>Branches that can accept bookings</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Total Staff</span>
            <strong>{{ number_format($totalStaffs) }}</strong>
            <small>Staff connected to branches</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Inactive</span>
            <strong>{{ number_format($inactiveBranches) }}</strong>
            <small>Inactive or closed branches</small>
        </div>
    </div>

    <div class="admin-booking-card category-card provider-branch-category-card">
        <div class="admin-booking-tabs provider-branch-tabs">
            @foreach ($statusTabs as $key => $tab)
                <button
                    type="button"
                    class="admin-booking-tab {{ $key === 'all' ? 'active' : '' }}"
                    data-branch-status-filter="{{ $key }}"
                >
                    {{ $tab['label'] }}
                    <span>{{ number_format($tab['count']) }}</span>
                </button>
            @endforeach
        </div>

        <div class="admin-booking-filter-panel compact provider-branch-filter-panel">
            <div class="admin-booking-filter-row provider-branch-filter-row">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input type="text" id="branchSearchInput" placeholder="Search branch">
                    </div>
                </label>

                <button type="button" class="admin-booking-mobile-search-submit" aria-label="Search branch">
                    Search
                </button>

                <button
                    type="button"
                    class="admin-booking-mobile-filter-toggle"
                    aria-controls="branchFilterRow"
                    aria-expanded="false"
                >
                    Filter
                </button>

                <label class="admin-booking-field count">
                    <select id="branchEntriesSelect" aria-label="Rows per page" title="Rows per page">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>

                <div class="admin-booking-filter-buttons">
                    <button type="button" id="branchResetFilter">Reset</button>
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalBranches) }} branch</span>
                <span>{{ number_format($activeBranches) }} active</span>
                <span>{{ number_format($totalStaffs) }} staff</span>
            </div>
        </div>

        @unless ($isBranchAccount)
            <div class="admin-category-add-row provider-branch-actions provider-branch-actions-mobile">
                <a href="{{ provider_route('provider.branch.create') }}" class="admin-category-add-button" data-setup-add-branch>
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>
                    Add Branch
                </a>
            </div>
        @endunless

        <div class="admin-category-mobile-list admin-booking-mobile-list provider-branch-mobile-list" id="branchMobileList">
            @foreach ($branchCollection as $index => $branch)
                @php
                    $staffCount = (int) ($branch->staffs_count ?? optional($branch->staffs)->count() ?? 0);
                    $status = $branch->status ?? 'active';
                    $location = $locationText($branch);
                    $phone = $phoneText($branch);
                    $schedule = $formatTime($branch->working_start_hour) . ' - ' . $formatTime($branch->working_end_hour);
                    $searchText = collect([
                        $branch->branch_name,
                        $branch->email,
                        $phone,
                        $branch->address,
                        $location,
                        $status,
                    ])->filter()->implode(' ');
                @endphp

                <article
                    class="admin-category-mobile-card admin-booking-mobile-card provider-branch-mobile-card"
                    data-branch-card
                    data-branch-id="{{ $branch->id }}"
                    data-branch-status="{{ $status }}"
                    data-branch-search="{{ Str::lower($searchText) }}"
                >
                    <header class="admin-category-mobile-head">
                        <div class="admin-category-mobile-title">
                            @if (! empty($branch->image_url))
                                <img src="{{ $branch->image_url }}" alt="{{ $branch->branch_name }}">
                            @else
                                <span>{{ $branchInitial($branch) }}</span>
                            @endif

                            <div>
                                <strong>{{ $branch->branch_name }}</strong>
                                <span>{{ $branch->email ?? 'No email' }}</span>
                            </div>
                        </div>

                        <b>{{ $staffCount }} staff</b>
                    </header>

                    <div class="admin-category-mobile-main admin-booking-mobile-main provider-branch-mobile-main">
                        <div>
                            <span>Phone</span>
                            <strong>{{ $phone }}</strong>
                        </div>

                        <div>
                            <span>Location</span>
                            <strong>{{ Str::limit($location ?: '-', 28) }}</strong>
                        </div>

                        <div>
                            <span>Hours</span>
                            <strong>{{ $schedule }}</strong>
                            <small>{{ $workingDayText($branch) }}</small>
                        </div>

                        <div>
                            <span>Status</span>
                            <strong>{{ $statusLabel($status) }}</strong>
                        </div>
                    </div>

                    <p>{{ Str::limit($branch->address ?: 'No address', 120) }}</p>

                    <footer class="admin-category-mobile-footer provider-branch-mobile-footer">
                        <form action="{{ provider_route('provider.branch.toggle-status', $branch->id) }}" method="POST" class="inline-form provider-service-status-form" data-status="{{ $status }}" data-has-ongoing="{{ $branch->has_ongoing_bookings ? 'true' : 'false' }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="provider-status-toggle status-{{ $statusClass($status) }}" title="Click to change status">
                                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                {{ $statusLabel($status) }}
                            </button>
                        </form>

                        <div class="category-actions provider-branch-action-icons">
                            <a href="{{ provider_route('provider.branch.edit', $branch->id) }}" class="category-action-btn" title="Edit" aria-label="Edit {{ $branch->branch_name }}">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                </svg>
                            </a>

                            @unless ($isBranchAccount)
                                @if ($branch->can_be_deleted)
                                <button
                                    type="button"
                                    class="category-action-btn danger branch-delete-trigger"
                                    title="Delete"
                                    aria-label="Delete {{ $branch->branch_name }}"
                                    data-delete-url="{{ provider_route('provider.branch.destroy', $branch->id) }}"
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
                            @endunless
                        </div>
                    </footer>
                </article>
            @endforeach

            <div class="admin-category-mobile-empty admin-booking-mobile-empty provider-branch-mobile-empty" id="branchMobileEmpty" {{ $totalBranches > 0 ? 'hidden' : '' }}>
                <strong>No branch available.</strong>
                <p>Add a new branch or change the search keyword.</p>
            </div>
        </div>

        <div class="admin-booking-table-wrap category-table-wrap provider-branch-category-table-wrap" data-setup-branch-list>
            <table class="admin-booking-table detailed category-table provider-branch-category-table" id="branchTable">
                <thead>
                    <tr>
                        <th data-sort="number">
                            <button type="button" class="admin-booking-sort provider-branch-sort-button">
                                #
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span>&uarr;</span>
                                    <span>&darr;</span>
                                </span>
                            </button>
                        </th>
                        <th data-sort="text">
                            <button type="button" class="admin-booking-sort provider-branch-sort-button">
                                Branch
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span>&uarr;</span>
                                    <span>&darr;</span>
                                </span>
                            </button>
                        </th>
                        <th data-sort="text">
                            <button type="button" class="admin-booking-sort provider-branch-sort-button">
                                Contact
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span>&uarr;</span>
                                    <span>&darr;</span>
                                </span>
                            </button>
                        </th>
                        <th data-sort="text">
                            <button type="button" class="admin-booking-sort provider-branch-sort-button">
                                Location
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span>&uarr;</span>
                                    <span>&darr;</span>
                                </span>
                            </button>
                        </th>
                        <th data-sort="text">
                            <button type="button" class="admin-booking-sort provider-branch-sort-button">
                                Schedule
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span>&uarr;</span>
                                    <span>&darr;</span>
                                </span>
                            </button>
                        </th>
                        <th data-sort="number">
                            <button type="button" class="admin-booking-sort provider-branch-sort-button">
                                Staff
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span>&uarr;</span>
                                    <span>&darr;</span>
                                </span>
                            </button>
                        </th>
                        <th data-sort="text">
                            <button type="button" class="admin-booking-sort provider-branch-sort-button">
                                Status
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span>&uarr;</span>
                                    <span>&darr;</span>
                                </span>
                            </button>
                        </th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($branchCollection as $index => $branch)
                        @php
                            $staffCount = (int) ($branch->staffs_count ?? optional($branch->staffs)->count() ?? 0);
                            $status = $branch->status ?? 'active';
                            $location = $locationText($branch);
                            $phone = $phoneText($branch);
                            $schedule = $formatTime($branch->working_start_hour) . ' - ' . $formatTime($branch->working_end_hour);
                        @endphp

                        <tr data-branch-id="{{ $branch->id }}" data-branch-status="{{ $status }}">
                            <td>
                                <div class="admin-booking-code-cell">
                                    <strong>{{ $index + 1 }}</strong>
                                    <small>ID #{{ $branch->id }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="category-name-box provider-branch-name-box">
                                    @if (! empty($branch->image_url))
                                        <img class="category-thumb" src="{{ $branch->image_url }}" alt="{{ $branch->branch_name }}">
                                    @else
                                        <span class="category-thumb-placeholder">{{ $branchInitial($branch) }}</span>
                                    @endif

                                    <div class="category-name-text">
                                        <strong>{{ $branch->branch_name }}</strong>
                                        <small>{{ $branch->email ?? 'No email' }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date">
                                    <strong>{{ $phone }}</strong>
                                    <small>{{ $branch->email ?? 'No email' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date provider-branch-location-stack">
                                    <strong>{{ $location ?: '-' }}</strong>
                                    <small>{{ Str::limit($branch->address ?: '-', 64) }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date provider-branch-schedule-stack">
                                    <strong>{{ $schedule }}</strong>
                                    <small>{{ $workingDayText($branch) }}</small>
                                </div>
                            </td>

                            <td>
                                <span class="admin-booking-status info">
                                    {{ number_format($staffCount) }}
                                </span>
                            </td>

                            <td>
                                <form action="{{ provider_route('provider.branch.toggle-status', $branch->id) }}" method="POST" class="inline-form provider-service-status-form" data-status="{{ $status }}" data-has-ongoing="{{ $branch->has_ongoing_bookings ? 'true' : 'false' }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="provider-status-toggle status-{{ $statusClass($status) }}" title="Click to change status">
                                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                        {{ $statusLabel($status) }}
                                    </button>
                                </form>
                            </td>

                            <td>
                                <div class="category-actions provider-branch-action-icons provider-branch-row-actions">
                                    <a href="{{ provider_route('provider.branch.edit', $branch->id) }}" class="category-action-btn" title="Edit" aria-label="Edit {{ $branch->branch_name }}">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                        </svg>
                                    </a>

                                    @unless ($isBranchAccount)
                                        @if ($branch->can_be_deleted)
                                        <button
                                            type="button"
                                            class="category-action-btn danger branch-delete-trigger"
                                            title="Delete"
                                            aria-label="Delete {{ $branch->branch_name }}"
                                            data-delete-url="{{ provider_route('provider.branch.destroy', $branch->id) }}"
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
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="branch-empty-row">
                            <td colspan="8" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 21V5a2 2 0 0 1 2-2h10v18"></path>
                                            <path d="M16 8h2a2 2 0 0 1 2 2v11"></path>
                                            <path d="M8 7h4"></path>
                                            <path d="M8 11h4"></path>
                                            <path d="M8 15h4"></path>
                                        </svg>
                                    </span>

                                    <strong>No branch available.</strong>
                                    <p>Add a new branch to start managing provider locations.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer category-footer provider-branch-footer">
            <p class="admin-booking-showing branch-info-text" id="branchInfoText">
                <strong>0-0</strong>
                <span>/ 0</span>
            </p>

            <div class="admin-booking-pagination category-pagination branch-pagination" id="branchPagination">
                <button type="button" data-page="first">First</button>
                <button type="button" data-page="previous">Previous</button>
                <button type="button" class="active" data-page="1">1</button>
                <button type="button" data-page="next">Next</button>
                <button type="button" data-page="last">Last</button>
            </div>
        </div>
    </div>
</section>

<div class="branch-delete-modal-overlay" id="branchDeleteModal">
    <div class="branch-delete-modal">
        <div class="branch-delete-icon">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M3 6h18"></path>
                <path d="M8 6V4h8v2"></path>
                <path d="M19 6l-1 14H6L5 6"></path>
                <path d="M10 11v6"></path>
                <path d="M14 11v6"></path>
            </svg>
        </div>

        <h2>Non-aktifkan / Hapus Cabang?</h2>

        <p>
            Jika Cabang ini belum pernah terhubung dengan data lain (Pesanan, Staff, dll), maka Cabang akan <b>dihapus permanen</b>.
            <br><br>
            Namun jika sudah pernah terhubung, Cabang hanya akan <b>dinonaktifkan</b>.
        </p>

        <div class="branch-delete-modal-actions">
            <button type="button" class="branch-modal-cancel" id="branchDeleteCancel">
                Batal
            </button>

            <form method="POST" id="branchDeleteConfirmForm">
                @csrf
                @method('DELETE')

                <button type="submit" class="branch-modal-delete">
                    Lanjutkan
                </button>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('provider/js/branch.js') }}"></script>
@endsection


