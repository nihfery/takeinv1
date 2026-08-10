@extends('provider.layouts.dashboard')

@section('title', 'Support Help - JasaKu')
@section('page_title', 'Support Help')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/support-chat.css') }}">
@endpush

@section('content')
@php
    $authUser = request()->user();
    $thread = $adminThread ?? $thread;
    $provider = $thread->provider;
    $requester = $thread->providerUser ?: $authUser;
    $ticketStatus = $thread->ticket_status ?? 'none';
    $ticketLabels = [
        'none' => 'Not Submitted',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'closed' => 'Chat Ended',
    ];
    $ticketStatusLabel = $ticketLabels[$ticketStatus] ?? ucfirst((string) $ticketStatus);
    $ticketStatusCopy = match ($ticketStatus) {
        'approved' => 'The support chat room is active. Use chat for direct follow-up with the admin team.',
        'pending' => 'Your ticket is in the admin review queue and waiting for approval.',
        'rejected' => 'The previous ticket was rejected. Update the request details before submitting again.',
        'closed' => 'The last support chat session has ended. Submit a new ticket if you still need admin help.',
        default => 'No active ticket yet. Submit a ticket if the FAQ does not answer your question.',
    };
    $ticketRequestedAt = $thread->ticket_requested_at?->format('d M Y H:i') ?? '-';
    $ticketReviewedAt = $thread->ticket_reviewed_at?->format('d M Y H:i') ?? '-';
    $ticketReviewer = $thread->ticketReviewer?->name ?? '-';
    $providerProfile = $provider?->providerProfile;
    $faqs = [
        [
            'topic' => 'Access',
            'question' => 'How do I open a chat with admin?',
            'answer' => 'Read the FAQ first. If you still need direct assistance, submit a support chat ticket from the bottom of this page. Admin will review the ticket before opening the chat room.',
        ],
        [
            'topic' => 'Ticket',
            'question' => 'Why does admin chat require a ticket?',
            'answer' => 'Tickets help admin understand the issue context faster, keep the support queue organized, and store the request history for follow-up.',
        ],
        [
            'topic' => 'Review',
            'question' => 'How long does ticket review take?',
            'answer' => 'Your ticket will enter the admin queue. Include a clear title and detailed context so the review process can move faster.',
        ],
        [
            'topic' => 'Internal',
            'question' => 'Does internal provider chat require a ticket?',
            'answer' => 'No. Internal chat between provider accounts can be used directly from the Chat menu.',
        ],
        [
            'topic' => 'Details',
            'question' => 'What details should I include in a ticket?',
            'answer' => 'Describe the main issue, affected menu, sample data if available, and actions already tried so admin can follow up quickly.',
        ],
    ];
    $supportSteps = [
        [
            'label' => 'FAQ',
            'title' => 'Check quick solutions',
            'body' => 'Review common answers before creating a new support ticket.',
        ],
        [
            'label' => 'Ticket',
            'title' => 'Submit context',
            'body' => 'Fill in the title and request details so admin understands the issue from the start.',
        ],
        [
            'label' => 'Review',
            'title' => 'Admin reviews',
            'body' => 'Admin approves, rejects, or closes the ticket based on the request context.',
        ],
        [
            'label' => 'Chat',
            'title' => 'Room opens',
            'body' => 'If approved, the support chat room becomes active and can be used for follow-up.',
        ],
    ];
@endphp

<section class="support-chat-page support-chat-provider support-help-page provider-support-help-page">
    <div class="support-chat-route">
        <div class="support-chat-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Support Help</strong>
        </div>
    </div>

    <header class="support-help-hero provider-support-help-hero">
        <div class="support-help-copy">
            <span class="support-help-kicker">Support Center</span>
            <h1>Provider Help Center</h1>
            <p>Manage admin support tickets, check review status, and open support chat from one organized page.</p>

            <div class="provider-support-hero-actions">
                @if ($ticketStatus === 'approved')
                    <a href="{{ provider_route('provider.chat.index', ['thread' => $thread->id]) }}" class="support-ticket-primary support-ticket-link">
                        Open Support Chat
                    </a>
                @else
                    <a href="#support-ticket-panel" class="support-ticket-primary support-ticket-link">
                        {{ $ticketStatus === 'pending' ? 'View Ticket Status' : 'Submit Ticket' }}
                    </a>
                @endif

                <a href="{{ provider_route('provider.chat.index') }}" class="support-ticket-secondary support-ticket-link">
                    Internal Chat
                </a>
            </div>
        </div>

        <div class="support-help-status provider-support-status-card">
            <span>Ticket Status</span>
            <strong>{{ $ticketStatusLabel }}</strong>
            <b class="support-ticket-badge {{ $ticketStatus }}">
                {{ $ticketStatusLabel }}
            </b>

            <p>{{ $ticketStatusCopy }}</p>
        </div>
    </header>

    @if (session('success'))
        <div class="support-ticket-alert success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="support-ticket-alert error">{{ $errors->first() }}</div>
    @endif

    <div class="support-help-grid provider-support-help-grid">
        <main class="support-help-main">
            <section class="support-help-section support-help-ticket provider-ticket-panel" id="support-ticket-panel" aria-labelledby="support-ticket-title">
                <div class="support-help-section-head">
                    <div>
                        <span>Ticket Support</span>
                        <h2 id="support-ticket-title">
                            @if ($ticketStatus === 'approved')
                                Admin chat is open
                            @elseif ($ticketStatus === 'pending')
                                Request is under review
                            @elseif ($ticketStatus === 'closed')
                                Support chat has ended
                            @elseif ($ticketStatus === 'rejected')
                                Resubmit support ticket
                            @else
                                Submit a support chat ticket
                            @endif
                        </h2>
                    </div>

                    <b class="support-ticket-badge {{ $ticketStatus }}">{{ $ticketStatusLabel }}</b>
                </div>

                <div class="provider-ticket-status-copy">
                    <p>{{ $ticketStatusCopy }}</p>
                </div>

                @if ($ticketStatus === 'approved')
                    <div class="provider-ticket-current">
                        <span>Ticket Subject</span>
                        <strong>{{ $thread->ticket_subject ?: 'Admin chat is open' }}</strong>
                        <p>{{ $thread->ticket_body ?: 'Admin has approved support chat access.' }}</p>
                    </div>

                    <dl class="support-ticket-meta">
                        <div>
                            <dt>Submitted</dt>
                            <dd>{{ $ticketRequestedAt }}</dd>
                        </div>

                        <div>
                            <dt>Approved</dt>
                            <dd>{{ $ticketReviewedAt }}</dd>
                        </div>

                        <div>
                            <dt>Reviewer</dt>
                            <dd>{{ $ticketReviewer }}</dd>
                        </div>
                    </dl>

                    <div class="support-ticket-actions">
                        <a href="{{ provider_route('provider.chat.index', ['thread' => $thread->id]) }}" class="support-ticket-primary support-ticket-link">
                            Open Support Chat
                        </a>
                    </div>
                @elseif ($ticketStatus === 'pending')
                    <div class="provider-ticket-current">
                        <span>Ticket Subject</span>
                        <strong>{{ $thread->ticket_subject ?: 'Support chat request is under review' }}</strong>
                        <p>{{ $thread->ticket_body ?: 'The ticket is being reviewed by admin. You can revisit this page to check its status.' }}</p>
                    </div>

                    <dl class="support-ticket-meta">
                        <div>
                            <dt>Submitted</dt>
                            <dd>{{ $ticketRequestedAt }}</dd>
                        </div>

                        <div>
                            <dt>Status</dt>
                            <dd>{{ $ticketStatusLabel }}</dd>
                        </div>

                        <div>
                            <dt>Reviewer</dt>
                            <dd>Waiting for admin</dd>
                        </div>
                    </dl>
                @else
                    @if (in_array($ticketStatus, ['rejected', 'closed'], true) && $thread->ticket_rejection_reason)
                        <div class="support-ticket-note">
                            {{ $thread->ticket_rejection_reason }}
                        </div>
                    @endif

                    @if ($isLocked ?? false)
                        <div class="bg-gray-100 border border-gray-200 rounded-md p-6 text-center">
                            <svg class="w-10 h-10 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 mb-1">Support Chat Terkunci</h3>
                            <p class="text-gray-500 mb-4">Layanan Support Chat dengan Admin hanya tersedia untuk akun berlangganan (Subscription) aktif. Tingkatkan paket Anda untuk mendapatkan bantuan langsung.</p>
                            <a href="{{ provider_route('provider.subscription.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Berlangganan Sekarang
                            </a>
                        </div>
                    @else
                        <form method="POST" action="{{ provider_route('provider.tickets.store') }}" class="support-ticket-form">
                            @csrf

                            <label>
                                <span>Ticket Title</span>
                                <input type="text" name="subject" value="{{ old('subject', $thread->ticket_subject) }}" maxlength="160" placeholder="Example: Need help verifying a service" required>
                            </label>

                            <label>
                                <span>Request Details</span>
                                <textarea name="body" rows="6" maxlength="2000" placeholder="Describe the issue, affected menu, sample data, and actions you have already tried." required>{{ old('body', $thread->ticket_body) }}</textarea>
                            </label>

                            <div class="provider-ticket-form-footer">
                                <small>Maximum 2,000 characters. A clear ticket helps admin open chat faster.</small>
                                <button type="submit" class="support-ticket-primary">
                                    Submit to Admin
                                </button>
                            </div>
                        </form>
                    @endif
                @endif
            </section>

            <section class="support-help-section support-faq-card provider-faq-panel" aria-labelledby="support-faq-title">
                <div class="support-help-section-head">
                    <div>
                        <span>FAQ Umum</span>
                        <h2 id="support-faq-title">Quick Help for Providers</h2>
                    </div>
                </div>

                <div class="support-faq-list provider-faq-list">
                    @foreach ($faqs as $index => $faq)
                        <details {{ $index === 0 ? 'open' : '' }}>
                            <summary>
                                <span class="provider-faq-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="provider-faq-question">
                                    <small>{{ $faq['topic'] }}</small>
                                    <strong>{{ $faq['question'] }}</strong>
                                </span>
                            </summary>
                            <p>{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        </main>

        <aside class="support-help-aside">
            <section class="support-help-side-card provider-support-side-card">
                <span>Ticket Status</span>
                <h2>{{ $ticketStatusLabel }}</h2>

                <div class="provider-side-status">
                    <b class="support-ticket-badge {{ $ticketStatus }}">{{ $ticketStatusLabel }}</b>
                    <p>{{ $ticketStatusCopy }}</p>
                </div>

                <div class="support-help-side-list">
                    <div>
                        <small>Submitted</small>
                        <strong>{{ $ticketRequestedAt }}</strong>
                    </div>

                    <div>
                        <small>Reviewed</small>
                        <strong>{{ $ticketReviewedAt }}</strong>
                    </div>

                    <div>
                        <small>Reviewer</small>
                        <strong>{{ $ticketReviewer }}</strong>
                    </div>
                </div>
            </section>

            <section class="support-help-side-card provider-support-side-card">
                <span>Account Details</span>
                <h2>Provider Information</h2>

                <div class="support-help-side-list">
                    <div>
                        <small>Provider</small>
                        <strong>{{ $provider->name ?? '-' }}</strong>
                    </div>

                    <div>
                        <small>Requester Account</small>
                        <strong>{{ $requester->name ?? '-' }}</strong>
                    </div>

                    <div>
                        <small>Ticket Status</small>
                        <strong>{{ $ticketStatusLabel }}</strong>
                    </div>

                    <div>
                        <small>Category</small>
                        <strong>{{ $providerProfile?->category ?? '-' }}</strong>
                    </div>
                </div>
            </section>

            <section class="support-help-side-card provider-support-flow-card">
                <span>Support Flow</span>
                <h2>Handling Process</h2>

                <ol class="provider-support-flow">
                    @foreach ($supportSteps as $step)
                        <li>
                            <b>{{ $step['label'] }}</b>
                            <div>
                                <strong>{{ $step['title'] }}</strong>
                                <small>{{ $step['body'] }}</small>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="support-help-side-card provider-support-side-card">
                <span>Internal Chat</span>
                <h2>Chat with provider accounts</h2>
                <p>Internal chat does not require tickets, and conversation history stays saved.</p>

                <div class="support-ticket-actions">
                    <a href="{{ provider_route('provider.chat.index') }}" class="support-ticket-primary support-ticket-link">
                        Open Internal Chat
                    </a>
                </div>
            </section>
        </aside>
    </div>
</section>

<script>
(() => {
    const ticketNotificationTypes = new Set([
        'ticket.approved',
        'ticket.rejected',
        'ticket.closed',
        'ticket.internal.approved',
        'ticket.internal.rejected',
        'ticket.internal.closed',
    ]);

    const refreshTicketPage = async () => {
        const currentPage = document.querySelector('.provider-support-help-page');

        if (!currentPage || !window.fetch || !window.DOMParser) {
            return;
        }

        try {
            const response = await fetch(window.location.href, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Ticket page refresh failed: ${response.status}`);
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextPage = doc.querySelector('.provider-support-help-page');

            if (nextPage) {
                currentPage.replaceWith(nextPage);
            }
        } catch (error) {
            console.error(error);
        }
    };

    window.addEventListener('app:notification-created', (event) => {
        const notification = event.detail?.notification || {};

        if (!ticketNotificationTypes.has(notification.type)) {
            return;
        }

        refreshTicketPage();
    });
})();
</script>
@endsection


