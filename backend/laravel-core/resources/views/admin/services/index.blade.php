@extends('admin.layouts.app')

@section('title', 'Services - JasaKu')
@section('page_title', 'Services')

@section('content')
@php
    $serviceCollection = $services ?? collect();
    $hasPaginator = is_object($serviceCollection)
        && method_exists($serviceCollection, 'links')
        && method_exists($serviceCollection, 'firstItem');

    $firstItem = $hasPaginator ? ($serviceCollection->firstItem() ?? 0) : 0;
    $lastItem = $hasPaginator ? ($serviceCollection->lastItem() ?? 0) : (is_countable($serviceCollection) ? count($serviceCollection) : 0);
    $totalItem = $hasPaginator ? $serviceCollection->total() : (is_countable($serviceCollection) ? count($serviceCollection) : 0);

    $filters = $filters ?? [
        'status' => request('status', 'all'),
        'search' => request('search', $search ?? ''),
        'per_page' => request('per_page', $perPage ?? 10),
        'document_status' => request('document_status', 'all'),
        'price_type' => request('price_type', 'all'),
        'sort_by' => request('sort_by', 'created_at'),
        'sort_direction' => request('sort_direction', 'desc'),
    ];

    $tabs = $tabs ?? [
        'all' => 'All Services',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    $documentStatuses = [
        'all' => 'All Documents',
        'verified' => 'Verified',
        'submitted' => 'Submitted',
        'pending' => 'Pending',
        'rejected' => 'Rejected',
    ];

    $priceTypes = [
        'all' => 'All Prices',
        'fixed' => 'Fixed',
        'hourly' => 'Hourly',
    ];

    $sortOptions = [
        'title' => 'Service',
        'provider' => 'Provider',
        'category' => 'Category',
        'code' => 'Code',
        'price' => 'Price',
        'status' => 'Status',
        'document_status' => 'Documents',
        'created_at' => 'Created',
    ];

    $currentStatus = $filters['status'] ?? 'all';
    $sortBy = $sortBy ?? ($filters['sort_by'] ?? 'created_at');
    $sortDirection = $sortDirection ?? ($filters['sort_direction'] ?? 'desc');

    $statusLabels = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'verified' => 'Verified',
        'submitted' => 'Submitted',
        'pending' => 'Pending',
        'rejected' => 'Rejected',
        'fixed' => 'Fixed',
        'hourly' => 'Hourly',
    ];

    $statusLabel = fn ($value) => $statusLabels[$value ?: 'pending'] ?? ucwords(str_replace('_', ' ', $value ?: 'pending'));

    $statusClass = function ($value) {
        return match ($value) {
            'active', 'verified' => 'success',
            'pending', 'submitted' => 'warning',
            'fixed', 'hourly' => 'info',
            'inactive', 'rejected' => 'danger',
            default => 'neutral',
        };
    };

    $formatMoney = fn ($value) => 'Rp' . number_format((float) ($value ?? 0), 0, ',', '.');

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

    $cleanQuery = function (array $query) {
        return collect($query)
            ->reject(function ($value, $key) {
                if ($value === null || $value === '') {
                    return true;
                }

                if (in_array($key, ['status', 'document_status', 'price_type'], true) && $value === 'all') {
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

    $summary = $summary ?? [
        'total' => $totalItem,
        'revenue' => 0,
        'active' => 0,
        'verified' => 0,
    ];

    $hasMobileAdvancedFilters = (($filters['document_status'] ?? 'all') !== 'all')
        || (($filters['price_type'] ?? 'all') !== 'all')
        || ((int) ($filters['per_page'] ?? 10) !== 10);
@endphp

<section class="admin-services-page admin-booking-page">
    <div class="admin-booking-route">
        <div class="admin-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Services</strong>
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
            <span>Total Services</span>
            <strong>{{ number_format((int) $summary['total']) }}</strong>
            <small>All provider services</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Service Value</span>
            <strong>{{ $formatMoney($summary['revenue']) }}</strong>
            <small>Total service value</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Active</span>
            <strong>{{ number_format((int) $summary['active']) }}</strong>
            <small>Services ready to book</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Verified</span>
            <strong>{{ number_format((int) $summary['verified']) }}</strong>
            <small>Valid provider documents</small>
        </div>
    </div>

    <div class="admin-booking-card admin-service-card">
        <div class="admin-booking-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.services.index', $queryFor(['status' => $key])) }}"
                   class="admin-booking-tab {{ ($currentStatus === $key || ($key === 'all' && empty($currentStatus))) ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.services.index') }}" class="admin-booking-filter-panel compact {{ $hasMobileAdvancedFilters ? 'is-expanded' : '' }}">
            @if (!empty($currentStatus) && $currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif

            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
            <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">

            <div class="admin-booking-filter-row" id="serviceFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="serviceSearchInput"
                               type="text"
                               name="search"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Search service">
                    </div>
                </label>

                <button type="submit" class="admin-booking-mobile-search-submit" aria-label="Search service">
                    Search
                </button>

                <button type="button"
                        class="admin-booking-mobile-filter-toggle {{ $hasMobileAdvancedFilters ? 'active' : '' }}"
                        aria-controls="serviceFilterRow"
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

                <label class="admin-booking-field mini">
                    <select name="price_type" aria-label="Price type" title="Price type">
                        @foreach ($priceTypes as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['price_type'] ?? 'all') === $key ? 'selected' : '' }}>
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
                    @if ($hasActiveFilters ?? false)
                        <a href="{{ route('admin.services.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} service</span>

                @if (($filters['search'] ?? '') !== '')
                    <span>Search: {{ $filters['search'] }}</span>
                @endif

                @if (($filters['document_status'] ?? 'all') !== 'all')
                    <span>Docs: {{ $documentStatuses[$filters['document_status']] ?? $statusLabel($filters['document_status']) }}</span>
                @endif

                @if (($filters['price_type'] ?? 'all') !== 'all')
                    <span>Price: {{ $priceTypes[$filters['price_type']] ?? $statusLabel($filters['price_type']) }}</span>
                @endif
            </div>
        </form>

        <div class="admin-service-mobile-list admin-booking-mobile-list">
            @forelse ($serviceCollection as $service)
                @php
                    $providerName = $service->provider_name ?? '-';
                    $providerEmail = $service->provider_email ?? 'Provider account';
                    $serviceStatus = $service->status ?? 'inactive';
                    $documentStatus = $service->provider_document_status ?? 'pending';
                    $priceTypeValue = $service->price_type ?: 'fixed';
                @endphp

                <article class="admin-service-mobile-card admin-booking-mobile-card">
                    <header>
                        <div>
                            <strong>{{ $service->title ?? '-' }}</strong>
                            <span>{{ $service->code ?: 'No code' }} &middot; {{ $service->category ?: 'Uncategorized' }}</span>
                        </div>

                        <b>{{ $formatMoney($service->price ?? 0) }}</b>
                    </header>

                    <div class="admin-service-mobile-main admin-booking-mobile-main">
                        <div>
                            <span>Provider</span>
                            <strong>{{ $providerName }}</strong>
                        </div>

                        <div>
                            <span>Duration</span>
                            <strong>{{ $formatDuration($service) }}</strong>
                        </div>
                    </div>

                    <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) ($service->description ?: 'This service does not have a description yet.')), 120) }}</p>

                    <footer>
                        <span class="admin-booking-status {{ $statusClass($serviceStatus) }}">
                            {{ $statusLabel($serviceStatus) }}
                        </span>
                        <span class="admin-booking-status {{ $statusClass($documentStatus) }}">
                            {{ $statusLabel($documentStatus) }}
                        </span>
                        <span class="admin-booking-status {{ $statusClass($priceTypeValue) }}">
                            {{ $statusLabel($priceTypeValue) }}
                        </span>
                    </footer>
                </article>
            @empty
                <div class="admin-service-mobile-empty admin-booking-mobile-empty">
                    <strong>No service data found.</strong>
                    <p>Try changing the keyword, status, document status, or price type.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap admin-service-table-wrap">
            <table class="admin-booking-table detailed admin-service-table">
                <thead>
                    <tr>
                        <th>
                            <a href="{{ route('admin.services.index', $sortQueryFor('title')) }}" class="admin-booking-sort {{ $sortBy === 'title' ? 'active' : '' }}">
                                Service
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('title', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('title', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.services.index', $sortQueryFor('provider')) }}" class="admin-booking-sort {{ $sortBy === 'provider' ? 'active' : '' }}">
                                Provider
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('provider', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('provider', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.services.index', $sortQueryFor('category')) }}" class="admin-booking-sort {{ $sortBy === 'category' ? 'active' : '' }}">
                                Category
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('category', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('category', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.services.index', $sortQueryFor('price')) }}" class="admin-booking-sort {{ $sortBy === 'price' ? 'active' : '' }}">
                                Pricing
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('price', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('price', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Availability</th>
                        <th>
                            <a href="{{ route('admin.services.index', $sortQueryFor('status')) }}" class="admin-booking-sort {{ $sortBy === 'status' ? 'active' : '' }}">
                                Status
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('status', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('status', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.services.index', $sortQueryFor('document_status')) }}" class="admin-booking-sort {{ $sortBy === 'document_status' ? 'active' : '' }}">
                                Documents
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('document_status', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('document_status', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.services.index', $sortQueryFor('created_at')) }}" class="admin-booking-sort {{ $sortBy === 'created_at' ? 'active' : '' }}">
                                Created
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('created_at', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('created_at', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($serviceCollection as $service)
                        @php
                            $providerName = $service->provider_name ?? '-';
                            $providerEmail = $service->provider_email ?? 'Provider account';
                            $serviceStatus = $service->status ?? 'inactive';
                            $documentStatus = $service->provider_document_status ?? 'pending';
                            $priceTypeValue = $service->price_type ?: 'fixed';
                            $enabledModes = collect([
                                $service->is_scheduled_enabled ? 'Scheduled' : null,
                                $service->is_queue_enabled ? 'Queue' : null,
                            ])->filter()->values();
                        @endphp

                        <tr>
                            <td>
                                <div class="admin-booking-service-cell admin-service-title-cell">
                                    <strong>{{ $service->title ?? '-' }}</strong>
                                    <small>{{ $service->code ?: 'No code' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-person admin-service-provider">
                                    <span>{{ strtoupper(substr($providerName !== '-' ? $providerName : 'P', 0, 1)) }}</span>
                                    <div>
                                        <strong>{{ $providerName }}</strong>
                                        <small>{{ $providerEmail }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-code-cell">
                                    <strong>{{ $service->category ?: '-' }}</strong>
                                    <small>{{ $service->slug ?: 'No slug' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-total-cell">
                                    <strong>{{ $formatMoney($service->price ?? 0) }}</strong>
                                    <small>{{ $statusLabel($priceTypeValue) }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-mode-stack">
                                    <span class="admin-booking-status {{ $statusClass($priceTypeValue) }}">
                                        {{ $enabledModes->isNotEmpty() ? $enabledModes->join(' / ') : 'Unavailable' }}
                                    </span>
                                    <small>{{ $formatDuration($service) }}</small>
                                    @if ($service->requires_dp)
                                        <small>DP {{ $formatMoney($service->dp_amount ?? 0) }}</small>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <span class="admin-booking-status {{ $statusClass($serviceStatus) }}">
                                    {{ $statusLabel($serviceStatus) }}
                                </span>
                            </td>

                            <td>
                                <span class="admin-booking-status {{ $statusClass($documentStatus) }}">
                                    {{ $statusLabel($documentStatus) }}
                                </span>
                            </td>

                            <td>
                                <div class="admin-booking-timeline">
                                    <span>{{ $formatDate($service->created_at) }}</span>
                                    <small>{{ $service->created_at?->format('H:i') ?? '-' }}</small>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 7h16"></path>
                                            <path d="M4 12h16"></path>
                                            <path d="M4 17h16"></path>
                                        </svg>
                                    </span>

                                    <strong>No service data found.</strong>
                                    <p>Try changing the keyword, status, document status, or price type.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer admin-service-footer">
            <p class="admin-booking-showing">
                <strong>{{ number_format($firstItem) }}-{{ number_format($lastItem) }}</strong>
                <span>/ {{ number_format($totalItem) }}</span>
            </p>

            @if ($hasPaginator)
                <div class="admin-booking-pagination admin-service-pagination">
                    @if ($serviceCollection->onFirstPage())
                        <span class="disabled">&lsaquo;</span>
                    @else
                        <a href="{{ $serviceCollection->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
                    @endif

                    <span class="active">{{ $serviceCollection->currentPage() }}</span>

                    @if ($serviceCollection->hasMorePages())
                        <a href="{{ $serviceCollection->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
                    @else
                        <span class="disabled">&rsaquo;</span>
                    @endif
                </div>
            @else
                <div class="admin-booking-pagination admin-service-pagination static">
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
    const page = document.querySelector('.admin-services-page');

    if (!page || !window.fetch || !window.DOMParser || !window.history) {
        return;
    }

    const card = page.querySelector('.admin-service-card');
    const replaceSelectors = [
        '.admin-booking-tabs',
        '.admin-booking-filter-panel',
        '.admin-service-mobile-list',
        '.admin-service-table-wrap',
        '.admin-service-footer',
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

        if (['status', 'document_status', 'price_type'].includes(key) && value === 'all') {
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

    const replaceServiceParts = (html) => {
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

    const loadServices = async (url, options = {}) => {
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
                throw new Error(`Service filter failed with status ${response.status}`);
            }

            const html = await response.text();

            if (controller !== activeRequest) {
                return;
            }

            replaceServiceParts(html);

            if (shouldPush) {
                window.history.pushState({ adminServicesAjax: true }, '', response.url);
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
        loadServices(buildFilterUrl(form));
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
        loadServices(url);
    });

    window.addEventListener('popstate', () => {
        loadServices(new URL(window.location.href), { push: false });
    });
})();
</script>
@endpush
