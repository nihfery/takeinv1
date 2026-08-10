@extends('admin.layouts.app')

@section('title', 'Coupons - JasaKu')
@section('page_title', 'Coupons')

@section('content')
@php
    $couponCollection = $coupons ?? collect();
    $hasPaginator = is_object($couponCollection)
        && method_exists($couponCollection, 'links')
        && method_exists($couponCollection, 'firstItem');

    $firstItem = $hasPaginator ? ($couponCollection->firstItem() ?? 0) : 0;
    $lastItem = $hasPaginator ? ($couponCollection->lastItem() ?? 0) : (is_countable($couponCollection) ? count($couponCollection) : 0);
    $totalItem = $hasPaginator ? $couponCollection->total() : (is_countable($couponCollection) ? count($couponCollection) : 0);

    $filters = $filters ?? [
        'tab' => request('tab', $tab ?? 'all'),
        'product_type' => request('product_type', 'any'),
        'coupon_type' => request('coupon_type', 'all'),
        'date' => request('date'),
        'search' => request('search', $search ?? ''),
        'per_page' => request('per_page', $perPage ?? 10),
        'sort_by' => request('sort_by', 'created_at'),
        'sort_direction' => request('sort_direction', 'desc'),
    ];

    $tabs = $tabs ?? [
        'all' => 'All Coupon',
        'valid' => 'Valid',
        'inactive' => 'Inactive',
        'expired' => 'Expired',
    ];

    $productTypes = [
        'any' => 'All Scopes',
        'all' => 'All Services',
        'service' => 'Service',
        'category' => 'Category',
    ];

    $couponTypes = [
        'all' => 'All Types',
        'percentage' => 'Percentage',
        'fixed' => 'Fixed Amount',
    ];

    $summary = $summary ?? [
        'total' => $totalItem,
        'valid' => 0,
        'expired' => 0,
        'redeemed' => 0,
    ];

    $currentTab = $filters['tab'] ?? 'all';
    $sortBy = $sortBy ?? ($filters['sort_by'] ?? 'created_at');
    $sortDirection = $sortDirection ?? ($filters['sort_direction'] ?? 'desc');

    $cleanQuery = function (array $query) {
        return collect($query)
            ->reject(function ($value, $key) {
                if ($value === null || $value === '') {
                    return true;
                }

                if ($key === 'product_type' && $value === 'any') {
                    return true;
                }

                if (in_array($key, ['tab', 'coupon_type'], true) && $value === 'all') {
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

    $couponValue = function ($coupon) {
        return $coupon->coupon_type === 'percentage'
            ? number_format((float) $coupon->coupon_value, 0) . '%'
            : 'Rp ' . number_format((float) $coupon->coupon_value, 0, ',', '.');
    };

    $couponTypeLabel = fn ($value) => $couponTypes[$value ?: 'percentage'] ?? ucwords(str_replace('_', ' ', (string) $value));
    $scopeLabel = fn ($value) => match ($value ?: 'all') {
        'service' => 'Selected Services',
        'category' => 'Selected Categories',
        default => 'All Services',
    };

    $scopeDetail = function ($coupon) {
        if (($coupon->product_type ?? 'all') === 'all') {
            return 'Applies to all services';
        }

        $count = count($coupon->product_ids ?: []);
        $label = $coupon->product_type === 'category' ? 'category' : 'service';

        return number_format($count) . ' ' . $label . ' selected';
    };

    $couponState = function ($coupon) {
        $today = now()->startOfDay();

        if ($coupon->end_date && $coupon->end_date->lt($today)) {
            return 'expired';
        }

        if (($coupon->status ?? 'inactive') !== 'active') {
            return 'inactive';
        }

        if ($coupon->start_date && $coupon->start_date->gt($today)) {
            return 'scheduled';
        }

        return 'active';
    };

    $couponStateLabel = fn ($state) => match ($state) {
        'active' => 'Valid',
        'scheduled' => 'Scheduled',
        'inactive' => 'Inactive',
        'expired' => 'Expired',
        default => ucwords(str_replace('_', ' ', (string) $state)),
    };

    $couponStateClass = fn ($state) => match ($state) {
        'active' => 'success',
        'scheduled' => 'info',
        'inactive' => 'warning',
        'expired' => 'danger',
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

    $formatDateTime = function ($value) {
        if (empty($value)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d M Y H:i');
        } catch (\Throwable $exception) {
            return '-';
        }
    };

    $usagePercent = function ($coupon) {
        $quantity = (int) ($coupon->quantity ?? 0);

        if ($quantity <= 0) {
            return 0;
        }

        return min(100, (int) round(((int) $coupon->used_count / $quantity) * 100));
    };

    $hasActiveFilters = $hasActiveFilters ?? (($filters['tab'] ?? 'all') !== 'all'
        || ($filters['search'] ?? '') !== ''
        || ($filters['product_type'] ?? 'any') !== 'any'
        || ($filters['coupon_type'] ?? 'all') !== 'all'
        || ! empty($filters['date'])
        || (int) ($filters['per_page'] ?? 10) !== 10
        || ($filters['sort_by'] ?? 'created_at') !== 'created_at'
        || ($filters['sort_direction'] ?? 'desc') !== 'desc');

    $hasMobileAdvancedFilters = (($filters['product_type'] ?? 'any') !== 'any')
        || (($filters['coupon_type'] ?? 'all') !== 'all')
        || ! empty($filters['date'])
        || ((int) ($filters['per_page'] ?? 10) !== 10);
@endphp

<section class="admin-coupon-page admin-booking-page">
    <div class="admin-booking-route admin-coupon-route">
        <div class="admin-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Coupons</strong>
        </div>

        <a href="{{ route('admin.coupons.create') }}" class="admin-coupon-primary admin-coupon-primary-desktop">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 5v14"></path>
                <path d="M5 12h14"></path>
            </svg>
            Add Coupon
        </a>
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

    <div class="admin-booking-summary-grid admin-coupon-summary-grid">
        <div class="admin-booking-summary-card pink">
            <span>Total Coupon</span>
            <strong>{{ number_format((int) $summary['total']) }}</strong>
            <small>All promo codes</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Valid</span>
            <strong>{{ number_format((int) $summary['valid']) }}</strong>
            <small>Active and not expired</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Redeemed</span>
            <strong>{{ number_format((int) $summary['redeemed']) }}</strong>
            <small>Total coupon redemptions</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Expired</span>
            <strong>{{ number_format((int) $summary['expired']) }}</strong>
            <small>Codes past their expiry date</small>
        </div>
    </div>

    <div class="admin-booking-card admin-coupon-card-shell">
        <div class="admin-booking-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.coupons.index', $queryFor(['tab' => $key])) }}"
                   class="admin-booking-tab {{ ($currentTab === $key || ($key === 'all' && empty($currentTab))) ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.coupons.index') }}" class="admin-booking-filter-panel compact {{ $hasMobileAdvancedFilters ? 'is-expanded' : '' }}">
            @if (!empty($currentTab) && $currentTab !== 'all')
                <input type="hidden" name="tab" value="{{ $currentTab }}">
            @endif

            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
            <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">

            <div class="admin-booking-filter-row" id="couponFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="couponSearchInput"
                               type="text"
                               name="search"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Search coupon">
                    </div>
                </label>

                <button type="submit" class="admin-booking-mobile-search-submit" aria-label="Search coupon">
                    Search
                </button>

                <button type="button"
                        class="admin-booking-mobile-filter-toggle {{ $hasMobileAdvancedFilters ? 'active' : '' }}"
                        aria-controls="couponFilterRow"
                        aria-expanded="{{ $hasMobileAdvancedFilters ? 'true' : 'false' }}">
                    Filter
                </button>

                <label class="admin-booking-field mini">
                    <select name="product_type" aria-label="Coupon scope" title="Coupon scope">
                        @foreach ($productTypes as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['product_type'] ?? 'any') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-booking-field mini">
                    <select name="coupon_type" aria-label="Coupon type" title="Coupon type">
                        @foreach ($couponTypes as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['coupon_type'] ?? 'all') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-booking-field mini">
                    <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" aria-label="Valid date" title="Valid date">
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
                        <a href="{{ route('admin.coupons.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} coupon</span>

                @if (($filters['search'] ?? '') !== '')
                    <span>Search: {{ $filters['search'] }}</span>
                @endif

                @if (($filters['tab'] ?? 'all') !== 'all')
                    <span>Status: {{ $tabs[$filters['tab']] ?? $filters['tab'] }}</span>
                @endif

                @if (($filters['product_type'] ?? 'any') !== 'any')
                    <span>Scope: {{ $productTypes[$filters['product_type']] ?? $filters['product_type'] }}</span>
                @endif

                @if (($filters['coupon_type'] ?? 'all') !== 'all')
                    <span>Type: {{ $couponTypes[$filters['coupon_type']] ?? $filters['coupon_type'] }}</span>
                @endif

                @if (!empty($filters['date']))
                    <span>Date: {{ $filters['date'] }}</span>
                @endif
            </div>
        </form>

        <div class="admin-coupon-add-row">
            <a href="{{ route('admin.coupons.create') }}" class="admin-coupon-primary admin-coupon-primary-mobile">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Add Coupon
            </a>
        </div>

        <div class="admin-coupon-mobile-list admin-booking-mobile-list">
            @forelse ($couponCollection as $coupon)
                @php
                    $state = $couponState($coupon);
                    $used = (int) ($coupon->used_count ?? 0);
                    $quantity = $coupon->quantity;
                @endphp

                <article class="admin-coupon-mobile-card admin-booking-mobile-card">
                    <header>
                        <div>
                            <strong>{{ $coupon->code }}</strong>
                            <span>{{ $couponTypeLabel($coupon->coupon_type) }} &middot; {{ $scopeLabel($coupon->product_type) }}</span>
                        </div>

                        <b>{{ $couponValue($coupon) }}</b>
                    </header>

                    <div class="admin-booking-mobile-main">
                        <div>
                            <span>Usage</span>
                            <strong>{{ $used }} / {{ $quantity ?? 'Unlimited' }}</strong>
                        </div>

                        <div>
                            <span>Valid Until</span>
                            <strong>{{ $formatDate($coupon->end_date) }}</strong>
                        </div>
                    </div>

                    <p>{{ $scopeDetail($coupon) }} &middot; Created {{ $formatDate($coupon->created_at) }}</p>

                    <footer class="admin-coupon-mobile-footer">
                        <span class="admin-booking-status {{ $couponStateClass($state) }}">
                            {{ $couponStateLabel($state) }}
                        </span>

                        <div class="admin-coupon-mobile-actions">
                            <a href="{{ route('admin.coupons.edit', $coupon->id) }}" class="admin-coupon-action" title="Edit">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                </svg>
                            </a>

                            <form action="{{ route('admin.coupons.destroy', $coupon->id) }}"
                                  method="POST"
                                  data-delete-form
                                  data-delete-title="Delete Coupon?"
                                  data-delete-item="{{ $coupon->code }}"
                                  data-delete-message="This coupon will be removed from the promo list and can no longer be used.">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="admin-coupon-action danger" title="Delete" aria-label="Delete coupon {{ $coupon->code }}">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4h8v2"></path>
                                        <path d="M6 6l1 15h10l1-15"></path>
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </footer>
                </article>
            @empty
                <div class="admin-coupon-mobile-empty admin-booking-mobile-empty">
                    <strong>No coupon data found.</strong>
                    <p>Try changing the keyword, status, scope, type, or coupon date.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap admin-coupon-table-wrap">
            <table class="admin-booking-table detailed admin-coupon-table">
                <thead>
                    <tr>
                        <th>
                            <a href="{{ route('admin.coupons.index', $sortQueryFor('code')) }}" class="admin-booking-sort {{ $sortBy === 'code' ? 'active' : '' }}">
                                Coupon
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('code', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('code', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.coupons.index', $sortQueryFor('product_type')) }}" class="admin-booking-sort {{ $sortBy === 'product_type' ? 'active' : '' }}">
                                Scope
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('product_type', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('product_type', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.coupons.index', $sortQueryFor('coupon_value')) }}" class="admin-booking-sort {{ $sortBy === 'coupon_value' ? 'active' : '' }}">
                                Discount
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('coupon_value', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('coupon_value', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.coupons.index', $sortQueryFor('used_count')) }}" class="admin-booking-sort {{ $sortBy === 'used_count' ? 'active' : '' }}">
                                Usage
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('used_count', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('used_count', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Remaining</th>
                        <th>
                            <a href="{{ route('admin.coupons.index', $sortQueryFor('end_date')) }}" class="admin-booking-sort {{ $sortBy === 'end_date' ? 'active' : '' }}">
                                Validity
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('end_date', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('end_date', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.coupons.index', $sortQueryFor('status')) }}" class="admin-booking-sort {{ $sortBy === 'status' ? 'active' : '' }}">
                                Status
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('status', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('status', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.coupons.index', $sortQueryFor('created_at')) }}" class="admin-booking-sort {{ $sortBy === 'created_at' ? 'active' : '' }}">
                                Created
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('created_at', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('created_at', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($couponCollection as $coupon)
                        @php
                            $state = $couponState($coupon);
                            $used = (int) ($coupon->used_count ?? 0);
                            $quantity = $coupon->quantity;
                            $remaining = $quantity === null ? null : max(0, (int) $quantity - $used);
                        @endphp

                        <tr>
                            <td>
                                <div class="admin-coupon-code-cell">
                                    <strong>{{ $coupon->code }}</strong>
                                    <small>ID {{ $coupon->id }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-coupon-scope-cell">
                                    <span>{{ $scopeLabel($coupon->product_type) }}</span>
                                    <small>{{ $scopeDetail($coupon) }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-coupon-discount-cell">
                                    <strong>{{ $couponValue($coupon) }}</strong>
                                    <small>{{ $couponTypeLabel($coupon->coupon_type) }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-coupon-usage-cell">
                                    <strong>{{ $used }} / {{ $quantity ?? 'Unlimited' }}</strong>
                                    <span class="admin-coupon-usage-bar">
                                        <i style="width: {{ $usagePercent($coupon) }}%"></i>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="admin-coupon-discount-cell">
                                    <strong>{{ $remaining === null ? 'Unlimited' : number_format($remaining) }}</strong>
                                    <small>{{ $remaining === null ? 'No quantity limit' : 'coupons remaining' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-coupon-date-cell">
                                    <strong>{{ $formatDate($coupon->start_date) }}</strong>
                                    <small>Until {{ $formatDate($coupon->end_date) }}</small>
                                </div>
                            </td>

                            <td>
                                <span class="admin-booking-status {{ $couponStateClass($state) }}">
                                    {{ $couponStateLabel($state) }}
                                </span>
                            </td>

                            <td>
                                <div class="admin-coupon-date-cell">
                                    <strong>{{ $formatDate($coupon->created_at) }}</strong>
                                    <small>{{ $formatDateTime($coupon->created_at) }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-coupon-actions">
                                    <a href="{{ route('admin.coupons.edit', $coupon->id) }}" class="admin-coupon-action" title="Edit">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                        </svg>
                                    </a>

                                    <form action="{{ route('admin.coupons.destroy', $coupon->id) }}"
                                          method="POST"
                                          data-delete-form
                                          data-delete-title="Delete Coupon?"
                                          data-delete-item="{{ $coupon->code }}"
                                          data-delete-message="This coupon will be removed from the promo list and can no longer be used.">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="admin-coupon-action danger" title="Delete" aria-label="Delete coupon {{ $coupon->code }}">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M3 6h18"></path>
                                                <path d="M8 6V4h8v2"></path>
                                                <path d="M6 6l1 15h10l1-15"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7z"></path>
                                        </svg>
                                    </span>

                                    <strong>No coupon data found.</strong>
                                    <p>Try changing the keyword, status, scope, type, or coupon date.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer">
            <p class="admin-booking-showing">
                <strong>{{ number_format($firstItem) }}-{{ number_format($lastItem) }}</strong>
                <span>/ {{ number_format($totalItem) }}</span>
            </p>

            @if ($hasPaginator)
                <div class="admin-booking-pagination">
                    @if ($couponCollection->onFirstPage())
                        <span class="disabled">&lsaquo;</span>
                    @else
                        <a href="{{ $couponCollection->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
                    @endif

                    <span class="active">{{ $couponCollection->currentPage() }}</span>

                    @if ($couponCollection->hasMorePages())
                        <a href="{{ $couponCollection->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
                    @else
                        <span class="disabled">&rsaquo;</span>
                    @endif
                </div>
            @else
                <div class="admin-booking-pagination static">
                    <span class="active">1</span>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const page = document.querySelector('.admin-coupon-page');

    if (!page || !window.fetch || !window.DOMParser || !window.history) {
        return;
    }

    const card = page.querySelector('.admin-booking-card');
    const replaceSelectors = [
        '.admin-booking-tabs',
        '.admin-booking-filter-panel',
        '.admin-coupon-add-row',
        '.admin-coupon-mobile-list',
        '.admin-coupon-table-wrap',
        '.admin-booking-footer',
    ];
    let activeRequest = null;

    const applyLoading = (isLoading) => {
        if (!card) {
            return;
        }

        card.classList.toggle('is-loading', isLoading);
        card.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    };

    const shouldKeepParam = (key, value) => {
        if (value === null || value === '') {
            return false;
        }

        if (key === 'product_type' && value === 'any') {
            return false;
        }

        if (['tab', 'coupon_type'].includes(key) && value === 'all') {
            return false;
        }

        if (key === 'sort_by' && value === 'created_at') {
            return false;
        }

        if (key === 'sort_direction' && value === 'desc') {
            return false;
        }

        if (key === 'per_page' && value === '10') {
            return false;
        }

        return true;
    };

    const buildFilterUrl = (form) => {
        const url = new URL(form.action || window.location.href, window.location.origin);
        const formData = new FormData(form);

        url.search = '';

        formData.forEach((value, key) => {
            const normalized = String(value).trim();

            if (shouldKeepParam(key, normalized)) {
                url.searchParams.set(key, normalized);
            }
        });

        return url;
    };

    const closestFromEvent = (event, selector) => {
        return event.target instanceof Element ? event.target.closest(selector) : null;
    };

    const syncMobileFilterToggle = (form) => {
        const toggle = form.querySelector('.admin-booking-mobile-filter-toggle');

        if (!toggle) {
            return;
        }

        const isExpanded = form.classList.contains('is-expanded');
        toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        toggle.classList.toggle('active', isExpanded);
    };

    const replaceCouponParts = (html) => {
        const doc = new DOMParser().parseFromString(html, 'text/html');

        replaceSelectors.forEach((selector) => {
            const currentNode = page.querySelector(selector);
            const nextNode = doc.querySelector(selector);

            if (currentNode && nextNode) {
                currentNode.replaceWith(nextNode);
            }
        });

        const nextTitle = doc.querySelector('title');

        if (nextTitle) {
            document.title = nextTitle.textContent;
        }
    };

    const loadCoupons = async (url, options = {}) => {
        const shouldPush = options.push !== false;
        const controller = new AbortController();

        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = controller;
        applyLoading(true);

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(`Coupon filter failed with status ${response.status}`);
            }

            const html = await response.text();

            if (controller !== activeRequest) {
                return;
            }

            replaceCouponParts(html);

            if (shouldPush) {
                window.history.pushState({ adminCouponsAjax: true }, '', response.url);
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error(error);
            window.location.href = url.toString();
        } finally {
            if (controller === activeRequest) {
                activeRequest = null;
                applyLoading(false);
            }
        }
    };

    page.addEventListener('submit', (event) => {
        const form = closestFromEvent(event, '.admin-booking-filter-panel');

        if (!form) {
            return;
        }

        event.preventDefault();
        loadCoupons(buildFilterUrl(form));
    });

    page.addEventListener('click', (event) => {
        const toggle = closestFromEvent(event, '.admin-booking-mobile-filter-toggle');

        if (toggle) {
            const form = toggle.closest('.admin-booking-filter-panel');

            if (form) {
                event.preventDefault();
                form.classList.toggle('is-expanded');
                syncMobileFilterToggle(form);
            }

            return;
        }

        const link = closestFromEvent(event, '.admin-booking-tabs a, .admin-booking-sort, .admin-booking-pagination a, .admin-booking-filter-buttons a');

        if (!link) {
            return;
        }

        const url = new URL(link.href, window.location.origin);

        if (url.origin !== window.location.origin) {
            return;
        }

        event.preventDefault();
        loadCoupons(url);
    });

    window.addEventListener('popstate', () => {
        loadCoupons(new URL(window.location.href), { push: false });
    });
})();
</script>
@endpush
