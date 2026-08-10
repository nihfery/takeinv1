@extends('admin.layouts.app')

@section('title', 'Category - JasaKu')
@section('page_title', 'Category')

@section('content')
@php
    use Illuminate\Support\Str;

    $categoryCollection = $categories ?? collect();
    $hasPaginator = is_object($categoryCollection)
        && method_exists($categoryCollection, 'links')
        && method_exists($categoryCollection, 'firstItem');

    $firstItem = $hasPaginator ? ($categoryCollection->firstItem() ?? 0) : 0;
    $lastItem = $hasPaginator ? ($categoryCollection->lastItem() ?? 0) : (is_countable($categoryCollection) ? count($categoryCollection) : 0);
    $totalItem = $hasPaginator ? $categoryCollection->total() : (is_countable($categoryCollection) ? count($categoryCollection) : 0);

    $filters = $filters ?? [
        'status' => request('status', 'all'),
        'featured' => request('featured', 'all'),
        'search' => request('search', $search ?? ''),
        'per_page' => request('per_page', $perPage ?? 10),
        'sort_by' => request('sort_by', 'created_at'),
        'sort_direction' => request('sort_direction', 'desc'),
    ];

    $tabs = $tabs ?? [
        'all' => 'All Category',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    $featuredOptions = [
        'all' => 'All Featured',
        'yes' => 'Featured',
        'no' => 'Not Featured',
    ];

    $currentStatus = $filters['status'] ?? 'all';
    $sortBy = $sortBy ?? ($filters['sort_by'] ?? 'created_at');
    $sortDirection = $sortDirection ?? ($filters['sort_direction'] ?? 'desc');

    $summary = $summary ?? [
        'total' => $totalItem,
        'active' => 0,
        'featured' => 0,
        'services' => 0,
    ];

    $statusLabel = fn ($value) => match ($value) {
        'active' => 'Active',
        'inactive' => 'Inactive',
        'yes' => 'Featured',
        'no' => 'Not Featured',
        default => ucwords(str_replace('_', ' ', (string) $value)),
    };

    $statusClass = fn ($value) => match ($value) {
        'active', 'yes' => 'success',
        'inactive', 'no' => 'danger',
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

    $cleanQuery = function (array $query) {
        return collect($query)
            ->reject(function ($value, $key) {
                if ($value === null || $value === '') {
                    return true;
                }

                if (in_array($key, ['status', 'featured'], true) && $value === 'all') {
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

    $categoryInitial = fn ($category) => strtoupper(substr((string) ($category->name ?: 'C'), 0, 1));
    $categoryDescription = fn ($category, $limit = 110) => Str::limit(strip_tags((string) ($category->description ?: 'This category does not have a description yet.')), $limit);

    $hasActiveFilters = $hasActiveFilters ?? (($filters['search'] ?? '') !== ''
        || ($filters['status'] ?? 'all') !== 'all'
        || ($filters['featured'] ?? 'all') !== 'all'
        || (int) ($filters['per_page'] ?? 10) !== 10
        || ($filters['sort_by'] ?? 'created_at') !== 'created_at'
        || ($filters['sort_direction'] ?? 'desc') !== 'desc');

    $hasMobileAdvancedFilters = (($filters['featured'] ?? 'all') !== 'all')
        || ((int) ($filters['per_page'] ?? 10) !== 10);
@endphp

<section class="admin-category-page admin-booking-page">
    <div class="admin-booking-route admin-category-route">
        <div class="admin-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <a href="{{ route('admin.services.index') }}">Services</a>
            <span>&rsaquo;</span>
            <strong>Category</strong>
        </div>

        <button type="button" class="admin-category-add-button admin-category-add-button-desktop" data-modal-open="addCategoryModal">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 5v14"></path>
                <path d="M5 12h14"></path>
            </svg>
            Add Category
        </button>
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
            <span>Total Category</span>
            <strong>{{ number_format((int) $summary['total']) }}</strong>
            <small>All service categories</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Active</span>
            <strong>{{ number_format((int) $summary['active']) }}</strong>
            <small>Categories shown in the catalog</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Featured</span>
            <strong>{{ number_format((int) $summary['featured']) }}</strong>
            <small>Priority on the customer landing page</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Linked Services</span>
            <strong>{{ number_format((int) $summary['services']) }}</strong>
            <small>Services using categories</small>
        </div>
    </div>

    <div class="admin-booking-card category-card">
        <div class="admin-booking-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.service-categories.index', $queryFor(['status' => $key])) }}"
                   class="admin-booking-tab {{ ($currentStatus === $key || ($key === 'all' && empty($currentStatus))) ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.service-categories.index') }}" class="admin-booking-filter-panel compact {{ $hasMobileAdvancedFilters ? 'is-expanded' : '' }}">
            @if (!empty($currentStatus) && $currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif

            <input type="hidden" name="sort_by" value="{{ $sortBy }}">
            <input type="hidden" name="sort_direction" value="{{ $sortDirection }}">

            <div class="admin-booking-filter-row" id="categoryFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="categorySearchInput"
                               type="text"
                               name="search"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Search category">
                    </div>
                </label>

                <button type="submit" class="admin-booking-mobile-search-submit" aria-label="Search category">
                    Search
                </button>

                <button type="button"
                        class="admin-booking-mobile-filter-toggle {{ $hasMobileAdvancedFilters ? 'active' : '' }}"
                        aria-controls="categoryFilterRow"
                        aria-expanded="{{ $hasMobileAdvancedFilters ? 'true' : 'false' }}">
                    Filter
                </button>

                <label class="admin-booking-field mini">
                    <select name="featured" aria-label="Featured status" title="Featured status">
                        @foreach ($featuredOptions as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['featured'] ?? 'all') === $key ? 'selected' : '' }}>
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
                        <a href="{{ route('admin.service-categories.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} category</span>

                @if (($filters['search'] ?? '') !== '')
                    <span>Search: {{ $filters['search'] }}</span>
                @endif

                @if (($filters['status'] ?? 'all') !== 'all')
                    <span>Status: {{ $statusLabel($filters['status']) }}</span>
                @endif

                @if (($filters['featured'] ?? 'all') !== 'all')
                    <span>Featured: {{ $featuredOptions[$filters['featured']] ?? $statusLabel($filters['featured']) }}</span>
                @endif
            </div>
        </form>

        <div class="admin-category-add-row">
            <button type="button" class="admin-category-add-button admin-category-add-button-mobile" data-modal-open="addCategoryModal">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Add Category
            </button>
        </div>

        <div class="admin-category-mobile-list admin-booking-mobile-list">
            @forelse ($categoryCollection as $category)
                @php
                    $imageUrl = $assetUrl($category->image);
                    $iconUrl = $assetUrl($category->icon);
                    $status = $category->status ?? 'inactive';
                    $isFeatured = (bool) $category->is_featured;
                    $servicesCount = (int) ($category->services_count ?? 0);
                @endphp

                <article class="admin-category-mobile-card admin-booking-mobile-card">
                    <header class="admin-category-mobile-head">
                        <div class="admin-category-mobile-title">
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $category->name }}">
                            @else
                                <span>{{ $categoryInitial($category) }}</span>
                            @endif

                            <div>
                                <strong>{{ $category->name }}</strong>
                                <span>{{ $category->slug ?: 'No slug' }}</span>
                            </div>
                        </div>

                        <b>{{ number_format($servicesCount) }} svc</b>
                    </header>

                    <div class="admin-category-mobile-main admin-booking-mobile-main">
                        <div>
                            <span>Status</span>
                            <strong>{{ $statusLabel($status) }}</strong>
                        </div>

                        <div>
                            <span>Featured</span>
                            <strong>{{ $isFeatured ? 'Yes' : 'No' }}</strong>
                        </div>
                    </div>

                    <p>{{ $categoryDescription($category, 120) }}</p>

                    <footer class="admin-category-mobile-footer">
                        <form action="{{ route('admin.service-categories.toggle-status', $category->id) }}" method="POST" class="inline-form">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="status-pill status-{{ $status }}" title="Click to change status">
                                <span></span>
                                {{ $statusLabel($status) }}
                            </button>
                        </form>

                        <form action="{{ route('admin.service-categories.toggle-featured', $category->id) }}" method="POST" class="toggle-form">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="feature-switch {{ $isFeatured ? 'is-on' : '' }}" aria-label="Toggle featured">
                                <span></span>
                            </button>
                        </form>

                        <div class="category-actions">
                            <button type="button" class="category-action-btn" title="Edit" data-modal-open="editCategoryModal{{ $category->id }}">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M4 20h4L18 10l-4-4L4 16v4Z"></path>
                                    <path d="m13 7 4 4"></path>
                                </svg>
                            </button>

                            <button type="button" class="category-action-btn danger" title="Delete" data-modal-open="deleteCategoryModal{{ $category->id }}">
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
                    <strong>No category data found.</strong>
                    <p>Try changing the keyword, status, or featured filter.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap category-table-wrap">
            <table class="admin-booking-table detailed category-table">
                <thead>
                    <tr>
                        <th>
                            <a href="{{ route('admin.service-categories.index', $sortQueryFor('name')) }}" class="admin-booking-sort {{ $sortBy === 'name' ? 'active' : '' }}">
                                Category
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('name', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('name', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.service-categories.index', $sortQueryFor('slug')) }}" class="admin-booking-sort {{ $sortBy === 'slug' ? 'active' : '' }}">
                                Slug
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('slug', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('slug', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Description</th>
                        <th>
                            <a href="{{ route('admin.service-categories.index', $sortQueryFor('services_count')) }}" class="admin-booking-sort {{ $sortBy === 'services_count' ? 'active' : '' }}">
                                Services
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('services_count', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('services_count', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.service-categories.index', $sortQueryFor('status')) }}" class="admin-booking-sort {{ $sortBy === 'status' ? 'active' : '' }}">
                                Status
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('status', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('status', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.service-categories.index', $sortQueryFor('featured')) }}" class="admin-booking-sort {{ $sortBy === 'featured' ? 'active' : '' }}">
                                Featured
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('featured', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('featured', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.service-categories.index', $sortQueryFor('created_at')) }}" class="admin-booking-sort {{ $sortBy === 'created_at' ? 'active' : '' }}">
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
                    @forelse ($categoryCollection as $category)
                        @php
                            $imageUrl = $assetUrl($category->image);
                            $iconUrl = $assetUrl($category->icon);
                            $status = $category->status ?? 'inactive';
                            $isFeatured = (bool) $category->is_featured;
                            $servicesCount = (int) ($category->services_count ?? 0);
                        @endphp

                        <tr>
                            <td>
                                <div class="category-name-box">
                                    @if ($imageUrl)
                                        <img src="{{ $imageUrl }}" alt="{{ $category->name }}" class="category-thumb">
                                    @else
                                        <span class="category-thumb-placeholder">{{ $categoryInitial($category) }}</span>
                                    @endif

                                    <div class="category-name-text">
                                        <strong>{{ $category->name }}</strong>
                                        <small>{{ $iconUrl ? 'Icon attached' : 'No icon uploaded' }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-code-cell">
                                    <strong>{{ $category->slug ?: '-' }}</strong>
                                    <small>ID #{{ $category->id }}</small>
                                </div>
                            </td>

                            <td>
                                <p class="category-description-text">{{ $categoryDescription($category, 92) }}</p>
                            </td>

                            <td>
                                <div class="admin-booking-total-cell">
                                    <strong>{{ number_format($servicesCount) }}</strong>
                                    <small>linked service</small>
                                </div>
                            </td>

                            <td>
                                <form action="{{ route('admin.service-categories.toggle-status', $category->id) }}" method="POST" class="inline-form">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="status-pill status-{{ $status }}" title="Click to change status">
                                        <span></span>
                                        {{ $statusLabel($status) }}
                                    </button>
                                </form>
                            </td>

                            <td>
                                <form action="{{ route('admin.service-categories.toggle-featured', $category->id) }}" method="POST" class="toggle-form">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="feature-switch {{ $isFeatured ? 'is-on' : '' }}"
                                        aria-label="Toggle featured"
                                        title="{{ $isFeatured ? 'Featured active' : 'Featured inactive' }}">
                                        <span></span>
                                    </button>
                                </form>
                            </td>

                            <td>
                                <div class="admin-booking-timeline">
                                    <span>{{ $formatDate($category->created_at) }}</span>
                                    <small>{{ $category->created_at?->format('H:i') ?? '-' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="category-actions">
                                    <button type="button" class="category-action-btn" title="Edit" data-modal-open="editCategoryModal{{ $category->id }}">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M4 20h4L18 10l-4-4L4 16v4Z"></path>
                                            <path d="m13 7 4 4"></path>
                                        </svg>
                                    </button>

                                    <button type="button" class="category-action-btn danger" title="Delete" data-modal-open="deleteCategoryModal{{ $category->id }}">
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M5 7h14"></path>
                                            <path d="M9 7V5h6v2"></path>
                                            <path d="m7 7 1 14h8l1-14"></path>
                                            <path d="M10 11v6"></path>
                                            <path d="M14 11v6"></path>
                                        </svg>
                                    </button>
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

                                    <strong>No category data found.</strong>
                                    <p>Try changing the keyword, status, or featured filter.</p>
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
                    @if ($categoryCollection->onFirstPage())
                        <span class="disabled">&lsaquo;</span>
                    @else
                        <a href="{{ $categoryCollection->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
                    @endif

                    <span class="active">{{ $categoryCollection->currentPage() }}</span>

                    @if ($categoryCollection->hasMorePages())
                        <a href="{{ $categoryCollection->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
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

{{-- Add Category Modal --}}
<div class="category-modal" id="addCategoryModal" aria-hidden="true">
    <div class="category-modal-dialog large" role="dialog" aria-modal="true" aria-labelledby="addCategoryTitle">
        <div class="category-modal-header">
            <div>
                <h3 id="addCategoryTitle">Add Category</h3>
                <p>Add a new service category for the customer and provider catalog.</p>
            </div>

            <button type="button" class="modal-close" data-modal-close aria-label="Close modal">
                &times;
            </button>
        </div>

        <form action="{{ route('admin.service-categories.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="category-modal-body">
                <div class="form-grid two">
                    <div class="form-group">
                        <label for="categoryName">Name <span>*</span></label>
                        <input id="categoryName"
                               type="text"
                               name="name"
                               value="{{ old('name') }}"
                               placeholder="Example: Hair Treatment"
                               data-slug-source
                               required>
                    </div>

                    <div class="form-group">
                        <label for="categorySlug">Slug</label>
                        <input id="categorySlug"
                               type="text"
                               name="slug"
                               value="{{ old('slug') }}"
                               placeholder="Generated automatically if empty"
                               data-slug-target>
                    </div>
                </div>

                <div class="category-media-grid">
                    <div class="category-media-card">
                        <div class="category-media-head">
                            <span class="category-media-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="3" y="5" width="18" height="14" rx="3"></rect>
                                    <path d="m7 15 3-3 3 3 2-2 2 2"></path>
                                    <circle cx="8" cy="9" r="1.5"></circle>
                                </svg>
                            </span>
                            <div>
                                <strong>Category Image</strong>
                                <small>Large thumbnail for category lists and catalogs.</small>
                            </div>
                        </div>

                        <label class="upload-field image-upload-field">
                            <input type="file" name="image" accept="image/*" data-file-input data-upload-label="Image">
                            <img src="" alt="Image preview" class="upload-preview">

                            <span class="upload-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 5v14"></path>
                                    <path d="M5 12h14"></path>
                                </svg>
                            </span>
                            <strong>Upload Image</strong>
                            <small>JPG, PNG, WEBP. Maximum 2MB.</small>
                            <em data-upload-meta>No file selected</em>
                        </label>

                        <p class="upload-note">Recommended ratio is 4:3 or 1:1 so it is not cropped on mobile.</p>
                    </div>

                    <div class="category-media-card">
                        <div class="category-media-head">
                            <span class="category-media-icon icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 3 4 7l8 4 8-4-8-4Z"></path>
                                    <path d="M4 12l8 4 8-4"></path>
                                    <path d="M4 17l8 4 8-4"></path>
                                </svg>
                            </span>
                            <div>
                                <strong>Category Icon</strong>
                                <small>Small icon for quick access and visual labels.</small>
                            </div>
                        </div>

                        <label class="upload-field icon-upload-field">
                            <input type="file" name="icon" accept="image/*,.svg" data-file-input data-upload-label="Icon">
                            <img src="" alt="Icon preview" class="upload-preview">

                            <span class="upload-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 5v14"></path>
                                    <path d="M5 12h14"></path>
                                </svg>
                            </span>
                            <strong>Upload Icon</strong>
                            <small>SVG, PNG, JPG, WEBP. Maximum 2MB.</small>
                            <em data-upload-meta>No file selected</em>
                        </label>

                        <p class="upload-note">Use a simple icon with a transparent background when possible.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="categoryDescription">Description</label>
                    <textarea id="categoryDescription" name="description" rows="4" placeholder="Deskripsi singkat category">{{ old('description') }}</textarea>
                </div>

                <div class="switch-grid">
                    <div class="switch-row">
                        <span>
                            Status Active
                            <small>The category can appear in the catalog.</small>
                        </span>

                        <label class="form-switch">
                            <input type="hidden" name="status" value="inactive">
                            <input type="checkbox" name="status" value="active" {{ old('status', 'active') === 'active' ? 'checked' : '' }}>
                            <span></span>
                        </label>
                    </div>

                    <div class="switch-row">
                        <span>
                            Featured
                            <small>Prioritize in the selected category area.</small>
                        </span>

                        <label class="form-switch">
                            <input type="hidden" name="is_featured" value="0">
                            <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', '1') === '1' ? 'checked' : '' }}>
                            <span></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="category-modal-footer">
                <button type="button" class="modal-cancel-btn" data-modal-close>
                    Cancel
                </button>

                <button type="submit" class="modal-save-btn">
                    Save Category
                </button>
            </div>
        </form>
    </div>
</div>

@foreach ($categoryCollection as $category)
    @php
        $imageUrl = $assetUrl($category->image);
        $iconUrl = $assetUrl($category->icon);
    @endphp

    {{-- Edit Category Modal --}}
    <div class="category-modal" id="editCategoryModal{{ $category->id }}" aria-hidden="true">
        <div class="category-modal-dialog large" role="dialog" aria-modal="true" aria-labelledby="editCategoryTitle{{ $category->id }}">
            <div class="category-modal-header">
                <div>
                    <h3 id="editCategoryTitle{{ $category->id }}">Edit Category</h3>
                    <p>Update the name, slug, media, status, and featured category.</p>
                </div>

                <button type="button" class="modal-close" data-modal-close aria-label="Close modal">
                    &times;
                </button>
            </div>

            <form action="{{ route('admin.service-categories.update', $category->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="category-modal-body">
                    <div class="form-grid two">
                        <div class="form-group">
                            <label for="categoryName{{ $category->id }}">Name <span>*</span></label>
                            <input id="categoryName{{ $category->id }}" type="text" name="name" value="{{ old('name', $category->name) }}" placeholder="Category name" required>
                        </div>

                        <div class="form-group">
                            <label for="categorySlug{{ $category->id }}">Slug</label>
                            <input id="categorySlug{{ $category->id }}" type="text" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="category-slug">
                        </div>
                    </div>

                    <div class="category-media-grid">
                        <div class="category-media-card">
                            <div class="category-media-head">
                                <span class="category-media-icon">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <rect x="3" y="5" width="18" height="14" rx="3"></rect>
                                        <path d="m7 15 3-3 3 3 2-2 2 2"></path>
                                        <circle cx="8" cy="9" r="1.5"></circle>
                                    </svg>
                                </span>
                                <div>
                                    <strong>Category Image</strong>
                                    <small>Replace the large category thumbnail.</small>
                                </div>
                            </div>

                            <div class="current-preview-box media-current-preview {{ $imageUrl ? 'has-current' : 'is-empty' }}">
                                @if ($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $category->name }}">
                                    <span>Current image</span>
                                @else
                                    <strong>{{ $categoryInitial($category) }}</strong>
                                    <span>No image</span>
                                @endif
                            </div>

                            <label class="upload-field image-upload-field">
                                <input type="file" name="image" accept="image/*" data-file-input data-upload-label="Image">
                                <img src="" alt="Image preview" class="upload-preview">

                                <span class="upload-icon">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M12 5v14"></path>
                                        <path d="M5 12h14"></path>
                                    </svg>
                                </span>
                                <strong>Change Image</strong>
                                <small>Leave blank if not replacing it.</small>
                                <em data-upload-meta>No new file selected</em>
                            </label>

                            <p class="upload-note">The new file will replace the image when the form is saved.</p>
                        </div>

                        <div class="category-media-card">
                            <div class="category-media-head">
                                <span class="category-media-icon icon">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M12 3 4 7l8 4 8-4-8-4Z"></path>
                                        <path d="M4 12l8 4 8-4"></path>
                                        <path d="M4 17l8 4 8-4"></path>
                                    </svg>
                                </span>
                                <div>
                                    <strong>Category Icon</strong>
                                    <small>Replace the small category icon.</small>
                                </div>
                            </div>

                            <div class="current-preview-box media-current-preview icon-current-preview {{ $iconUrl ? 'has-current' : 'is-empty' }}">
                                @if ($iconUrl)
                                    <img src="{{ $iconUrl }}" alt="{{ $category->name }}">
                                    <span>Current icon</span>
                                @else
                                    <strong>{{ $categoryInitial($category) }}</strong>
                                    <span>No icon</span>
                                @endif
                            </div>

                            <label class="upload-field icon-upload-field">
                                <input type="file" name="icon" accept="image/*,.svg" data-file-input data-upload-label="Icon">
                                <img src="" alt="Icon preview" class="upload-preview">

                                <span class="upload-icon">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M12 5v14"></path>
                                        <path d="M5 12h14"></path>
                                    </svg>
                                </span>
                                <strong>Change Icon</strong>
                                <small>Leave blank if not replacing it.</small>
                                <em data-upload-meta>No new file selected</em>
                            </label>

                            <p class="upload-note">SVG is recommended so the icon stays sharp.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="categoryDescription{{ $category->id }}">Description</label>
                        <textarea id="categoryDescription{{ $category->id }}" name="description" rows="4" placeholder="Category description">{{ old('description', $category->description) }}</textarea>
                    </div>

                    <div class="switch-grid">
                        <div class="switch-row">
                            <span>
                                Status Active
                                <small>The category can appear in the catalog.</small>
                            </span>

                            <label class="form-switch">
                                <input type="hidden" name="status" value="inactive">
                                <input type="checkbox" name="status" value="active" {{ old('status', $category->status) === 'active' ? 'checked' : '' }}>
                                <span></span>
                            </label>
                        </div>

                        <div class="switch-row">
                            <span>
                                Featured
                                <small>Prioritize in the selected category area.</small>
                            </span>

                            <label class="form-switch">
                                <input type="hidden" name="is_featured" value="0">
                                <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $category->is_featured ? '1' : '0') === '1' ? 'checked' : '' }}>
                                <span></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="category-modal-footer">
                    <button type="button" class="modal-cancel-btn" data-modal-close>
                        Cancel
                    </button>

                    <button type="submit" class="modal-save-btn">
                        Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete Category Modal --}}
    <div class="category-modal" id="deleteCategoryModal{{ $category->id }}" aria-hidden="true">
        <div class="category-modal-dialog delete" role="dialog" aria-modal="true" aria-labelledby="deleteCategoryTitle{{ $category->id }}">
            <div class="delete-icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M5 7h14"></path>
                    <path d="M9 7V5h6v2"></path>
                    <path d="m7 7 1 14h8l1-14"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                </svg>
            </div>

            <h3 id="deleteCategoryTitle{{ $category->id }}">Delete Category?</h3>

            <p>
                Category <strong>{{ $category->name }}</strong> will be deleted from the list.
                Services already using this category will lose their category relationship.
            </p>

            <div class="delete-actions">
                <button type="button" class="modal-cancel-btn" data-modal-close>
                    Cancel
                </button>

                <form action="{{ route('admin.service-categories.destroy', $category->id) }}" method="POST" class="delete-category-form">
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="delete-confirm-btn">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection

@push('scripts')
    <script src="{{ asset('admin/js/service-categories.js') }}"></script>
@endpush
