@extends('admin.layouts.app')

@section('title', 'Providers - JasaKu')
@section('page_title', 'Providers')

@section('content')
@php
    $providerCollection = $providers ?? collect();
    $hasPaginator = is_object($providerCollection)
        && method_exists($providerCollection, 'links')
        && method_exists($providerCollection, 'firstItem');

    $firstItem = $hasPaginator ? ($providerCollection->firstItem() ?? 0) : 0;
    $lastItem = $hasPaginator ? ($providerCollection->lastItem() ?? 0) : (is_countable($providerCollection) ? count($providerCollection) : 0);
    $totalItem = $hasPaginator ? $providerCollection->total() : (is_countable($providerCollection) ? count($providerCollection) : 0);

    $filters = $filters ?? [
        'status' => request('status', 'all'),
        'document_status' => request('document_status', 'all'),
        'search' => request('search', $search ?? ''),
        'per_page' => request('per_page', $perPage ?? 10),
    ];

    $tabs = $tabs ?? [
        'all' => 'All Providers',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    $documentStatuses = [
        'all' => 'All Documents',
        'pending' => 'Pending',
        'submitted' => 'Submitted',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ];

    $summary = $summary ?? [
        'total' => $totalItem,
        'active' => 0,
        'verified' => 0,
        'branches' => 0,
    ];

    $currentStatus = $filters['status'] ?? 'all';

    $cleanQuery = function (array $query) {
        return collect($query)
            ->reject(function ($value, $key) {
                if ($value === null || $value === '') {
                    return true;
                }

                if (in_array($key, ['status', 'document_status'], true) && $value === 'all') {
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

    $statusLabel = fn ($value) => match ($value ?: 'pending') {
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
        'submitted' => 'Submitted',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        default => ucwords(str_replace('_', ' ', (string) $value)),
    };

    $statusClass = fn ($value) => match ($value ?: 'pending') {
        'active', 'verified' => 'success',
        'pending', 'submitted' => 'warning',
        'inactive', 'rejected' => 'danger',
        default => 'neutral',
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

    $profileImageUrl = function ($profile) {
        if (! $profile || ! $profile->image) {
            return null;
        }

        return filter_var($profile->image, FILTER_VALIDATE_URL)
            ? $profile->image
            : asset('storage/' . ltrim($profile->image, '/'));
    };

    $hasActiveFilters = $hasActiveFilters ?? (($filters['status'] ?? 'all') !== 'all'
        || ($filters['document_status'] ?? 'all') !== 'all'
        || ($filters['search'] ?? '') !== ''
        || (int) ($filters['per_page'] ?? 10) !== 10);

    $hasMobileAdvancedFilters = (($filters['document_status'] ?? 'all') !== 'all')
        || ((int) ($filters['per_page'] ?? 10) !== 10);
@endphp

<section class="providers-page admin-booking-page admin-people-page">
    <div class="admin-booking-route">
        <div class="admin-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>People</strong>
            <span>&rsaquo;</span>
            <strong>Providers</strong>
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

    <div class="admin-booking-summary-grid admin-people-summary-grid">
        <div class="admin-booking-summary-card pink">
            <span>Total Providers</span>
            <strong>{{ number_format((int) $summary['total']) }}</strong>
            <small>Registered main providers</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Active</span>
            <strong>{{ number_format((int) $summary['active']) }}</strong>
            <small>Accounts allowed to access the dashboard</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Verified</span>
            <strong>{{ number_format((int) $summary['verified']) }}</strong>
            <small>Valid provider documents</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Branch Accounts</span>
            <strong>{{ number_format((int) $summary['branches']) }}</strong>
            <small>Total provider branch accounts</small>
        </div>
    </div>

    <div class="admin-booking-card providers-card admin-people-card">
        <div class="admin-booking-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.providers.index', $queryFor(['status' => $key])) }}"
                   class="admin-booking-tab {{ ($currentStatus === $key || ($key === 'all' && empty($currentStatus))) ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.providers.index') }}" class="admin-booking-filter-panel compact {{ $hasMobileAdvancedFilters ? 'is-expanded' : '' }}">
            @if (!empty($currentStatus) && $currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif

            <div class="admin-booking-filter-row" id="providerFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="providerSearchInput"
                               type="text"
                               name="search"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Search provider">
                    </div>
                </label>

                <button type="submit" class="admin-booking-mobile-search-submit" aria-label="Search provider">
                    Search
                </button>

                <button type="button"
                        class="admin-booking-mobile-filter-toggle {{ $hasMobileAdvancedFilters ? 'active' : '' }}"
                        aria-controls="providerFilterRow"
                        aria-expanded="{{ $hasMobileAdvancedFilters ? 'true' : 'false' }}">
                    Filter
                </button>

                <label class="admin-booking-field mini">
                    <select name="document_status" aria-label="Document status" title="Document status">
                        @foreach ($documentStatuses as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['document_status'] ?? 'all') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-booking-field count">
                    <select name="per_page" aria-label="Rows per page" title="Rows per page">
                        <option value="10" {{ (int) ($filters['per_page'] ?? 10) === 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ (int) ($filters['per_page'] ?? 10) === 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ (int) ($filters['per_page'] ?? 10) === 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ (int) ($filters['per_page'] ?? 10) === 100 ? 'selected' : '' }}>100</option>
                    </select>
                </label>

                <div class="admin-booking-filter-buttons">
                    <button type="submit">Filter</button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('admin.providers.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} provider</span>

                @if (($filters['search'] ?? '') !== '')
                    <span>Search: {{ $filters['search'] }}</span>
                @endif

                @if (($filters['status'] ?? 'all') !== 'all')
                    <span>Status: {{ $statusLabel($filters['status']) }}</span>
                @endif

                @if (($filters['document_status'] ?? 'all') !== 'all')
                    <span>Docs: {{ $documentStatuses[$filters['document_status']] ?? $statusLabel($filters['document_status']) }}</span>
                @endif
            </div>
        </form>

        <div class="provider-mobile-list admin-booking-mobile-list">
            @forelse ($providerCollection as $provider)
                @php
                    $profile = $provider->providerProfile;
                    $branchAccounts = $provider->branchAccounts ?? collect();
                    $accountStatus = $profile->status ?? 'inactive';
                    $documentStatus = $profile->document_status ?? 'pending';
                    $imageUrl = $profileImageUrl($profile);
                    $initial = strtoupper(substr($provider->name ?? 'P', 0, 1));
                @endphp

                <article class="provider-mobile-card admin-booking-mobile-card">
                    <header>
                        <div class="admin-people-mobile-title">
                            <span class="provider-avatar">
                                @if ($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $provider->name }}">
                                @else
                                    {{ $initial }}
                                @endif
                            </span>

                            <div>
                                <strong>{{ $provider->name }}</strong>
                                <span>{{ $provider->email }}</span>
                            </div>
                        </div>

                        <b>{{ $branchAccounts->count() }} branch</b>
                    </header>

                    <div class="admin-booking-mobile-main">
                        <div>
                            <span>Phone</span>
                            <strong>{{ $profile->phone_number ?? '-' }}</strong>
                        </div>

                        <div>
                            <span>Category</span>
                            <strong>{{ $profile->category ?? '-' }}</strong>
                        </div>
                    </div>

                    <p>
                        {{ $branchAccounts->isEmpty()
                            ? 'No branch accounts yet.'
                            : $branchAccounts->take(2)->pluck('name')->join(', ') . ($branchAccounts->count() > 2 ? ' +' . ($branchAccounts->count() - 2) . ' more' : '') }}
                    </p>

                    <footer class="admin-people-mobile-footer">
                        <span class="admin-booking-status {{ $statusClass($accountStatus) }}">
                            {{ $statusLabel($accountStatus) }}
                        </span>
                        <span class="admin-booking-status {{ $statusClass($documentStatus) }}">
                            {{ $statusLabel($documentStatus) }}
                        </span>

                        <div class="provider-actions">
                            <a href="{{ route('admin.providers.show', $provider->id) }}" class="provider-action-btn" title="View">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M2.5 12C4.5 7.8 8 5.5 12 5.5C16 5.5 19.5 7.8 21.5 12C19.5 16.2 16 18.5 12 18.5C8 18.5 4.5 16.2 2.5 12Z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </a>

                            <form action="{{ route('admin.providers.destroy', $provider->id) }}" method="POST" data-delete-form data-delete-title="Delete Provider?" data-delete-item="{{ $provider->name }}" data-delete-message="This provider will be removed from the list. Provider profile and document data will also be deleted.">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="provider-action-btn danger" title="Delete" aria-label="Delete provider {{ $provider->name }}">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M5 7h14"></path>
                                        <path d="M9 7V5h6v2"></path>
                                        <path d="M8 10v8"></path>
                                        <path d="M12 10v8"></path>
                                        <path d="M16 10v8"></path>
                                        <path d="M7 7l1 14h8l1-14"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </footer>
                </article>
            @empty
                <div class="provider-mobile-empty admin-booking-mobile-empty">
                    <strong>No provider data found.</strong>
                    <p>Try changing the keyword, account status, or document status.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap providers-table-wrap">
            <table class="admin-booking-table detailed providers-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Contact</th>
                        <th>Category</th>
                        <th>Branches</th>
                        <th>Account</th>
                        <th>Documents</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($providerCollection as $provider)
                        @php
                            $profile = $provider->providerProfile;
                            $branchAccounts = $provider->branchAccounts ?? collect();
                            $accountStatus = $profile->status ?? 'inactive';
                            $documentStatus = $profile->document_status ?? 'pending';
                            $imageUrl = $profileImageUrl($profile);
                            $initial = strtoupper(substr($provider->name ?? 'P', 0, 1));
                        @endphp

                        <tr class="provider-parent-row">
                            <td>
                                <div class="provider-name-box">
                                    <button
                                        type="button"
                                        class="provider-expand-btn"
                                        data-provider-toggle="{{ $provider->id }}"
                                        aria-expanded="false"
                                        aria-controls="providerBranches-{{ $provider->id }}"
                                        @disabled($branchAccounts->isEmpty())
                                    >
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M9 6l6 6-6 6"></path>
                                        </svg>
                                    </button>

                                    <div class="provider-avatar">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="{{ $provider->name }}">
                                        @else
                                            {{ $initial }}
                                        @endif
                                    </div>

                                    <div class="provider-name-text">
                                        <strong>{{ $provider->name }}</strong>
                                        <small>{{ $provider->username ?? 'Provider account' }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-people-stack">
                                    <strong>{{ $provider->email }}</strong>
                                    <small>{{ $profile->phone_number ?? 'No phone' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-people-stack">
                                    <strong>{{ $profile->category ?? '-' }}</strong>
                                    <small>Service category</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-people-stack center">
                                    <strong>{{ number_format($branchAccounts->count()) }}</strong>
                                    <small>branch account</small>
                                </div>
                            </td>

                            <td>
                                <form
                                    action="{{ route('admin.providers.toggle-status', $provider->id) }}"
                                    method="POST"
                                    class="account-toggle-form"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <button
                                        type="submit"
                                        class="account-toggle {{ $accountStatus === 'active' ? 'active' : '' }}"
                                        title="Toggle account status"
                                    >
                                        <span></span>
                                    </button>
                                </form>
                            </td>

                            <td>
                                <a href="{{ route('admin.providers.show', $provider->id) }}" class="admin-booking-status {{ $statusClass($documentStatus) }}" title="Review dokumen provider">
                                    {{ $statusLabel($documentStatus) }}
                                </a>
                            </td>

                            <td>
                                <div class="admin-people-stack">
                                    <strong>{{ $formatDate($provider->created_at) }}</strong>
                                    <small>{{ $provider->created_at?->format('H:i') ?? '-' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="provider-actions">
                                    <a href="{{ route('admin.providers.show', $provider->id) }}" class="provider-action-btn" title="View">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M2.5 12C4.5 7.8 8 5.5 12 5.5C16 5.5 19.5 7.8 21.5 12C19.5 16.2 16 18.5 12 18.5C8 18.5 4.5 16.2 2.5 12Z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>

                                    <form action="{{ route('admin.providers.destroy', $provider->id) }}" method="POST" data-delete-form data-delete-title="Delete Provider?" data-delete-item="{{ $provider->name }}" data-delete-message="This provider will be removed from the list. Provider profile and document data will also be deleted.">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="provider-action-btn danger" title="Delete" aria-label="Delete provider {{ $provider->name }}">
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M5 7h14"></path>
                                                <path d="M9 7V5h6v2"></path>
                                                <path d="M8 10v8"></path>
                                                <path d="M12 10v8"></path>
                                                <path d="M16 10v8"></path>
                                                <path d="M7 7l1 14h8l1-14"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <tr class="provider-branches-row" id="providerBranches-{{ $provider->id }}" hidden>
                            <td colspan="8">
                                <div class="provider-branch-panel">
                                    <div class="provider-branch-panel-head">
                                        <div>
                                            <strong>Branch Accounts</strong>
                                            <span>{{ $provider->name }}</span>
                                        </div>

                                        <span>{{ $branchAccounts->count() }} accounts</span>
                                    </div>

                                    @if ($branchAccounts->isEmpty())
                                        <div class="provider-branch-empty">
                                            This main provider does not have branch accounts yet.
                                        </div>
                                    @else
                                        <div class="provider-branch-list">
                                            <div class="provider-branch-line head">
                                                <span>Account</span>
                                                <span>Branch</span>
                                                <span>Role</span>
                                                <span>Menu</span>
                                                <span>Status</span>
                                            </div>

                                            @foreach ($branchAccounts as $account)
                                                @php
                                                    $branchName = $account->providerBranch->branch_name ?? 'No branch selected';
                                                    $roleName = $account->providerRole->role_name ?? 'No role selected';
                                                    $roleStatus = $account->providerRole?->status ?? 'inactive';
                                                    $menuCount = $account->providerRole?->menuPermissions?->count() ?? 0;
                                                @endphp

                                                <div class="provider-branch-line">
                                                    <div class="provider-branch-account">
                                                        <strong>{{ $account->name }}</strong>
                                                        <small>{{ $account->email }}</small>
                                                    </div>

                                                    <span>{{ $branchName }}</span>
                                                    <span>{{ $roleName }}</span>
                                                    <span>{{ $menuCount }} menu</span>
                                                    <span class="provider-branch-status {{ $roleStatus }}">
                                                        {{ ucfirst($roleStatus) }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                    </span>

                                    <strong>No provider data found.</strong>
                                    <p>Try changing the keyword, account status, or document status.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer providers-footer">
            <p class="admin-booking-showing">
                <strong>{{ number_format($firstItem) }}-{{ number_format($lastItem) }}</strong>
                <span>/ {{ number_format($totalItem) }}</span>
            </p>

            @if ($hasPaginator)
                <div class="admin-booking-pagination providers-pagination">
                    @if ($providerCollection->onFirstPage())
                        <span class="disabled">&lsaquo;</span>
                    @else
                        <a href="{{ $providerCollection->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
                    @endif

                    <span class="active">{{ $providerCollection->currentPage() }}</span>

                    @if ($providerCollection->hasMorePages())
                        <a href="{{ $providerCollection->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
                    @else
                        <span class="disabled">&rsaquo;</span>
                    @endif
                </div>
            @else
                <div class="admin-booking-pagination providers-pagination static">
                    <span class="active">1</span>
                </div>
            @endif
        </div>
    </div>
</section>

@endsection

@push('scripts')
    <script src="{{ asset('admin/js/providers.js') }}"></script>
@endpush
