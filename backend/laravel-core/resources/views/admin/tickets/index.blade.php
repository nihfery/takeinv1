@extends('admin.layouts.app')

@section('title', 'Tickets - JasaKu')
@section('page_title', 'Tickets')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/support-chat.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/css/pages/tickets.css') }}">
@endpush

@section('content')
@php
    $ticketCollection = $threads ?? collect();
    $statusCounts = $statusCounts ?? collect();
    $hasPaginator = is_object($ticketCollection)
        && method_exists($ticketCollection, 'links')
        && method_exists($ticketCollection, 'firstItem');

    $firstItem = $hasPaginator ? ($ticketCollection->firstItem() ?? 0) : 0;
    $lastItem = $hasPaginator ? ($ticketCollection->lastItem() ?? 0) : (is_countable($ticketCollection) ? count($ticketCollection) : 0);
    $totalItem = $hasPaginator ? $ticketCollection->total() : (is_countable($ticketCollection) ? count($ticketCollection) : 0);

    $filters = [
        'status' => $status ?? request('status', 'pending'),
        'search' => $search ?? request('search', ''),
        'per_page' => $perPage ?? request('per_page', 10),
        'sort_by' => $sortBy ?? request('sort_by', 'requested_at'),
        'sort_direction' => $sortDirection ?? request('sort_direction', 'desc'),
        'thread' => request('thread'),
        'page' => request('page'),
    ];

    $ticketLabels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
    ];

    $statusTabs = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
        'all' => 'All Tickets',
    ];

    $sortOptions = [
        'requested_at' => 'Requested',
        'reviewed_at' => 'Reviewed',
        'last_message_at' => 'Last Chat',
        'status' => 'Status',
        'subject' => 'Subject',
        'requester' => 'Requester',
        'provider' => 'Provider',
    ];

    $summary = $summary ?? [
        'total' => (int) ($statusCounts?->sum() ?? 0),
        'pending' => (int) ($statusCounts['pending'] ?? 0),
        'approved' => (int) ($statusCounts['approved'] ?? 0),
        'completed' => (int) ($statusCounts['rejected'] ?? 0) + (int) ($statusCounts['closed'] ?? 0),
    ];

    $statusClass = fn ($value) => match ($value ?: 'pending') {
        'approved', 'active', 'verified' => 'success',
        'pending', 'submitted' => 'warning',
        'closed' => 'neutral',
        'rejected', 'inactive' => 'danger',
        default => 'info',
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

    $requesterFor = fn ($thread) => $thread->providerUser ?: $thread->provider;
    $branchNameFor = fn ($requester) => $requester?->providerBranch?->branch_name;

    $cleanQuery = function (array $query) {
        return collect($query)
            ->reject(function ($value, $key) {
                if ($value === null || $value === '') {
                    return true;
                }

                if ($key === 'status' && $value === 'pending') {
                    return true;
                }

                if ($key === 'per_page' && (int) $value === 10) {
                    return true;
                }

                if ($key === 'sort_by' && $value === 'requested_at') {
                    return true;
                }

                if ($key === 'sort_direction' && $value === 'desc') {
                    return true;
                }

                if ($key === 'page' && (int) $value <= 1) {
                    return true;
                }

                return false;
            })
            ->all();
    };

    $queryFor = fn (array $overrides = []) => $cleanQuery(array_merge($filters, $overrides));

    $threadQuery = fn ($thread) => $queryFor([
        'thread' => $thread->id,
        'page' => $hasPaginator ? $ticketCollection->currentPage() : null,
    ]);

    $sortQueryFor = function (string $key) use ($queryFor, $filters) {
        $currentSort = $filters['sort_by'] ?? 'requested_at';
        $currentDirection = $filters['sort_direction'] ?? 'desc';

        return $queryFor([
            'sort_by' => $key,
            'sort_direction' => $currentSort === $key && $currentDirection === 'asc' ? 'desc' : 'asc',
            'page' => null,
        ]);
    };

    $sortIconClass = fn (string $key, string $direction) => (($filters['sort_by'] ?? 'requested_at') === $key && ($filters['sort_direction'] ?? 'desc') === $direction) ? 'active' : '';

    $hasActiveFilters = ($filters['status'] ?? 'pending') !== 'pending'
        || ($filters['search'] ?? '') !== ''
        || (int) ($filters['per_page'] ?? 10) !== 10
        || ($filters['sort_by'] ?? 'requested_at') !== 'requested_at'
        || ($filters['sort_direction'] ?? 'desc') !== 'desc';

    $hasMobileAdvancedFilters = (($filters['status'] ?? 'pending') !== 'pending')
        || ((int) ($filters['per_page'] ?? 10) !== 10)
        || (($filters['sort_by'] ?? 'requested_at') !== 'requested_at')
        || (($filters['sort_direction'] ?? 'desc') !== 'desc');
@endphp

<section class="admin-booking-page admin-ticket-page">
    <div class="admin-booking-route">
        <div class="admin-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Support</strong>
            <span>&rsaquo;</span>
            <strong>Tickets</strong>
        </div>
    </div>

    @if (session('success'))
        <div class="support-ticket-alert success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="support-ticket-alert error">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="admin-booking-summary-grid">
        <div class="admin-booking-summary-card pink">
            <span>Total Ticket</span>
            <strong>{{ number_format((int) $summary['total']) }}</strong>
            <small>All provider support tickets</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Pending</span>
            <strong>{{ number_format((int) $summary['pending']) }}</strong>
            <small>Waiting for admin review</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Approved</span>
            <strong>{{ number_format((int) $summary['approved']) }}</strong>
            <small>Chat is available</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Finished</span>
            <strong>{{ number_format((int) $summary['completed']) }}</strong>
            <small>Rejected or closed</small>
        </div>
    </div>

    <div class="admin-booking-card admin-ticket-card">
        <div class="admin-booking-tabs">
            @foreach ($statusTabs as $key => $label)
                @php
                    $count = $key === 'all'
                        ? (int) $statusCounts->sum()
                        : (int) ($statusCounts[$key] ?? 0);
                @endphp

                <a href="{{ route('admin.tickets.index', $queryFor(['status' => $key, 'thread' => null, 'page' => null])) }}"
                   class="admin-booking-tab {{ ($filters['status'] ?? 'pending') === $key ? 'active' : '' }}">
                    {{ $label }}
                    <span class="admin-ticket-tab-count">{{ number_format($count) }}</span>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.tickets.index') }}" class="admin-booking-filter-panel compact {{ $hasMobileAdvancedFilters ? 'is-expanded' : '' }}">
            <div class="admin-booking-filter-row admin-ticket-filter-row" id="ticketFilterRow">
                <label class="admin-booking-field search">
                    <div class="admin-booking-search-box">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>

                        <input id="ticketSearchInput"
                               type="text"
                               name="search"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Search ticket">
                    </div>
                </label>

                <button type="submit" class="admin-booking-mobile-search-submit" aria-label="Search ticket">
                    Search
                </button>

                <button type="button"
                        class="admin-booking-mobile-filter-toggle {{ $hasMobileAdvancedFilters ? 'active' : '' }}"
                        aria-controls="ticketFilterRow"
                        aria-expanded="{{ $hasMobileAdvancedFilters ? 'true' : 'false' }}">
                    Filter
                </button>

                <label class="admin-booking-field mini">
                    <select name="status" aria-label="Ticket status" title="Ticket status">
                        @foreach ($statusTabs as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['status'] ?? 'pending') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-booking-field mini">
                    <select name="sort_by" aria-label="Sort by" title="Sort by">
                        @foreach ($sortOptions as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['sort_by'] ?? 'requested_at') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-booking-field mini">
                    <select name="sort_direction" aria-label="Sort direction" title="Sort direction">
                        <option value="desc" {{ ($filters['sort_direction'] ?? 'desc') === 'desc' ? 'selected' : '' }}>Newest</option>
                        <option value="asc" {{ ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'selected' : '' }}>Oldest</option>
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
                        <a href="{{ route('admin.tickets.index') }}">Reset</a>
                    @endif
                </div>
            </div>

            <div class="admin-booking-filter-meta">
                <span class="admin-booking-filter-count">{{ number_format($totalItem) }} ticket</span>

                @if (($filters['search'] ?? '') !== '')
                    <span>Search: {{ $filters['search'] }}</span>
                @endif

                <span>Status: {{ $statusTabs[$filters['status'] ?? 'pending'] ?? 'Pending' }}</span>
                <span>Sort: {{ $sortOptions[$filters['sort_by'] ?? 'requested_at'] ?? 'Requested' }}</span>

                @if (($filters['sort_direction'] ?? 'desc') === 'asc')
                    <span>Direction: Oldest</span>
                @endif
            </div>
        </form>

        <div class="admin-booking-mobile-list admin-ticket-mobile-list">
            @forelse ($ticketCollection as $thread)
                @php
                    $provider = $thread->provider;
                    $requester = $requesterFor($thread);
                    $profile = $provider?->providerProfile;
                    $ticketStatus = $thread->ticket_status ?? 'pending';
                    $subject = $thread->ticket_subject ?: 'Provider chat request';
                    $ticketBody = $thread->ticket_body ?: 'The provider has not written ticket details yet.';
                @endphp

                <article class="admin-booking-mobile-card admin-ticket-mobile-card">
                    <header>
                        <div>
                            <strong>{{ $subject }}</strong>
                            <span>Ticket #{{ $thread->id }} &middot; {{ $formatDateTime($thread->ticket_requested_at) }}</span>
                        </div>

                        <b>{{ $ticketLabels[$ticketStatus] ?? ucfirst($ticketStatus) }}</b>
                    </header>

                    <div class="admin-booking-mobile-main">
                        <div>
                            <span>Requester</span>
                            <strong>{{ $requester->name ?? 'Provider' }}</strong>
                        </div>

                        <div>
                            <span>Provider</span>
                            <strong>{{ $provider->name ?? '-' }}</strong>
                        </div>
                    </div>

                    <p>{{ $ticketBody }}</p>

                    <footer>
                        <span class="admin-booking-status {{ $statusClass($ticketStatus) }}">
                            {{ $ticketLabels[$ticketStatus] ?? ucfirst($ticketStatus) }}
                        </span>
                        <span class="admin-booking-status {{ $statusClass($profile->document_status ?? 'pending') }}">
                            {{ ucfirst($profile->document_status ?? 'pending') }}
                        </span>
                        <a href="{{ route('admin.tickets.index', $threadQuery($thread)) }}" class="admin-ticket-mini-link">
                            Details
                        </a>
                    </footer>
                </article>
            @empty
                <div class="admin-booking-mobile-empty">
                    <strong>No ticket data found.</strong>
                    <p>Try changing the keyword, status, or ticket order.</p>
                </div>
            @endforelse
        </div>

        <div class="admin-booking-table-wrap admin-ticket-table-wrap">
            <table class="admin-booking-table detailed admin-ticket-table">
                <thead>
                    <tr>
                        <th>
                            <a href="{{ route('admin.tickets.index', $sortQueryFor('subject')) }}" class="admin-booking-sort {{ ($filters['sort_by'] ?? 'requested_at') === 'subject' ? 'active' : '' }}">
                                Ticket
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('subject', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('subject', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.tickets.index', $sortQueryFor('requester')) }}" class="admin-booking-sort {{ ($filters['sort_by'] ?? 'requested_at') === 'requester' ? 'active' : '' }}">
                                Requester
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('requester', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('requester', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.tickets.index', $sortQueryFor('provider')) }}" class="admin-booking-sort {{ ($filters['sort_by'] ?? 'requested_at') === 'provider' ? 'active' : '' }}">
                                Provider
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('provider', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('provider', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Branch</th>
                        <th>
                            <a href="{{ route('admin.tickets.index', $sortQueryFor('status')) }}" class="admin-booking-sort {{ ($filters['sort_by'] ?? 'requested_at') === 'status' ? 'active' : '' }}">
                                Status
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('status', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('status', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Provider Docs</th>
                        <th>
                            <a href="{{ route('admin.tickets.index', $sortQueryFor('requested_at')) }}" class="admin-booking-sort {{ ($filters['sort_by'] ?? 'requested_at') === 'requested_at' ? 'active' : '' }}">
                                Requested
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('requested_at', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('requested_at', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.tickets.index', $sortQueryFor('reviewed_at')) }}" class="admin-booking-sort {{ ($filters['sort_by'] ?? 'requested_at') === 'reviewed_at' ? 'active' : '' }}">
                                Review
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('reviewed_at', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('reviewed_at', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>
                            <a href="{{ route('admin.tickets.index', $sortQueryFor('last_message_at')) }}" class="admin-booking-sort {{ ($filters['sort_by'] ?? 'requested_at') === 'last_message_at' ? 'active' : '' }}">
                                Last Chat
                                <span class="admin-booking-sort-icons" aria-hidden="true">
                                    <span class="{{ $sortIconClass('last_message_at', 'asc') }}">&uarr;</span>
                                    <span class="{{ $sortIconClass('last_message_at', 'desc') }}">&darr;</span>
                                </span>
                            </a>
                        </th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($ticketCollection as $thread)
                        @php
                            $provider = $thread->provider;
                            $requester = $requesterFor($thread);
                            $profile = $provider?->providerProfile;
                            $ticketStatus = $thread->ticket_status ?? 'pending';
                            $isActive = $activeThread && (int) $activeThread->id === (int) $thread->id;
                            $requesterInitial = strtoupper(substr($requester->name ?? 'P', 0, 1));
                            $providerInitial = strtoupper(substr($provider->name ?? 'P', 0, 1));
                            $branchName = $branchNameFor($requester);
                            $roleName = $requester?->providerRole?->role_name;
                            $lastMessageBody = trim((string) ($thread->lastMessage?->body ?? ''));
                            $lastMessagePreview = $thread->lastMessage
                                ? ($lastMessageBody !== '' ? \Illuminate\Support\Str::limit($lastMessageBody, 42) : ($thread->lastMessage->attachment_path ? 'Mengirim gambar' : 'No message body'))
                                : 'No messages yet';
                        @endphp

                        <tr class="{{ $isActive ? 'is-selected' : '' }}">
                            <td>
                                <div class="admin-ticket-subject-cell">
                                    <strong>{{ $thread->ticket_subject ?: 'Provider chat request' }}</strong>
                                    <small>Ticket #{{ $thread->id }}</small>
                                    <em>{{ \Illuminate\Support\Str::limit($thread->ticket_body ?: 'The provider has not written ticket details yet.', 70) }}</em>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-person">
                                    <span>{{ $requesterInitial }}</span>
                                    <div>
                                        <strong>{{ $requester->name ?? 'Provider' }}</strong>
                                        <small>{{ $requester->email ?? '-' }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-person">
                                    <span>{{ $providerInitial }}</span>
                                    <div>
                                        <strong>{{ $provider->name ?? '-' }}</strong>
                                        <small>{{ $provider->email ?? 'Provider account' }}</small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-date">
                                    <strong>{{ $branchName ?: 'Main provider' }}</strong>
                                    <small>{{ $roleName ?: 'Owner account' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-mode-stack">
                                    <span class="admin-booking-status {{ $statusClass($ticketStatus) }}">
                                        {{ $ticketLabels[$ticketStatus] ?? ucfirst($ticketStatus) }}
                                    </span>
                                    <small>{{ ucfirst($thread->status ?? 'open') }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-mode-stack">
                                    <span class="admin-booking-status {{ $statusClass($profile->document_status ?? 'pending') }}">
                                        {{ ucfirst($profile->document_status ?? 'pending') }}
                                    </span>
                                    <small>{{ ucfirst($profile->status ?? 'inactive') }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-timeline">
                                    <span>{{ $formatDate($thread->ticket_requested_at) }}</span>
                                    <small>{{ $thread->ticket_requested_at?->format('H:i') ?? '-' }}</small>
                                    <small>By {{ $thread->opener->name ?? $requester->name ?? 'Provider' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-timeline">
                                    <span>{{ $formatDate($thread->ticket_reviewed_at) }}</span>
                                    <small>{{ $thread->ticket_reviewed_at?->format('H:i') ?? '-' }}</small>
                                    <small>{{ $thread->ticketReviewer->name ?? 'Not reviewed' }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-booking-service-cell">
                                    <strong>{{ $lastMessagePreview }}</strong>
                                    <small>{{ $formatDateTime($thread->last_message_at) }}</small>
                                </div>
                            </td>

                            <td>
                                <div class="admin-ticket-action-row">
                                    <a href="{{ route('admin.tickets.index', $threadQuery($thread)) }}" class="admin-ticket-action-btn">
                                        Details
                                    </a>

                                    @if (in_array($ticketStatus, ['pending', 'rejected'], true))
                                        <form method="POST" action="{{ route('admin.tickets.approve', $thread) }}">
                                            @csrf
                                            <button type="submit" class="admin-ticket-action-btn primary">
                                                Setujui
                                            </button>
                                        </form>
                                    @endif

                                    @if ($ticketStatus === 'approved')
                                        <a href="{{ route('admin.chat.index', ['thread' => $thread->id]) }}" class="admin-ticket-action-btn primary">
                                            Chat
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="admin-booking-empty">
                                <div>
                                    <span>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 5h16v14H4z"></path>
                                            <path d="M8 9h8"></path>
                                            <path d="M8 13h5"></path>
                                        </svg>
                                    </span>

                                    <strong>No ticket data found.</strong>
                                    <p>Try changing the keyword, status, or ticket order.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($activeThread)
            @php
                $activeProvider = $activeThread->provider;
                $activeRequester = $requesterFor($activeThread);
                $activeProfile = $activeProvider?->providerProfile;
                $activeStatus = $activeThread->ticket_status ?? 'pending';
                $activeBranchName = $branchNameFor($activeRequester);
                $activeRoleName = $activeRequester?->providerRole?->role_name;
                $activeLastMessage = $activeThread->lastMessage;
                $activeLastBody = trim((string) ($activeLastMessage?->body ?? ''));
                $activeLastPreview = $activeLastMessage
                    ? ($activeLastBody !== '' ? $activeLastBody : ($activeLastMessage->attachment_path ? 'Mengirim gambar' : 'No message body'))
                    : 'No chat messages yet.';
            @endphp

            <div class="admin-ticket-detail-grid">
                <div class="support-ticket-card admin-ticket-review-card">
                    <span class="support-ticket-kicker">Ticket #{{ $activeThread->id }}</span>
                    <div class="admin-ticket-detail-heading">
                        <h2>{{ $activeThread->ticket_subject ?: 'Provider chat request' }}</h2>
                        <span class="admin-booking-status {{ $statusClass($activeStatus) }}">
                            {{ $ticketLabels[$activeStatus] ?? ucfirst($activeStatus) }}
                        </span>
                    </div>

                    <p>{{ $activeThread->ticket_body ?: 'The provider has not written ticket details yet.' }}</p>

                    <dl class="support-ticket-meta admin-ticket-meta">
                        <div>
                            <dt>Diajukan</dt>
                            <dd>{{ $formatDateTime($activeThread->ticket_requested_at) }}</dd>
                        </div>

                        <div>
                            <dt>Direview</dt>
                            <dd>{{ $formatDateTime($activeThread->ticket_reviewed_at) }}</dd>
                        </div>

                        <div>
                            <dt>Reviewer</dt>
                            <dd>{{ $activeThread->ticketReviewer->name ?? '-' }}</dd>
                        </div>

                        <div>
                            <dt>Last Chat</dt>
                            <dd>{{ $formatDateTime($activeThread->last_message_at) }}</dd>
                        </div>
                    </dl>

                    @if (in_array($activeStatus, ['rejected', 'closed'], true) && $activeThread->ticket_rejection_reason)
                        <div class="support-ticket-note">
                            {{ $activeThread->ticket_rejection_reason }}
                        </div>
                    @endif

                    <div class="admin-ticket-last-message">
                        <strong>Last message</strong>
                        <span>{{ \Illuminate\Support\Str::limit($activeLastPreview, 180) }}</span>
                    </div>

                    <div class="support-ticket-actions admin-ticket-detail-actions">
                        @if (in_array($activeStatus, ['pending', 'rejected'], true))
                            <form method="POST" action="{{ route('admin.tickets.approve', $activeThread) }}">
                                @csrf

                                <button type="submit" class="support-ticket-primary">
                                    Approve Ticket
                                </button>
                            </form>
                        @endif

                        @if ($activeStatus === 'pending')
                            <form method="POST" action="{{ route('admin.tickets.reject', $activeThread) }}" class="support-ticket-reject-form admin-ticket-reject-form">
                                @csrf

                                <input type="text" name="reason" maxlength="500" placeholder="Optional rejection reason">

                                <button type="submit" class="support-ticket-secondary">
                                    Tolak
                                </button>
                            </form>
                        @endif

                        @if ($activeStatus === 'approved')
                            <a href="{{ route('admin.chat.index', ['thread' => $activeThread->id]) }}" class="support-ticket-primary support-ticket-link">
                                Open Chat
                            </a>
                        @endif
                    </div>
                </div>

                <aside class="admin-ticket-info-card">
                    <h3>Provider Details</h3>

                    <div class="admin-ticket-info-list">
                        <div>
                            <span>Requester</span>
                            <strong>{{ $activeRequester->name ?? 'Provider' }}</strong>
                            <small>{{ $activeRequester->email ?? '-' }}</small>
                        </div>

                        <div>
                            <span>Scope</span>
                            <strong>{{ $activeBranchName ?: 'Main provider' }}</strong>
                            <small>{{ $activeRoleName ?: 'Owner account' }}</small>
                        </div>

                        <div>
                            <span>Provider</span>
                            <strong>{{ $activeProvider->name ?? '-' }}</strong>
                            <small>{{ $activeProvider->email ?? '-' }}</small>
                        </div>

                        <div>
                            <span>Category</span>
                            <strong>{{ $activeProfile->category ?? '-' }}</strong>
                            <small>{{ $activeProfile->phone_number ?? 'No phone' }}</small>
                        </div>

                        <div>
                            <span>Account</span>
                            <strong>{{ ucfirst($activeProfile->status ?? 'inactive') }}</strong>
                            <small>Provider account status</small>
                        </div>

                        <div>
                            <span>Documents</span>
                            <strong>{{ ucfirst($activeProfile->document_status ?? 'pending') }}</strong>
                            <small>Document verification status</small>
                        </div>

                        <div>
                            <span>Opened By</span>
                            <strong>{{ $activeThread->opener->name ?? '-' }}</strong>
                            <small>{{ $activeThread->opener?->email ?? '-' }}</small>
                        </div>

                        <div>
                            <span>Closed By</span>
                            <strong>{{ $activeThread->closer->name ?? '-' }}</strong>
                            <small>{{ $formatDateTime($activeThread->closed_at) }}</small>
                        </div>
                    </div>
                </aside>
            </div>
        @else
            <div class="admin-ticket-detail-grid">
                <div class="admin-booking-empty admin-ticket-detail-empty">
                    <div>
                        <span>
                            <svg viewBox="0 0 24 24">
                                <path d="M4 5h16v14H4z"></path>
                                <path d="M8 9h8"></path>
                                <path d="M8 13h5"></path>
                            </svg>
                        </span>

                        <strong>Choose a Ticket</strong>
                        <p>Ticket review details will appear here once data is available.</p>
                    </div>
                </div>
            </div>
        @endif

        <div class="admin-booking-footer admin-ticket-footer">
            <p class="admin-booking-showing">
                <strong>{{ number_format($firstItem) }}-{{ number_format($lastItem) }}</strong>
                <span>/ {{ number_format($totalItem) }}</span>
            </p>

            @if ($hasPaginator)
                <div class="admin-booking-pagination">
                    @if ($ticketCollection->onFirstPage())
                        <span class="disabled">&lsaquo;</span>
                    @else
                        <a href="{{ $ticketCollection->previousPageUrl() }}" aria-label="Previous page">&lsaquo;</a>
                    @endif

                    <span class="active">{{ $ticketCollection->currentPage() }}</span>

                    @if ($ticketCollection->hasMorePages())
                        <a href="{{ $ticketCollection->nextPageUrl() }}" aria-label="Next page">&rsaquo;</a>
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
    const page = document.querySelector('.admin-ticket-page');

    if (!page || !window.fetch || !window.DOMParser || !window.history) {
        return;
    }

    const card = page.querySelector('.admin-ticket-card');
    const replaceSelectors = [
        '.admin-booking-summary-grid',
        '.admin-booking-tabs',
        '.admin-booking-filter-panel',
        '.admin-ticket-mobile-list',
        '.admin-ticket-table-wrap',
        '.admin-ticket-detail-grid',
        '.admin-ticket-footer',
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

        if (key === 'status' && value === 'pending') {
            return false;
        }

        if (key === 'sort_by' && value === 'requested_at') {
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

    const replaceTicketParts = (html) => {
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

    const loadTickets = async (url, options = {}) => {
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
                throw new Error(`Ticket filter failed with status ${response.status}`);
            }

            const html = await response.text();

            if (controller !== activeRequest) {
                return;
            }

            replaceTicketParts(html);

            if (shouldPush) {
                window.history.pushState({ adminTicketsAjax: true }, '', response.url);
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
        loadTickets(buildFilterUrl(form));
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

        const link = closestFromEvent(event, '.admin-booking-tabs a, .admin-booking-sort, .admin-booking-pagination a, .admin-booking-filter-buttons a, .admin-ticket-mini-link, .admin-ticket-action-btn[href]');

        if (!link) {
            return;
        }

        const url = new URL(link.href, window.location.origin);

        if (url.origin !== window.location.origin || url.pathname.includes('/chat')) {
            return;
        }

        event.preventDefault();
        loadTickets(url);
    });

    window.addEventListener('popstate', () => {
        loadTickets(new URL(window.location.href), { push: false });
    });

    window.addEventListener('app:notification-created', (event) => {
        const notification = event.detail?.notification || {};

        if (notification.type !== 'ticket.pending') {
            return;
        }

        const url = notification.url
            ? new URL(notification.url, window.location.origin)
            : new URL(window.location.href);

        if (url.origin !== window.location.origin || !url.pathname.includes('/tickets')) {
            return;
        }

        loadTickets(url, { push: false });
    });
})();
</script>
@endpush
