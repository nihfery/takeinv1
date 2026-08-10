@extends('provider.layouts.dashboard')

@section('title', 'Payments - JasaKu')
@section('page_title', 'Payments')
@section('page_subtitle', 'Monitor transactions, payment status, payment methods, and related bookings.')

@section('content')
@php
    $filters = $filters ?? [
        'status' => request('status', 'all'),
        'payment_type' => request('payment_type', 'all'),
        'search' => request('search', ''),
        'date_from' => request('date_from'),
        'date_to' => request('date_to'),
        'per_page' => request('per_page', 25),
        'sort_by' => request('sort_by', 'created_at'),
        'sort_direction' => request('sort_direction', 'desc'),
    ];

    $paymentStatuses = $paymentStatuses ?? [
        'all' => 'All Payments',
        'unpaid' => 'Unpaid',
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'expired' => 'Expired',
    ];

    $paymentTypes = $paymentTypes ?? [
        'all' => 'All Types',
        'dp' => 'DP',
        'full_payment' => 'Full Payment',
        'pay_at_salon' => 'Pay at Salon',
    ];

    $paymentCollection = $payments ?? collect();
    $hasPaginator = is_object($paymentCollection)
        && method_exists($paymentCollection, 'links')
        && method_exists($paymentCollection, 'firstItem');
    $firstItem = $hasPaginator ? ($paymentCollection->firstItem() ?? 0) : 0;
    $lastItem = $hasPaginator ? ($paymentCollection->lastItem() ?? 0) : (is_countable($paymentCollection) ? count($paymentCollection) : 0);
    $totalItem = $hasPaginator ? $paymentCollection->total() : (is_countable($paymentCollection) ? count($paymentCollection) : 0);

    $summary = $summary ?? [
        'total' => $totalItem,
        'amount' => 0,
        'paid' => 0,
        'pending' => 0,
    ];
    $statusBreakdown = collect($statusBreakdown ?? []);
    $typeBreakdown = collect($typeBreakdown ?? []);
    $tabCounts = $tabCounts ?? [];
    $currentStatus = $filters['status'] ?? 'all';
    $sortBy = $filters['sort_by'] ?? 'created_at';
    $sortDirection = $filters['sort_direction'] ?? 'desc';

    $cleanQuery = function (array $query) {
        return collect($query)
            ->reject(function ($value, $key) {
                if ($value === null || $value === '') {
                    return true;
                }

                if (in_array($key, ['status', 'payment_type'], true) && $value === 'all') {
                    return true;
                }

                if ($key === 'sort_by' && $value === 'created_at') {
                    return true;
                }

                if ($key === 'sort_direction' && $value === 'desc') {
                    return true;
                }

                if ($key === 'per_page' && (int) $value === 25) {
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

    $statusLabels = [
        'dp' => 'DP',
        'full_payment' => 'Full Payment',
        'pay_at_salon' => 'Pay at Salon',
        'unpaid' => 'Unpaid',
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'expired' => 'Expired',
    ];

    $statusLabel = fn ($value) => $statusLabels[$value ?: 'pending'] ?? ucwords(str_replace('_', ' ', $value ?: 'pending'));
    $statusClass = function ($value) {
        return match ($value) {
            'paid', 'full_payment' => 'success',
            'pending', 'unpaid', 'dp' => 'warning',
            'pay_at_salon' => 'info',
            'failed' => 'danger',
            'refunded' => 'neutral',
            'expired' => 'danger',
            default => 'neutral',
        };
    };

    $formatDate = function ($value, string $format = 'd M Y H:i') {
        if (empty($value)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable $exception) {
            return '-';
        }
    };

    $hasMobileAdvancedFilters = (($filters['payment_type'] ?? 'all') !== 'all')
        || ! empty($filters['date_from'])
        || ! empty($filters['date_to'])
        || ((int) ($filters['per_page'] ?? 25) !== 25);
@endphp

<section class="admin-booking-page provider-payment-page">
    <div class="admin-booking-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Payments</strong>
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

    <div class="admin-booking-summary-grid">
        <div class="admin-booking-summary-card pink">
            <span>Total Payment</span>
            <strong>{{ number_format((int) ($summary['total'] ?? 0)) }}</strong>
            <small>Transactions based on the active filters</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Revenue</span>
            <strong>Rp{{ number_format((float) ($summary['amount'] ?? 0), 0, ',', '.') }}</strong>
            <small>Total payment amount</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Paid</span>
            <strong>{{ number_format((int) ($summary['paid'] ?? 0)) }}</strong>
            <small>Completed payments</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Pending</span>
            <strong>{{ number_format((int) ($summary['pending'] ?? 0)) }}</strong>
            <small>Unpaid or pending payments</small>
        </div>
    </div>

    <div class="admin-dashboard-split-grid provider-payment-overview-grid">
        <div class="admin-chart-card admin-panel-card">
            <div class="admin-chart-head">
                <h3>Payment status mix</h3>
            </div>

            <div class="admin-compact-list">
                @forelse ($statusBreakdown as $item)
                    <div>
                        <span>{{ $item['label'] }}</span>
                        <strong>{{ number_format((int) ($item['count'] ?? 0)) }}</strong>
                        <em>Rp{{ number_format((float) ($item['amount'] ?? 0), 0, ',', '.') }}</em>
                    </div>
                @empty
                    <p class="admin-empty-inline">No payment status data yet.</p>
                @endforelse
            </div>
        </div>

        <div class="admin-chart-card admin-panel-card">
            <div class="admin-chart-head">
                <h3>Payment type mix</h3>
            </div>

            <div class="admin-compact-list">
                @forelse ($typeBreakdown as $item)
                    <div>
                        <span>{{ $item['label'] }}</span>
                        <strong>{{ number_format((int) ($item['count'] ?? 0)) }}</strong>
                        <em>Rp{{ number_format((float) ($item['amount'] ?? 0), 0, ',', '.') }}</em>
                    </div>
                @empty
                    <p class="admin-empty-inline">No payment type data yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="admin-booking-card">
        <div class="admin-booking-tabs provider-payment-tabs">
            @foreach ($paymentStatuses as $key => $label)
                <a href="{{ provider_route('provider.payments.index', $queryFor(['status' => $key])) }}"
                    class="admin-booking-tab {{ ($currentStatus === $key || ($key === 'all' && empty($currentStatus))) ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ provider_route('provider.payments.index') }}" class="admin-booking-filter-panel compact {{ $hasMobileAdvancedFilters ? 'is-expanded' : '' }}">
            @if (! empty($currentStatus) && $currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif

            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
            <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">

            <div class="admin-booking-filter-row provider-payment-filter-row" id="paymentFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="paymentSearchInput"
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Search payment">
                    </div>
                </label>

                <button type="submit" class="admin-booking-mobile-search-submit" aria-label="Search payment">
                    Search
                </button>

                <button type="button"
                    class="admin-booking-mobile-filter-toggle {{ $hasMobileAdvancedFilters ? 'active' : '' }}"
                    aria-controls="paymentFilterRow"
                    aria-expanded="{{ $hasMobileAdvancedFilters ? 'true' : 'false' }}">
                    Filter
                </button>

                <label class="admin-booking-field mini">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" aria-label="Date from" title="Date from">
                </label>

                <label class="admin-booking-field mini">
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" aria-label="Date to" title="Date to">
                </label>

                <label class="admin-booking-field mini">
                    <select name="payment_type" aria-label="Payment type" title="Payment type">
                        @foreach ($paymentTypes as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['payment_type'] ?? 'all') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-booking-field count">
                    <select name="per_page" aria-label="Rows per page" title="Rows per page">
                        <option value="10" {{ (int) ($filters['per_page'] ?? 25) === 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ (int) ($filters['per_page'] ?? 25) === 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ (int) ($filters['per_page'] ?? 25) === 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ (int) ($filters['per_page'] ?? 25) === 100 ? 'selected' : '' }}>100</option>
                    </select>
                </label>

                <div class="admin-booking-filter-buttons">
                    <button type="submit">Filter</button>
                    @if ($hasActiveFilters ?? false)
                        <a href="{{ provider_route('provider.payments.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} payments</span>

                @if (($filters['search'] ?? '') !== '')
                    <span>Search: {{ $filters['search'] }}</span>
                @endif

                @if (($filters['status'] ?? 'all') !== 'all')
                    <span>Status: {{ $paymentStatuses[$filters['status']] ?? $statusLabel($filters['status']) }}</span>
                @endif

                @if (($filters['payment_type'] ?? 'all') !== 'all')
                    <span>Type: {{ $paymentTypes[$filters['payment_type']] ?? $statusLabel($filters['payment_type']) }}</span>
                @endif

                @if (! empty($filters['date_from']))
                    <span>From: {{ $filters['date_from'] }}</span>
                @endif

                @if (! empty($filters['date_to']))
                    <span>To: {{ $filters['date_to'] }}</span>
                @endif
            </div>
        </form>

        <div class="admin-booking-mobile-list">
            @forelse ($paymentCollection as $payment)
                @php
                    $booking = $payment->booking;
                    $multiServices = $booking?->services ?? collect();
                    $serviceName = $multiServices->isNotEmpty()
                        ? $multiServices->pluck('title')->join(', ')
                        : ($booking?->service?->title ?? '-');
                    $customerName = $booking?->customer?->name ?? $booking?->customer_name ?? 'Walk-in';
                    $branchName = $booking?->branch?->branch_name ?? '-';
                    $paymentMethod = $payment->payment_channel
                        ?: $payment->payment_method
                        ?: (($payment->payment_type ?? null) === 'pay_at_salon' ? 'Pay at Salon' : 'Manual');
                @endphp

                <article class="admin-booking-mobile-card">
                    <header>
                        <div>
                            <strong>{{ $booking?->booking_code ?? ('PAY-' . $payment->id) }}</strong>
                            <span>{{ $formatDate($payment->created_at) }}</span>
                        </div>

                        <b>Rp{{ number_format((float) $payment->amount, 0, ',', '.') }}</b>
                    </header>

                    <div class="admin-booking-mobile-main">
                        <div>
                            <span>Customer</span>
                            <strong>{{ $customerName }}</strong>
                        </div>

                        <div>
                            <span>Branch</span>
                            <strong>{{ $branchName }}</strong>
                        </div>
                    </div>

                    <p>{{ $serviceName }}</p>

                    <footer>
                        <span class="admin-booking-status {{ $statusClass($payment->status) }}">
                            {{ $statusLabel($payment->status) }}
                        </span>
                        <span class="admin-booking-status {{ $statusClass($payment->payment_type) }}">
                            {{ $statusLabel($payment->payment_type) }}
                        </span>
                        <span class="admin-booking-status info">
                            {{ $statusLabel($paymentMethod) }}
                        </span>
                    </footer>
                </article>
            @empty
                <div class="admin-booking-mobile-empty">
                    <strong>No payments yet.</strong>
                    <p>Try changing the keyword, date, type, or payment status.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap">
            <table class="admin-booking-table detailed provider-payment-table">
                <thead>
                    <tr>
                        <th>
                            <a href="{{ provider_route('provider.payments.index', $sortQueryFor('created_at')) }}" class="admin-booking-sort {{ $sortBy === 'created_at' ? 'active' : '' }}">
                                Payment
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('created_at', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('created_at', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ provider_route('provider.payments.index', $sortQueryFor('paid_at')) }}" class="admin-booking-sort {{ $sortBy === 'paid_at' ? 'active' : '' }}">
                                Timeline
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('paid_at', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('paid_at', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Services</th>
                        <th>
                            <a href="{{ provider_route('provider.payments.index', $sortQueryFor('payment_type')) }}" class="admin-booking-sort {{ $sortBy === 'payment_type' ? 'active' : '' }}">
                                Type
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('payment_type', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('payment_type', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Method</th>
                        <th>
                            <a href="{{ provider_route('provider.payments.index', $sortQueryFor('status')) }}" class="admin-booking-sort {{ $sortBy === 'status' ? 'active' : '' }}">
                                Status
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('status', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('status', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ provider_route('provider.payments.index', $sortQueryFor('amount')) }}" class="admin-booking-sort {{ $sortBy === 'amount' ? 'active' : '' }}">
                                Amount
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('amount', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('amount', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Reference</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($paymentCollection as $payment)
                        @php
                            $booking = $payment->booking;
                            $multiServices = $booking?->services ?? collect();
                            $serviceName = $multiServices->isNotEmpty()
                                ? $multiServices->pluck('title')->join(', ')
                                : ($booking?->service?->title ?? '-');
                            $serviceCount = $multiServices->isNotEmpty() ? $multiServices->count() : ($booking?->service ? 1 : 0);
                            $customerName = $booking?->customer?->name ?? $booking?->customer_name ?? 'Walk-in';
                            $customerPhone = $booking?->customer_phone ?? '-';
                            $branchName = $booking?->branch?->branch_name ?? '-';
                            $staffName = $booking?->staff?->full_name ?: 'Any Available';
                            $paymentMethod = $payment->payment_channel
                                ?: $payment->payment_method
                                ?: (($payment->payment_type ?? null) === 'pay_at_salon' ? 'Pay at Salon' : 'Manual');
                            $reference = $payment->midtrans_order_id
                                ?: $payment->midtrans_transaction_id
                                ?: $payment->payment_code
                                ?: ('PAY-' . $payment->id);
                            $initial = strtoupper(substr($customerName !== '-' ? $customerName : 'C', 0, 1));
                        @endphp

                        <tr>
                            <td>
                                <div class="admin-booking-code-cell">
                                    <strong>{{ $booking?->booking_code ?? ('PAY-' . $payment->id) }}</strong>
                                    <small>Payment ID {{ $payment->id }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date">
                                    <strong>{{ $formatDate($payment->created_at) }}</strong>
                                    <small>Paid: {{ $formatDate($payment->paid_at) }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-person">
                                    <span>{{ $initial }}</span>
                                    <div>
                                        <strong>{{ $customerName }}</strong>
                                        <small>{{ $customerPhone ?: 'No phone' }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date">
                                    <strong>{{ $branchName }}</strong>
                                    <small>Staff: {{ $staffName }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-service-cell">
                                    <strong>{{ $serviceName }}</strong>
                                    <small>{{ $serviceCount > 1 ? $serviceCount . ' services' : 'Single service' }}</small>
                                </div>
                            </td>

                            <td>
                                <span class="admin-booking-status {{ $statusClass($payment->payment_type) }}">
                                    {{ $statusLabel($payment->payment_type) }}
                                </span>
                            </td>

                            <td>
                                <div class="admin-booking-mode-stack">
                                    <span class="admin-booking-status info">{{ $statusLabel($paymentMethod) }}</span>
                                    @if ($payment->payment_code_label)
                                        <small>{{ $payment->payment_code_label }}</small>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <span class="admin-booking-status {{ $statusClass($payment->status) }}">
                                    {{ $statusLabel($payment->status) }}
                                </span>
                            </td>

                            <td>
                                <div class="admin-booking-total-cell">
                                    <strong>Rp{{ number_format((float) $payment->amount, 0, ',', '.') }}</strong>
                                    <small>{{ $statusLabel($payment->fraud_status ?: 'normal') }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-timeline">
                                    <span>{{ $reference }}</span>
                                    <small>Updated {{ $formatDate($payment->updated_at, 'd M H:i') }}</small>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 7h16"></path>
                                            <path d="M4 12h16"></path>
                                            <path d="M4 17h10"></path>
                                        </svg>
                                    </span>

                                    <strong>No payments yet.</strong>
                                    <p>Try changing the keyword, date, type, or payment status.</p>
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
                    @if ($paymentCollection->onFirstPage())
                        <span class="disabled">&lsaquo;</span>
                    @else
                        <a href="{{ $paymentCollection->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
                    @endif

                    <span class="active">{{ $paymentCollection->currentPage() }}</span>

                    @if ($paymentCollection->hasMorePages())
                        <a href="{{ $paymentCollection->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
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
