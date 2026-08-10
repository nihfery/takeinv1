@extends('admin.layouts.app')

@section('title', 'Chat - JasaKu')
@section('page_title', 'Chat')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/support-chat.css') }}">
@endpush

@section('content')
@php
    $threadQuery = fn ($thread) => array_filter([
        'thread' => $thread->id,
        'search' => $search ?: null,
    ]);

    $ticketLabels = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
        'none' => 'No ticket yet',
    ];

    $isTicketList = ($activeTab ?? 'messages') === 'tickets';
    $isRoomOpen = ! $isTicketList && $activeThread && $activeThreadCanChat;
    $isTicketDetailOpen = $isTicketList && ($activeTicketThread ?? null);
    $isPanelOpen = $isRoomOpen || $isTicketDetailOpen;
    $activeChatTotal = $threads->count();
    $ticketTotal = $ticketThreads->count();
@endphp

<section class="support-chat-page support-chat-admin support-chat-modern {{ $isPanelOpen ? 'has-active-room' : 'is-chat-list' }} {{ $isTicketList ? 'is-ticket-list' : '' }}" data-support-chat>
    <div class="support-chat-route">
        <div class="support-chat-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Chat</strong>
        </div>
    </div>

    @if (session('success'))
        <div class="support-ticket-alert success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="support-ticket-alert error">{{ $errors->first() }}</div>
    @endif

    <div class="support-chat-shell support-chat-shell-modern">
        <aside class="support-chat-list support-chat-directory">
            <div class="support-directory-head">
                <form method="GET" action="{{ route('admin.chat.index') }}" class="support-chat-search compact">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                        <path d="M21 21L16.7 16.7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>

                    @if ($isTicketList)
                        <input type="hidden" name="tab" value="tickets">
                    @endif

                    <input type="text" name="search" value="{{ $search }}" placeholder="Search" data-chat-search>
                </form>

                <div class="support-message-tabs">
                    <a href="{{ route('admin.chat.index', array_filter(['search' => $search ?: null])) }}" class="{{ $isTicketList ? '' : 'active' }}">
                        <span>All</span>
                        <b>{{ number_format($activeChatTotal) }}</b>
                    </a>
                    <a href="{{ route('admin.chat.index', array_filter(['tab' => 'tickets', 'search' => $search ?: null])) }}" class="{{ $isTicketList ? 'active' : '' }}">
                        <span>Tickets</span>
                        <b>{{ number_format($ticketTotal) }}</b>
                    </a>
                </div>
            </div>

            <div class="support-thread-list" data-thread-list>
                @if ($isTicketList)
                    @forelse ($ticketThreads as $thread)
                        @php
                            $provider = $thread->provider;
                            $requester = $thread->providerUser ?: $provider;
                            $branchName = $requester?->providerBranch?->branch_name;
                            $requesterLabel = $branchName ? "Branch: {$branchName}" : 'Main provider';
                            $ticketStatus = $thread->ticket_status ?? 'pending';
                            $isActiveTicket = $activeTicketThread && (int) $activeTicketThread->id === (int) $thread->id;
                            $initial = strtoupper(substr($requester->name ?? 'P', 0, 1));
                            $ticketPreview = \Illuminate\Support\Str::limit($thread->ticket_subject ?: 'Provider chat ticket', 48);
                        @endphp

                        <a
                            href="{{ route('admin.chat.index', array_filter(['tab' => 'tickets', 'ticket' => $thread->id, 'search' => $search ?: null])) }}"
                            class="support-thread-item support-ticket-list-item {{ $isActiveTicket ? 'active' : '' }}"
                            data-thread-id="{{ $thread->id }}"
                            data-chat-row
                            data-chat-label="{{ \Illuminate\Support\Str::lower(($requester->name ?? 'Provider') . ' ' . $requesterLabel . ' ' . ($provider->name ?? 'Provider') . ' ' . $ticketPreview) }}"
                        >
                            <span class="support-avatar">{{ $initial }}</span>

                            <span class="support-thread-copy">
                                <strong>{{ $requester->name ?? 'Provider' }}</strong>
                                <small class="support-thread-subline">
                                    <span class="support-thread-subtitle">{{ $requesterLabel }} | {{ $provider->name ?? 'Provider' }}</span>
                                    <span class="support-ticket-mini {{ $ticketStatus }}">
                                        {{ $ticketLabels[$ticketStatus] ?? ucfirst($ticketStatus) }}
                                    </span>
                                </small>
                                <em class="support-thread-last">{{ $ticketPreview }}</em>
                            </span>

                            <span class="support-thread-meta">
                                <time>{{ $thread->ticket_requested_at?->format('H:i') }}</time>
                            </span>
                        </a>
                    @empty
                        <div class="support-chat-empty compact">
                            <strong>No tickets yet</strong>
                            <span>Incoming provider tickets appear here.</span>
                        </div>
                    @endforelse
                @else
                    @forelse ($threads as $thread)
                        @php
                            $provider = $thread->provider;
                            $requester = $thread->providerUser ?: $provider;
                            $branchName = $requester?->providerBranch?->branch_name;
                            $requesterLabel = $branchName ? "Branch: {$branchName}" : 'Main provider';
                            $lastMessage = $thread->lastMessage;
                            $ticketStatus = $thread->ticket_status ?? 'none';
                            $unreadCount = (int) ($thread->unread_count ?? 0);
                            $isActive = $activeThread && (int) $activeThread->id === (int) $thread->id;
                            $initial = strtoupper(substr($requester->name ?? 'P', 0, 1));
                            $previewBody = $lastMessage ? trim((string) $lastMessage->body) : '';
                            $preview = $lastMessage
                                ? ($previewBody !== ''
                                    ? \Illuminate\Support\Str::limit($previewBody, 48)
                                    : ($lastMessage->attachment_path ? 'Mengirim gambar' : ''))
                                : \Illuminate\Support\Str::limit($thread->ticket_subject ?: 'Provider chat ticket', 48);
                        @endphp

                        <a
                            href="{{ route('admin.chat.index', $threadQuery($thread)) }}"
                            class="support-thread-item {{ $isActive ? 'active' : '' }}"
                            data-thread-id="{{ $thread->id }}"
                            data-chat-row
                            data-chat-label="{{ \Illuminate\Support\Str::lower(($requester->name ?? 'Provider') . ' ' . $requesterLabel . ' ' . ($provider->name ?? 'Provider')) }}"
                        >
                            <span class="support-avatar">{{ $initial }}</span>

                            <span class="support-thread-copy">
                                <strong>{{ $requester->name ?? 'Provider' }}</strong>
                                <small class="support-thread-subline">
                                    <span class="support-thread-subtitle">{{ $requesterLabel }} | {{ $provider->name ?? 'Provider' }}</span>
                                    <span class="support-ticket-mini {{ $ticketStatus }}" data-thread-status="{{ $thread->id }}">
                                        {{ $ticketLabels[$ticketStatus] ?? ucfirst($ticketStatus) }}
                                    </span>
                                </small>
                                <em class="support-thread-last" data-thread-last>
                                    {{ $preview }}
                                </em>
                            </span>

                            <span class="support-thread-meta">
                                <time data-thread-time>{{ $thread->last_message_at?->format('H:i') }}</time>
                                <b class="{{ $unreadCount > 0 ? '' : 'is-hidden' }}" data-thread-unread>{{ $unreadCount }}</b>
                            </span>
                        </a>
                    @empty
                        <div class="support-chat-empty compact">
                            <strong>No active chats yet</strong>
                            <span>Chats appear after tickets are approved from the Tickets menu.</span>
                        </div>
                    @endforelse
                @endif
            </div>
        </aside>

        @if ($isTicketList)
            <div class="support-chat-panel support-chat-conversation support-ticket-board">
                @if ($activeTicketThread)
                    @php
                        $activeTicketProvider = $activeTicketThread->provider;
                        $activeTicketRequester = $activeTicketThread->providerUser ?: $activeTicketProvider;
                        $activeTicketBranchName = $activeTicketRequester?->providerBranch?->branch_name;
                        $activeTicketRequesterLabel = $activeTicketBranchName ? "Branch: {$activeTicketBranchName}" : 'Main provider';
                        $activeTicketProfile = $activeTicketProvider?->providerProfile;
                        $activeTicketStatus = $activeTicketThread->ticket_status ?? 'pending';
                        $activeTicketInitial = strtoupper(substr($activeTicketRequester->name ?? 'P', 0, 1));
                    @endphp

                    <div class="support-chat-panel-head modern support-ticket-detail-head">
                        <a
                            href="{{ route('admin.chat.index', array_filter(['tab' => 'tickets', 'search' => $search ?: null])) }}"
                            class="support-chat-back"
                            title="Back to ticket list"
                            aria-label="Back to ticket list"
                        >
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Back</span>
                        </a>

                        <div class="support-chat-person">
                            <span class="support-avatar large">{{ $activeTicketInitial }}</span>

                            <div>
                                <strong>{{ $activeTicketRequester->name ?? 'Provider' }}</strong>
                                <span>{{ $activeTicketRequesterLabel }} | {{ $activeTicketRequester->email ?? '-' }}</span>
                            </div>
                        </div>

                        <div class="support-chat-tags">
                            <span class="support-ticket-badge {{ $activeTicketStatus }}">
                                {{ $ticketLabels[$activeTicketStatus] ?? ucfirst($activeTicketStatus) }}
                            </span>
                            <span>{{ ucfirst($activeTicketProfile->status ?? 'inactive') }}</span>
                            <span>{{ ucfirst($activeTicketProfile->document_status ?? 'pending') }}</span>
                        </div>
                    </div>

                    <div class="support-ticket-review support-ticket-detail-review">
                        <div class="support-ticket-card support-ticket-detail-card">
                            <span class="support-ticket-kicker">Ticket Request</span>
                            <h2>{{ $activeTicketThread->ticket_subject ?: 'Provider chat request' }}</h2>
                            <p>{{ $activeTicketThread->ticket_body ?: 'The provider has not written ticket details yet.' }}</p>

                            <dl class="support-ticket-meta">
                                <div>
                                    <dt>Diajukan</dt>
                                    <dd>{{ $activeTicketThread->ticket_requested_at?->format('d M Y H:i') ?? '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Direview</dt>
                                    <dd>{{ $activeTicketThread->ticket_reviewed_at?->format('d M Y H:i') ?? '-' }}</dd>
                                </div>
                            </dl>

                            @if (in_array($activeTicketStatus, ['rejected', 'closed'], true) && $activeTicketThread->ticket_rejection_reason)
                                <div class="support-ticket-note">
                                    {{ $activeTicketThread->ticket_rejection_reason }}
                                </div>
                            @endif

                            <div class="support-ticket-actions">
                                @if (in_array($activeTicketStatus, ['pending', 'rejected'], true))
                                    <form method="POST" action="{{ route('admin.tickets.approve', $activeTicketThread) }}">
                                        @csrf
                                        <input type="hidden" name="return_to" value="chat">

                                        <button type="submit" class="support-ticket-primary">
                                            Approve Ticket
                                        </button>
                                    </form>
                                @endif

                                @if ($activeTicketStatus === 'pending')
                                    <form method="POST" action="{{ route('admin.tickets.reject', $activeTicketThread) }}" class="support-ticket-reject-form">
                                        @csrf
                                        <input type="hidden" name="return_to" value="chat">

                                        <input type="text" name="reason" maxlength="500" placeholder="Optional rejection reason">

                                        <button type="submit" class="support-ticket-secondary">
                                            Tolak
                                        </button>
                                    </form>
                                @endif

                                @if ($activeTicketStatus === 'approved')
                                    <a href="{{ route('admin.chat.index', ['thread' => $activeTicketThread->id]) }}" class="support-ticket-primary support-ticket-link">
                                        Open Chat
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="support-chat-empty full modern support-ticket-board-empty">
                        <span class="support-empty-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M4 5h16v14H4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <strong>Choose a Ticket</strong>
                        <span>Choose a ticket from the list to open its details.</span>
                    </div>
                @endif
            </div>
        @else
        <div class="support-chat-panel support-chat-conversation">
            @if ($activeThread)
                @php
                    $activeProvider = $activeThread->provider;
                    $activeRequester = $activeThread->providerUser ?: $activeProvider;
                    $activeBranchName = $activeRequester?->providerBranch?->branch_name;
                    $activeRequesterLabel = $activeBranchName ? "Branch: {$activeBranchName}" : 'Main provider';
                    $ticketStatus = $activeThread->ticket_status ?? 'none';
                    $activeInitial = strtoupper(substr($activeRequester->name ?? 'P', 0, 1));
                @endphp

                <div class="support-chat-panel-head modern">
                    <a
                        href="{{ route('admin.chat.index', array_filter(['list' => 1, 'search' => $search ?: null])) }}"
                        class="support-chat-back"
                        title="Back to chat list"
                        aria-label="Back to chat list"
                    >
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Back</span>
                    </a>

                    <div class="support-chat-person">
                        <span class="support-avatar large support-room-avatar {{ $activeThreadCanChat ? 'is-active' : 'is-inactive' }}">
                            <span>{{ $activeInitial }}</span>
                        </span>

                        <div>
                            <span class="support-room-title-row">
                                <strong>{{ $activeRequester->name ?? 'Provider' }}</strong>
                                <span
                                    class="support-room-status {{ $activeThreadCanChat ? 'is-active' : 'is-inactive' }}"
                                    role="img"
                                    title="{{ $activeThreadCanChat ? 'Active' : 'Inactive' }}"
                                    aria-label="{{ $activeThreadCanChat ? 'Active' : 'Inactive' }}"
                                ></span>
                            </span>
                            <span class="support-room-subtitle">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M4 4h16v16H4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                {{ $activeRequesterLabel }} | {{ $activeRequester->email ?? '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="support-chat-tags">
                        <span class="support-ticket-badge {{ $ticketStatus }}" data-thread-status="{{ $activeThread->id }}">
                            {{ $ticketLabels[$ticketStatus] ?? ucfirst($ticketStatus) }}
                        </span>
                    </div>

                    <div class="support-chat-actions">
                        @if ($activeThreadCanChat)
                            <form method="POST" action="{{ route('admin.chat.ticket.end', $activeThread) }}" class="support-chat-end-form support-chat-end-compact" data-chat-end-form>
                                @csrf

                                <button type="submit" title="End chat">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                    <span>Akhiri</span>
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('admin.tickets.index', ['thread' => $activeThread->id, 'status' => $ticketStatus]) }}" title="Tickets" aria-label="Open chat ticket">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M4 5h16v14H4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                <path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </a>

                    </div>
                </div>

                @if (! $activeThreadCanChat)
                    <div class="support-ticket-review">
                        <div class="support-ticket-card">
                            <span class="support-ticket-kicker">Chat Ticket</span>
                            <h2>{{ $activeThread->ticket_subject ?: 'Provider chat request' }}</h2>
                            <p>Ticket requests and approvals are managed in the Tickets menu.</p>

                            <dl class="support-ticket-meta">
                                <div>
                                    <dt>Diajukan</dt>
                                    <dd>{{ $activeThread->ticket_requested_at?->format('d M Y H:i') ?? '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Status</dt>
                                    <dd>{{ $ticketLabels[$ticketStatus] ?? ucfirst($ticketStatus) }}</dd>
                                </div>
                            </dl>

                            @if (in_array($ticketStatus, ['rejected', 'closed'], true) && $activeThread->ticket_rejection_reason)
                                <div class="support-ticket-note">
                                    {{ $activeThread->ticket_rejection_reason }}
                                </div>
                            @endif

                            <div class="support-ticket-actions">
                                <a href="{{ route('admin.tickets.index', ['thread' => $activeThread->id, 'status' => $ticketStatus]) }}" class="support-ticket-primary support-ticket-link">
                                    Open Tickets
                                </a>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="support-chat-messages modern" data-chat-messages>
                        @forelse ($messages as $message)
                            <div
                                class="support-message {{ $message['is_mine'] ? 'is-mine' : '' }}"
                                data-message-id="{{ $message['id'] }}"
                                data-message-sender-id="{{ $message['sender_id'] }}"
                                data-message-sender-role="{{ $message['sender_role'] }}"
                                data-message-created-at="{{ $message['created_at'] }}"
                            >
                                <div class="support-bubble">
                                    <div class="support-bubble-meta">
                                        <span class="support-sender-line">
                                            <strong>{{ $message['sender_name'] }}</strong>
                                            @if (($message['sender_role'] ?? '') === 'admin')
                                                <span class="support-admin-badge">Admin</span>
                                                <span class="support-verified-check support-message-check" title="Official admin account" aria-label="Official admin account">
                                                    <svg viewBox="0 0 20 20" fill="none">
                                                        <circle cx="10" cy="10" r="9" fill="currentColor"/>
                                                        <path d="M6 10.2l2.5 2.5L14.2 7" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                            @endif
                                        </span>
                                        <span class="support-bubble-time">{{ $message['sent_at'] }}</span>
                                    </div>

                                    @if (! empty($message['attachment']) && ($message['attachment']['type'] ?? '') === 'image')
                                        <a href="{{ $message['attachment']['url'] }}" class="support-message-image" target="_blank" rel="noopener">
                                            <img src="{{ $message['attachment']['url'] }}" alt="{{ $message['attachment']['name'] ?: 'Chat image' }}">
                                        </a>
                                    @endif

                                    @if ($message['body'] !== '')
                                        <p>{!! nl2br(e($message['body'])) !!}</p>
                                    @endif

                                    @if ($message['is_mine'])
                                        <div class="support-bubble-footer">
                                            <span
                                                class="support-message-status is-{{ $message['delivery_status'] }}"
                                                data-message-status
                                                data-status="{{ $message['delivery_status'] }}"
                                            >
                                                {{ $message['delivery_label'] }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="support-chat-empty" data-chat-empty>
                                <span class="support-empty-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M21 15a4 4 0 0 1-4 4H8l-5 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                        <path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <strong>No messages yet</strong>
                                <span>Start a conversation with this provider.</span>
                            </div>
                        @endforelse
                    </div>

                    <form class="support-chat-compose modern" data-chat-form enctype="multipart/form-data">
                        <div class="support-compose-tools">
                            <button type="button" title="Emoticon" data-emoji-toggle>
                                <svg viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                                    <path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>

                                    <button type="button" title="Send image" data-chat-image-trigger>
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="9" cy="10" r="1.5" fill="currentColor"/>
                                    <path d="M6.5 17l4.2-4.2 2.8 2.8 1.8-1.8L18 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>

                        <input type="file" name="image" accept="image/*" data-chat-image hidden>

                        <div class="support-emoji-panel" data-emoji-panel hidden>
                            @foreach (['😀', '😊', '🙏', '👍', '👌', '✨', '❤️', '😂', '😮', '😢', '🔥', '✅'] as $emoji)
                                <button type="button" data-emoji="{{ $emoji }}">{{ $emoji }}</button>
                            @endforeach
                        </div>

                        <div class="support-compose-field">
                            <textarea name="body" rows="1" placeholder="Write a message..." maxlength="2000" data-chat-input></textarea>

                            <div class="support-file-preview is-hidden" data-chat-file-preview>
                                <span data-chat-file-name></span>
                                <button type="button" title="Remove image" aria-label="Remove image" data-chat-file-clear>
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M18 6 6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" title="Kirim pesan">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M22 2L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Kirim</span>
                        </button>
                    </form>
                @endif
            @else
                <div class="support-chat-empty full modern">
                    <span class="support-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <strong>Choose a Chat</strong>
                    <span>Choose a provider from the list to open the chat room.</span>
                </div>
            @endif
        </div>
        @endif
    </div>

    <div class="support-confirm-modal" data-chat-end-dialog hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="chatEndConfirmTitle">
        <button type="button" class="support-confirm-backdrop" data-chat-end-cancel aria-label="Cancel"></button>

        <div class="support-confirm-card">
            <span class="support-confirm-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 17h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    <path d="M10.3 4.2 2.8 17.2A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.8L13.7 4.2a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
            </span>

            <div class="support-confirm-copy">
                <strong id="chatEndConfirmTitle">End this chat?</strong>
                <span>Are you sure you want to end this chat? The provider must submit another ticket to open the next chat session.</span>
            </div>

            <div class="support-confirm-actions">
                <button type="button" class="support-confirm-secondary" data-chat-end-cancel>Cancel</button>
                <button type="button" class="support-confirm-danger" data-chat-end-confirm>End Chat</button>
            </div>
        </div>
    </div>

    <script type="application/json" id="supportChatConfig">
        {!! json_encode($chatConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('js/support-chat.js') }}" defer></script>
@endpush
