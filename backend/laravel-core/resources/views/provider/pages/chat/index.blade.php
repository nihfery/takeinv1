@extends('provider.layouts.dashboard')

@section('title', 'Chat - JasaKu')
@section('page_title', 'Chat')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/support-chat.css') }}">
@endpush

@section('content')
@php
    $authUser = request()->user();
    $search = trim((string) request()->query('search', ''));
    $threadQuery = fn ($thread) => array_filter([
        'thread' => $thread->id,
        'search' => $search ?: null,
    ]);
    $adminTicketStatus = $adminThread->ticket_status ?? 'none';
    $ticketLabels = [
        'none' => 'Not Submitted',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'closed' => 'Chat Ended',
    ];

    $otherParticipant = function ($thread) use ($authUser) {
        if (($thread->conversation_type ?? 'provider_admin') === 'provider_admin') {
            return null;
        }

        return (int) $thread->provider_user_id === (int) $authUser->id
            ? $thread->branchUser
            : $thread->providerUser;
    };

    $threadTitle = function ($thread) use ($otherParticipant) {
        if (($thread->conversation_type ?? 'provider_admin') === 'provider_admin') {
            return 'JasaKu Admin';
        }

        return $otherParticipant($thread)?->name ?: 'Provider';
    };

    $threadSubtitle = function ($thread) use ($authUser, $otherParticipant) {
        if (($thread->conversation_type ?? 'provider_admin') === 'provider_admin') {
            $requester = $thread->providerUser ?: $authUser;
            $branchName = $requester?->providerBranch?->branch_name;

            return $branchName ? "Support admin | {$branchName}" : 'Support admin';
        }

        $other = $otherParticipant($thread);

        return $other?->providerBranch?->branch_name ?: 'Provider internal';
    };

    $threadIsAdmin = fn ($thread) => ($thread->conversation_type ?? 'provider_admin') === 'provider_admin';
    $threadBadge = fn ($thread) => $threadIsAdmin($thread) ? 'Admin' : 'Internal';
    $threadInitial = fn ($thread) => strtoupper(substr($threadTitle($thread), 0, 1) ?: 'C');
    $activeIsAdmin = $activeThread && $threadIsAdmin($activeThread);
    $isRoomOpen = $activeThread && $activeThreadCanChat;
    $activeChatTotal = $threads->count();
    $supportSummaryValue = match ($adminTicketStatus) {
        'pending' => 'Pending',
        'approved' => 'Active',
        'rejected' => 'Rejected',
        'closed' => 'Ended',
        default => '-',
    };
@endphp

<section class="support-chat-page support-chat-admin support-chat-modern {{ $isRoomOpen ? 'has-active-room' : 'is-chat-list' }}" data-support-chat>
    <div class="support-chat-route">
        <div class="support-chat-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
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
                <form method="GET" action="{{ provider_route('provider.chat.index') }}" class="support-chat-search compact">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                        <path d="M21 21L16.7 16.7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search" data-chat-search>
                </form>

                <div class="support-message-tabs">
                    <a href="{{ provider_route('provider.chat.index', array_filter(['list' => 1, 'search' => $search ?: null])) }}" class="active">
                        <span>All</span>
                        <b>{{ number_format($activeChatTotal) }}</b>
                    </a>
                    <a href="{{ provider_route('provider.tickets.index') }}">
                        <span>Support Help</span>
                        <b>{{ $supportSummaryValue }}</b>
                    </a>
                </div>
            </div>

            <div class="support-thread-list" data-thread-list>
                @forelse ($threads as $thread)
                    @php
                        $unreadCount = (int) ($thread->unread_count ?? 0);
                        $isActive = $activeThread && (int) $activeThread->id === (int) $thread->id;
                        $isAdminThread = $threadIsAdmin($thread);
                        $lastMessage = $thread->lastMessage;
                        $previewBody = $lastMessage ? trim((string) $lastMessage->body) : '';
                        $preview = $lastMessage
                            ? ($previewBody !== ''
                                ? \Illuminate\Support\Str::limit($previewBody, 48)
                                : ($lastMessage->attachment_path ? 'Sent an image' : ''))
                            : \Illuminate\Support\Str::limit($thread->ticket_subject ?: 'Start a conversation', 48);
                    @endphp

                    <a
                        href="{{ provider_route('provider.chat.index', $threadQuery($thread)) }}"
                        class="support-thread-item {{ $isActive ? 'active' : '' }}"
                        data-thread-id="{{ $thread->id }}"
                        data-chat-row
                        data-chat-label="{{ \Illuminate\Support\Str::lower($threadTitle($thread) . ' ' . $threadSubtitle($thread)) }}"
                    >
                        <span class="support-avatar">{{ $threadInitial($thread) }}</span>

                        <span class="support-thread-copy">
                            <span class="support-identity-line">
                                <strong>{{ $threadTitle($thread) }}</strong>
                                @if ($isAdminThread)
                                    <span class="support-admin-chip">Admin</span>
                                    <span class="support-verified-check" title="Official admin account" aria-label="Official admin account">
                                        <svg viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="9" fill="currentColor"/>
                                            <path d="M6 10.2l2.5 2.5L14.2 7" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                @endif
                            </span>
                            <small class="support-thread-subline">
                                <span class="support-thread-subtitle">{{ $threadSubtitle($thread) }}</span>
                                <span class="support-ticket-mini {{ $isAdminThread ? ($thread->ticket_status ?? 'approved') : 'approved' }}">
                                    {{ $isAdminThread ? ($ticketLabels[$thread->ticket_status ?? 'approved'] ?? 'Support') : $threadBadge($thread) }}
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
                        <span>Select an internal contact or open Support Help.</span>
                    </div>
                @endforelse
            </div>

            <div class="support-contact-section">
                <span>Internal Contacts</span>

                @forelse ($internalContacts as $contact)
                    <form
                        method="POST"
                        action="{{ provider_route('provider.chat.internal.start') }}"
                        class="support-contact-form"
                        data-chat-row
                        data-chat-label="{{ \Illuminate\Support\Str::lower($contact->name . ' ' . ($contact->providerBranch?->branch_name ?? 'main provider')) }}"
                    >
                        @csrf
                        <input type="hidden" name="contact_user_id" value="{{ $contact->id }}">

                        <button type="submit" class="support-thread-item support-contact-button">
                            <span class="support-avatar">{{ strtoupper(substr($contact->name ?? 'P', 0, 1)) }}</span>

                            <span class="support-thread-copy">
                                <strong>{{ $contact->name }}</strong>
                                <small>{{ $contact->providerBranch?->branch_name ?? 'Main Provider' }}</small>
                                <em class="support-thread-last">Open conversation</em>
                            </span>
                        </button>
                    </form>
                @empty
                    <div class="support-chat-empty compact">
                        <strong>No contacts</strong>
                        <span>Internal contacts will appear after branch accounts are created.</span>
                    </div>
                @endforelse
            </div>
        </aside>

        <div class="support-chat-panel support-chat-conversation">
            @if ($activeThread)
                @php
                    $activeRoomStatus = $activeThread->ticket_status ?? 'approved';
                    $activeRoomTitle = $threadTitle($activeThread);
                    $activeRoomInitial = $threadInitial($activeThread);
                    $activeRoomSubtitle = $threadSubtitle($activeThread);

                    if (! $activeIsAdmin) {
                        $activeOther = $otherParticipant($activeThread);
                        $activeBranchName = $activeOther?->providerBranch?->branch_name ?: 'Provider internal';
                        $activeRoomSubtitle = $activeBranchName . ' | ' . ($activeOther?->email ?? '-');
                    }
                @endphp

                <div class="support-chat-panel-head modern">
                    <a
                        href="{{ provider_route('provider.chat.index', ['list' => 1]) }}"
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
                            <span>{{ $activeRoomInitial }}</span>
                        </span>

                        <div>
                            <span class="support-room-title-row">
                                <strong>{{ $activeRoomTitle }}</strong>
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
                                {{ $activeRoomSubtitle }}
                            </span>
                        </div>
                    </div>

                    <div class="support-chat-tags">
                        <span class="support-ticket-badge {{ $activeRoomStatus }}" data-thread-status="{{ $activeThread->id }}">
                            {{ $activeIsAdmin ? ($ticketLabels[$activeRoomStatus] ?? ucfirst($activeRoomStatus)) : 'Internal' }}
                        </span>
                    </div>

                    <div class="support-chat-actions">
                        @if ($activeIsAdmin)
                            <a href="{{ provider_route('provider.tickets.index') }}" title="Support Help" aria-label="Open support help">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M4 5h16v14H4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>

                @if (! $activeThreadCanChat)
                    <div class="support-ticket-review">
                        <div class="support-ticket-card">
                            <span class="support-ticket-kicker">Chat Ticket</span>
                            <h2>{{ $activeThread->ticket_subject ?: 'Provider chat request' }}</h2>
                            <p>Ticket submission and approval are managed from the Support Help menu.</p>

                            <dl class="support-ticket-meta">
                                <div>
                                    <dt>Submitted</dt>
                                    <dd>{{ $activeThread->ticket_requested_at?->format('d M Y H:i') ?? '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Status</dt>
                                    <dd>{{ $ticketLabels[$activeRoomStatus] ?? ucfirst($activeRoomStatus) }}</dd>
                                </div>
                            </dl>

                            @if (in_array($activeRoomStatus, ['rejected', 'closed'], true) && $activeThread->ticket_rejection_reason)
                                <div class="support-ticket-note">
                                    {{ $activeThread->ticket_rejection_reason }}
                                </div>
                            @endif

                            <div class="support-ticket-actions">
                                <a href="{{ provider_route('provider.tickets.index') }}" class="support-ticket-primary support-ticket-link">
                                    Open Support Help
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
                            <span>Write the first message to start the conversation.</span>
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

                    <button type="submit" title="Send message">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M22 2L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Send</span>
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
                    <strong>Select a chat</strong>
                    <span>Select an internal contact on the left, or open Support Help for admin assistance.</span>
                </div>
            @endif
        </div>
    </div>

    <script type="application/json" id="supportChatConfig">
        {!! json_encode($chatConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>
</section>

<script src="{{ asset('js/support-chat.js') }}" defer></script>
@endsection


