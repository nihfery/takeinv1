@extends('admin.layouts.app')

@section('title', 'Users - JasaKu')
@section('page_title', 'Users')

@section('content')
@php
    $userCollection = $users ?? collect();
    $hasPaginator = is_object($userCollection)
        && method_exists($userCollection, 'links')
        && method_exists($userCollection, 'firstItem');

    $firstItem = $hasPaginator ? ($userCollection->firstItem() ?? 0) : 0;
    $lastItem = $hasPaginator ? ($userCollection->lastItem() ?? 0) : (is_countable($userCollection) ? count($userCollection) : 0);
    $totalItem = $hasPaginator ? $userCollection->total() : (is_countable($userCollection) ? count($userCollection) : 0);

    $filters = $filters ?? [
        'status' => request('status', 'all'),
        'gender' => request('gender', 'all'),
        'search' => request('search', $search ?? ''),
        'per_page' => request('per_page', $perPage ?? 10),
    ];

    $tabs = $tabs ?? [
        'all' => 'All Users',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    $genderOptions = [
        'all' => 'All Gender',
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ];

    $summary = $summary ?? [
        'total' => $totalItem,
        'active' => 0,
        'inactive' => 0,
        'profiles' => 0,
    ];

    $currentStatus = $filters['status'] ?? 'all';

    $cleanQuery = function (array $query) {
        return collect($query)
            ->reject(function ($value, $key) {
                if ($value === null || $value === '') {
                    return true;
                }

                if (in_array($key, ['status', 'gender'], true) && $value === 'all') {
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

    $statusLabel = fn ($value) => match ($value ?: 'active') {
        'active' => 'Active',
        'inactive' => 'Inactive',
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
        default => ucwords(str_replace('_', ' ', (string) $value)),
    };

    $statusClass = fn ($value) => match ($value ?: 'active') {
        'active' => 'success',
        'inactive' => 'danger',
        'male', 'female', 'other' => 'info',
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

    $avatarUrl = function ($profile) {
        if (! $profile || ! $profile->avatar) {
            return null;
        }

        return filter_var($profile->avatar, FILTER_VALIDATE_URL)
            ? $profile->avatar
            : asset('storage/' . ltrim($profile->avatar, '/'));
    };

    $hasActiveFilters = $hasActiveFilters ?? (($filters['status'] ?? 'all') !== 'all'
        || ($filters['gender'] ?? 'all') !== 'all'
        || ($filters['search'] ?? '') !== ''
        || (int) ($filters['per_page'] ?? 10) !== 10);

    $hasMobileAdvancedFilters = (($filters['gender'] ?? 'all') !== 'all')
        || ((int) ($filters['per_page'] ?? 10) !== 10);
@endphp

<section class="users-page admin-booking-page admin-people-page">
    <div class="admin-booking-route">
        <div class="admin-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>People</strong>
            <span>&rsaquo;</span>
            <strong>Users</strong>
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
            <span>Total Users</span>
            <strong>{{ number_format((int) $summary['total']) }}</strong>
            <small>Registered customers</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Active</span>
            <strong>{{ number_format((int) $summary['active']) }}</strong>
            <small>Accounts allowed to book</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Inactive</span>
            <strong>{{ number_format((int) $summary['inactive']) }}</strong>
            <small>Disabled customer accounts</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Profiles</span>
            <strong>{{ number_format((int) $summary['profiles']) }}</strong>
            <small>Customers with detailed profiles</small>
        </div>
    </div>

    <div class="admin-booking-card users-card admin-people-card">
        <div class="admin-booking-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.users.index', $queryFor(['status' => $key])) }}"
                   class="admin-booking-tab {{ ($currentStatus === $key || ($key === 'all' && empty($currentStatus))) ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="admin-booking-filter-panel compact {{ $hasMobileAdvancedFilters ? 'is-expanded' : '' }}">
            @if (!empty($currentStatus) && $currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif

            <div class="admin-booking-filter-row" id="userFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="userSearchInput"
                               type="text"
                               name="search"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Search user">
                    </div>
                </label>

                <button type="submit" class="admin-booking-mobile-search-submit" aria-label="Search user">
                    Search
                </button>

                <button type="button"
                        class="admin-booking-mobile-filter-toggle {{ $hasMobileAdvancedFilters ? 'active' : '' }}"
                        aria-controls="userFilterRow"
                        aria-expanded="{{ $hasMobileAdvancedFilters ? 'true' : 'false' }}">
                    Filter
                </button>

                <label class="admin-booking-field mini">
                    <select name="gender" aria-label="Gender" title="Gender">
                        @foreach ($genderOptions as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['gender'] ?? 'all') === $key ? 'selected' : '' }}>
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
                        <a href="{{ route('admin.users.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} user</span>

                @if (($filters['search'] ?? '') !== '')
                    <span>Search: {{ $filters['search'] }}</span>
                @endif

                @if (($filters['status'] ?? 'all') !== 'all')
                    <span>Status: {{ $statusLabel($filters['status']) }}</span>
                @endif

                @if (($filters['gender'] ?? 'all') !== 'all')
                    <span>Gender: {{ $genderOptions[$filters['gender']] ?? $statusLabel($filters['gender']) }}</span>
                @endif
            </div>
        </form>

        <div class="user-mobile-list admin-booking-mobile-list">
            @forelse ($userCollection as $customer)
                @php
                    $profile = $customer->customerProfile;
                    $status = $profile->status ?? 'active';
                    $imageUrl = $avatarUrl($profile);
                    $initial = strtoupper(substr($customer->name ?? 'U', 0, 1));
                @endphp

                <article class="user-mobile-card admin-booking-mobile-card">
                    <header>
                        <div class="admin-people-mobile-title">
                            <span class="user-avatar">
                                @if ($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $customer->name }}">
                                @else
                                    {{ $initial }}
                                @endif
                            </span>

                            <div>
                                <strong>{{ $customer->name }}</strong>
                                <span>{{ $customer->email }}</span>
                            </div>
                        </div>

                        <b>{{ $statusLabel($status) }}</b>
                    </header>

                    <div class="admin-booking-mobile-main">
                        <div>
                            <span>Phone</span>
                            <strong>{{ $profile->phone_number ?? '-' }}</strong>
                        </div>

                        <div>
                            <span>Gender</span>
                            <strong>{{ ($profile->gender ?? null) ? $statusLabel($profile->gender) : '-' }}</strong>
                        </div>
                    </div>

                    <p>
                        {{ collect([$profile->city ?? null, $profile->state ?? null, $profile->country ?? null])->filter()->implode(', ') ?: 'Customer address is incomplete.' }}
                    </p>

                    <footer class="admin-people-mobile-footer">
                        <span class="admin-booking-status {{ $statusClass($status) }}">
                            {{ $statusLabel($status) }}
                        </span>

                        <div class="user-actions">
                            <a href="{{ route('admin.users.show', $customer->id) }}" class="user-action-btn" title="View">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M2.5 12C4.5 7.8 8 5.5 12 5.5C16 5.5 19.5 7.8 21.5 12C19.5 16.2 16 18.5 12 18.5C8 18.5 4.5 16.2 2.5 12Z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </a>

                            <form action="{{ route('admin.users.destroy', $customer->id) }}" method="POST" data-delete-form data-delete-title="Delete User?" data-delete-item="{{ $customer->name }}" data-delete-message="This user will be removed from the customer list. Customer profile data will also be deleted.">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="user-action-btn danger" title="Delete" aria-label="Delete user {{ $customer->name }}">
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
                <div class="user-mobile-empty admin-booking-mobile-empty">
                    <strong>No user data found.</strong>
                    <p>Try changing the keyword, account status, or gender.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap users-table-wrap">
            <table class="admin-booking-table detailed users-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($userCollection as $customer)
                        @php
                            $profile = $customer->customerProfile;
                            $status = $profile->status ?? 'active';
                            $imageUrl = $avatarUrl($profile);
                            $initial = strtoupper(substr($customer->name ?? 'U', 0, 1));
                        @endphp

                        <tr>
                            <td>
                                <div class="user-name-box">
                                    <div class="user-avatar">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="{{ $customer->name }}">
                                        @else
                                            {{ $initial }}
                                        @endif
                                    </div>

                                    <div class="user-name-text">
                                        <strong>{{ $customer->name }}</strong>
                                        <small>{{ $customer->username ?? 'Customer account' }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-people-stack">
                                    <strong>{{ $customer->email }}</strong>
                                    <small>{{ $customer->email_verified_at ? 'Email verified' : 'Email not verified' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-people-stack">
                                    <strong>{{ $profile->phone_number ?? '-' }}</strong>
                                    <small>Primary phone</small>
                                </div>
                            </td>

                            <td>
                                <span class="admin-booking-status {{ $statusClass($profile->gender ?? null) }}">
                                    {{ ($profile->gender ?? null) ? $statusLabel($profile->gender) : 'Not Set' }}
                                </span>
                            </td>

                            <td>
                                <form
                                    action="{{ route('admin.users.toggle-status', $customer->id) }}"
                                    method="POST"
                                    class="account-toggle-form"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <button
                                        type="submit"
                                        class="account-toggle {{ $status === 'active' ? 'active' : '' }}"
                                        title="Toggle user status"
                                    >
                                        <span></span>
                                    </button>
                                </form>
                            </td>

                            <td>
                                <div class="admin-people-stack">
                                    <strong>{{ $formatDate($customer->created_at) }}</strong>
                                    <small>{{ $customer->created_at?->format('H:i') ?? '-' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="user-actions">
                                    <a href="{{ route('admin.users.show', $customer->id) }}" class="user-action-btn" title="View">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M2.5 12C4.5 7.8 8 5.5 12 5.5C16 5.5 19.5 7.8 21.5 12C19.5 16.2 16 18.5 12 18.5C8 18.5 4.5 16.2 2.5 12Z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>

                                    <form action="{{ route('admin.users.destroy', $customer->id) }}" method="POST" data-delete-form data-delete-title="Delete User?" data-delete-item="{{ $customer->name }}" data-delete-message="This user will be removed from the customer list. Customer profile data will also be deleted.">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="user-action-btn danger" title="Delete" aria-label="Delete user {{ $customer->name }}">
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
                    @empty
                        <tr>
                            <td colspan="7" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </span>

                                    <strong>No user data found.</strong>
                                    <p>Try changing the keyword, account status, or gender.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer users-footer">
            <p class="admin-booking-showing">
                <strong>{{ number_format($firstItem) }}-{{ number_format($lastItem) }}</strong>
                <span>/ {{ number_format($totalItem) }}</span>
            </p>

            @if ($hasPaginator)
                <div class="admin-booking-pagination users-pagination">
                    @if ($userCollection->onFirstPage())
                        <span class="disabled">&lsaquo;</span>
                    @else
                        <a href="{{ $userCollection->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
                    @endif

                    <span class="active">{{ $userCollection->currentPage() }}</span>

                    @if ($userCollection->hasMorePages())
                        <a href="{{ $userCollection->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
                    @else
                        <span class="disabled">&rsaquo;</span>
                    @endif
                </div>
            @else
                <div class="admin-booking-pagination users-pagination static">
                    <span class="active">1</span>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('admin/js/users.js') }}"></script>
@endpush
