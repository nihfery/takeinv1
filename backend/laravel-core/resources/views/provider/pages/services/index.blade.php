@extends('provider.layouts.dashboard')

@section('title', 'My Service - Provider Dashboard')
@section('page_title', 'My Service')
@section('page_subtitle', 'Manage every service you created, including pricing, availability, branch coverage, and document status.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/my-service.css') }}">
@endpush

@section('content')
@php
    use Illuminate\Support\Str;

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

    $documentStatusClass = fn ($status) => match ($status) {
        'verified' => 'success',
        'rejected' => 'danger',
        'pending' => 'warning',
        default => 'secondary',
    };

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
        'scheduled' => 'Scheduled',
        'queue' => 'Queue',
    ];

    $statusLabel = fn ($value) => $statusLabels[$value ?: 'pending'] ?? ucwords(str_replace('_', ' ', $value ?: 'pending'));

    $statusClass = function ($value) {
        return match ($value) {
            'active', 'verified' => 'success',
            'pending', 'submitted' => 'warning',
            'fixed', 'hourly', 'scheduled', 'queue' => 'info',
            'inactive', 'rejected' => 'danger',
            default => 'neutral',
        };
    };

    $statusPillClass = fn ($value) => in_array($value, ['active', 'inactive'], true) ? $value : 'inactive';
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

    $branchSummary = function ($service) {
        $branchIds = collect($service->branch_ids ?? [])->filter()->values();

        if ($branchIds->isEmpty()) {
            return 'All branches';
        }

        return $branchIds->count() . ' branch' . ($branchIds->count() > 1 ? 'es' : '');
    };

    $assetUrl = function ($path) {
        if (!$path) {
            return null;
        }

        $path = ltrim($path, '/');

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/' . $path);
    };

    $serviceInitial = fn ($service) => strtoupper(substr((string) ($service->title ?: 'S'), 0, 1));
    $serviceDescription = fn ($service, $limit = 110) => Str::limit(strip_tags((string) ($service->description ?: 'This service does not have a description yet.')), $limit);

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
        'active' => 0,
        'verified' => 0,
        'revenue' => 0,
    ];

    $hasMobileAdvancedFilters = (($filters['document_status'] ?? 'all') !== 'all')
        || (($filters['price_type'] ?? 'all') !== 'all')
        || ((int) ($filters['per_page'] ?? 10) !== 10);
@endphp

<section class="admin-category-page admin-booking-page provider-my-service-category-page">
    <div class="admin-booking-route admin-category-route provider-service-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>My Service</strong>
        </div>

        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="{{ provider_route('provider.services.create') }}" class="admin-category-add-button admin-category-add-button-desktop" data-setup-add-service>
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Add Service
            </a>
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
            <span>Total Service</span>
            <strong>{{ number_format((int) $summary['total']) }}</strong>
            <small>All provider services</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Active</span>
            <strong>{{ number_format((int) $summary['active']) }}</strong>
            <small>Services shown in the catalog</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Verified</span>
            <strong>{{ number_format((int) $summary['verified']) }}</strong>
            <small>Provider documents are valid</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Service Value</span>
            <strong>{{ $formatMoney($summary['revenue']) }}</strong>
            <small>Total service value</small>
        </div>
    </div>

    <div class="admin-booking-card category-card provider-service-category-card">
        <div class="admin-booking-tabs provider-service-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ provider_route('provider.services.index', $queryFor(['status' => $key])) }}"
                   class="admin-booking-tab {{ ($currentStatus === $key || ($key === 'all' && empty($currentStatus))) ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ provider_route('provider.services.index') }}" class="admin-booking-filter-panel compact {{ $hasMobileAdvancedFilters ? 'is-expanded' : '' }}">
            @if (! empty($currentStatus) && $currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif

            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
            <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">

            <div class="admin-booking-filter-row provider-service-category-filter-row" id="serviceFilterRow">
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
                        <a href="{{ provider_route('provider.services.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} service</span>

                @if (($filters['search'] ?? '') !== '')
                    <span>Search: {{ $filters['search'] }}</span>
                @endif

                @if (($filters['status'] ?? 'all') !== 'all')
                    <span>Status: {{ $statusLabel($filters['status']) }}</span>
                @endif

                @if (($filters['document_status'] ?? 'all') !== 'all')
                    <span>Docs: {{ $documentStatuses[$filters['document_status']] ?? $statusLabel($filters['document_status']) }}</span>
                @endif

                @if (($filters['price_type'] ?? 'all') !== 'all')
                    <span>Price: {{ $priceTypes[$filters['price_type']] ?? $statusLabel($filters['price_type']) }}</span>
                @endif
            </div>
        </form>

        <div class="admin-category-add-row" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
            <a href="{{ provider_route('provider.services.create') }}" class="admin-category-add-button admin-category-add-button-mobile" style="width: 100%;" data-setup-add-service>
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Add Service
            </a>
        </div>

        <div class="admin-category-mobile-list admin-booking-mobile-list">
            @forelse ($serviceCollection as $service)
                @php
                    $imageUrl = $assetUrl($service->gallery_image);
                    $serviceStatus = $service->status ?? 'inactive';
                    $documentStatus = $service->provider_document_status ?? 'pending';
                    $priceTypeValue = $service->price_type ?: 'fixed';
                    $enabledModes = collect([
                        $service->is_scheduled_enabled ? 'Scheduled' : null,
                        $service->is_queue_enabled ? 'Queue' : null,
                    ])->filter()->values();
                @endphp

                <article class="admin-category-mobile-card admin-booking-mobile-card provider-service-mobile-card">
                    <header class="admin-category-mobile-head">
                        <div class="admin-category-mobile-title">
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $service->title }}">
                            @else
                                <span>{{ $serviceInitial($service) }}</span>
                            @endif

                            <div>
                                <strong>{{ $service->title ?? '-' }}</strong>
                                <span>{{ $service->code ?: 'No code' }}</span>
                            </div>
                        </div>

                        <b>{{ $formatMoney($service->price ?? 0) }}</b>
                    </header>

                    <div class="admin-category-mobile-main admin-booking-mobile-main provider-service-mobile-main">
                        <div>
                            <span>Category</span>
                            <strong>{{ $service->category ?: '-' }}</strong>
                        </div>

                        <div>
                            <span>Duration</span>
                            <strong>{{ $formatDuration($service) }}</strong>
                        </div>

                        <div>
                            <span>Type</span>
                            <strong>{{ $statusLabel($priceTypeValue) }}</strong>
                            <small>{{ $enabledModes->isNotEmpty() ? $enabledModes->join(' / ') : 'Unavailable' }}</small>
                        </div>

                        <div>
                            <span>Branches</span>
                            <strong>{{ $branchSummary($service) }}</strong>
                        </div>
                    </div>

                    <p>{{ $serviceDescription($service, 120) }}</p>

                    <footer class="admin-category-mobile-footer provider-service-mobile-footer">
                        <form action="{{ provider_route('provider.services.toggle-status', $service->id) }}" method="POST" class="inline-form provider-service-status-form" data-status="{{ $serviceStatus }}" data-has-ongoing="{{ $service->has_ongoing_bookings ? 'true' : 'false' }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="provider-status-toggle status-{{ $statusPillClass($serviceStatus) }}" title="Click to change status">
                                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                {{ $statusLabel($serviceStatus) }}
                            </button>
                        </form>

                        <span class="admin-booking-status {{ $statusClass($documentStatus) }}">
                            {{ $statusLabel($documentStatus) }}
                        </span>

                        <div class="category-actions">
                            <a href="{{ provider_route('provider.services.edit', $service->id) }}" class="category-action-btn" title="Edit" aria-label="Edit {{ $service->title }}">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M4 20h4L18 10l-4-4L4 16v4Z"></path>
                                    <path d="m13 7 4 4"></path>
                                </svg>
                            </a>

                            <button type="button"
                                    class="category-action-btn danger"
                                    title="Delete"
                                    aria-label="Delete {{ $service->title }}"
                                    data-service-delete-url="{{ provider_route('provider.services.destroy', $service->id) }}">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M5 7h14"></path>
                                    <path d="M9 7V5h6v2"></path>
                                    <path d="m7 7 1 14h8l1-14"></path>
                                    <path d="M10 11v6"></path>
                                    <path d="M14 11v6"></path>
                                </svg>
                            </button>
                        </div>
                    </footer>
                </article>
            @empty
                <div class="admin-category-mobile-empty admin-booking-mobile-empty">
                    <strong>No service data found.</strong>
                    <p>Try changing the keyword, status, document, or price type.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap category-table-wrap provider-service-category-table-wrap" data-setup-service-list>
            <table class="admin-booking-table detailed category-table provider-service-category-table">
                <thead>
                    <tr>
                        <th>
                            <a href="{{ provider_route('provider.services.index', $sortQueryFor('title')) }}" class="admin-booking-sort {{ $sortBy === 'title' ? 'active' : '' }}">
                                Service
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('title', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('title', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ provider_route('provider.services.index', $sortQueryFor('category')) }}" class="admin-booking-sort {{ $sortBy === 'category' ? 'active' : '' }}">
                                Category
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('category', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('category', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Description</th>
                        <th>
                            <a href="{{ provider_route('provider.services.index', $sortQueryFor('price')) }}" class="admin-booking-sort {{ $sortBy === 'price' ? 'active' : '' }}">
                                Pricing
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('price', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('price', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ provider_route('provider.services.index', $sortQueryFor('status')) }}" class="admin-booking-sort {{ $sortBy === 'status' ? 'active' : '' }}">
                                Status
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('status', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('status', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ provider_route('provider.services.index', $sortQueryFor('document_status')) }}" class="admin-booking-sort {{ $sortBy === 'document_status' ? 'active' : '' }}">
                                Docs
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('document_status', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('document_status', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ provider_route('provider.services.index', $sortQueryFor('created_at')) }}" class="admin-booking-sort {{ $sortBy === 'created_at' ? 'active' : '' }}">
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
                    @forelse ($serviceCollection as $service)
                        @php
                            $imageUrl = $assetUrl($service->gallery_image);
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
                                <div class="category-name-box provider-service-name-box">
                                    @if ($imageUrl)
                                        <img src="{{ $imageUrl }}" alt="{{ $service->title }}" class="category-thumb">
                                    @else
                                        <span class="category-thumb-placeholder">{{ $serviceInitial($service) }}</span>
                                    @endif

                                    <div class="category-name-text">
                                        <strong>{{ $service->title ?? '-' }}</strong>
                                        <small>{{ $service->code ?: 'No code' }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-code-cell">
                                    <strong>{{ $service->category ?: '-' }}</strong>
                                    <small>{{ $service->slug ?: 'No slug' }}</small>
                                    <small>{{ $branchSummary($service) }}</small>
                                </div>
                            </td>

                            <td>
                                <p class="category-description-text">{{ $serviceDescription($service, 92) }}</p>
                                <small class="provider-service-description-meta">
                                    {{ $formatDuration($service) }} &middot; {{ $enabledModes->isNotEmpty() ? $enabledModes->join(' / ') : 'Unavailable' }}
                                </small>
                            </td>

                            <td>
                                <div class="admin-booking-total-cell">
                                    <strong>{{ $formatMoney($service->price ?? 0) }}</strong>
                                    <small>{{ $statusLabel($priceTypeValue) }}</small>
                                    @if ($service->requires_dp)
                                        <small>DP {{ $formatMoney($service->dp_amount ?? 0) }}</small>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <form action="{{ provider_route('provider.services.toggle-status', $service->id) }}" method="POST" class="inline-form provider-service-status-form" data-status="{{ $serviceStatus }}" data-has-ongoing="{{ $service->has_ongoing_bookings ? 'true' : 'false' }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="provider-status-toggle status-{{ $statusPillClass($serviceStatus) }}" title="Click to change status">
                                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                        {{ $statusLabel($serviceStatus) }}
                                    </button>
                                </form>
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

                            <td>
                                <div class="category-actions provider-service-row-actions">
                                    <a href="{{ provider_route('provider.services.edit', $service->id) }}" class="category-action-btn" title="Edit" aria-label="Edit {{ $service->title }}">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M4 20h4L18 10l-4-4L4 16v4Z"></path>
                                            <path d="m13 7 4 4"></path>
                                        </svg>
                                    </a>

                                    @if ($service->can_be_deleted)
                                        <button type="button"
                                                class="category-action-btn danger"
                                                title="Delete"
                                                aria-label="Delete {{ $service->title }}"
                                                data-service-delete-url="{{ provider_route('provider.services.destroy', $service->id) }}">
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M5 7h14"></path>
                                                <path d="M9 7V5h6v2"></path>
                                                <path d="m7 7 1 14h8l1-14"></path>
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
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 7h16"></path>
                                            <path d="M4 12h16"></path>
                                            <path d="M4 17h16"></path>
                                        </svg>
                                    </span>

                                    <strong>No service data found.</strong>
                                    <p>Try changing the keyword, status, document, or price type.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-booking-footer category-footer">
            <p class="admin-booking-showing">
                <strong>{{ number_format($firstItem) }}-{{ number_format($lastItem) }}</strong>
                <span>/ {{ number_format($totalItem) }}</span>
            </p>

            @if ($hasPaginator)
                <div class="admin-booking-pagination category-pagination">
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
                <div class="admin-booking-pagination category-pagination static">
                    <span class="active">1</span>
                </div>
            @endif
        </div>
    </div>
</section>

<div class="category-modal" id="serviceDeleteModalOverlay" aria-hidden="true">
    <div class="category-modal-dialog delete" id="serviceDeleteModal" role="dialog" aria-modal="true" aria-labelledby="serviceDeleteTitle">
        <div class="delete-icon">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M5 7h14"></path>
                <path d="M9 7V5h6v2"></path>
                <path d="m7 7 1 14h8l1-14"></path>
                <path d="M10 11v6"></path>
                <path d="M14 11v6"></path>
            </svg>
        </div>

        <h3 id="serviceDeleteTitle">Non-aktifkan / Hapus Layanan?</h3>

        <p>
            Jika Layanan ini belum pernah dipesan, maka Layanan akan <b>dihapus permanen</b>.
            <br><br>
            Namun jika sudah pernah dipesan, Layanan hanya akan <b>dinonaktifkan</b> dari sistem.
        </p>

        <div class="delete-actions">
            <button type="button" class="modal-cancel-btn" data-service-delete-cancel>
                Batal
            </button>

            <form method="POST" id="serviceDeleteForm" class="delete-category-form">
                @csrf
                @method('DELETE')

                <button type="submit" class="delete-confirm-btn">
                    Lanjutkan
                </button>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('provider/js/my-service.js') }}"></script>
@endsection


