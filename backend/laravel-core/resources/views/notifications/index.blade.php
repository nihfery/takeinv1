@extends($layout)

@section('title', 'Notification - JasaKu')
@section('page_title', 'Notification')
@section('page_subtitle', 'All non-chat operational alerts are stored here.')

@section('content')
@php
    use Illuminate\Support\Str;

    $isProvider = ($context ?? 'admin') === 'provider';
    $status = $status ?? 'all';
    $summary = $summary ?? ['total' => 0, 'unread' => 0, 'read' => 0];
    $routes = $routes ?? [
        'index' => url('/notifications'),
        'read_all' => url('/notifications/read'),
        'read' => url('/notifications/__ID__/read'),
    ];

    $statusTabs = [
        'all' => ['label' => 'All', 'count' => $summary['total'] ?? 0],
        'unread' => ['label' => 'Unread', 'count' => $summary['unread'] ?? 0],
        'read' => ['label' => 'Read', 'count' => $summary['read'] ?? 0],
    ];

    $typeLabel = function (?string $type) {
        return match (true) {
            Str::startsWith((string) $type, 'booking.') => 'Booking',
            Str::startsWith((string) $type, 'provider.document') => 'Document',
            Str::startsWith((string) $type, 'provider.status') => 'Provider',
            Str::startsWith((string) $type, 'ticket.') => 'Ticket',
            default => 'Notification',
        };
    };

    $typeClass = function (?string $type) {
        return match (true) {
            Str::startsWith((string) $type, 'booking.') => 'booking',
            Str::startsWith((string) $type, 'provider.document') => 'document',
            Str::startsWith((string) $type, 'provider.status') => 'provider',
            Str::startsWith((string) $type, 'ticket.') => 'ticket',
            default => 'general',
        };
    };

    $tabUrl = function (string $nextStatus) use ($routes) {
        if ($nextStatus === 'all') {
            return $routes['index'];
        }

        return $routes['index'] . '?' . http_build_query(['status' => $nextStatus]);
    };

    $readUrl = fn ($notification) => str_replace('__ID__', (string) $notification->id, $routes['read']);
@endphp

<section class="notification-center-page {{ $isProvider ? 'is-provider' : 'is-admin' }}">
    <div class="notification-center-head">
        <div class="notification-center-breadcrumb">
            <a href="{{ $isProvider ? provider_route('provider.dashboard') : route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>Notification</strong>
        </div>
    </div>

    @if (session('success'))
        <div class="notification-center-alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="notification-center-summary">
        <div>
            <span>Total</span>
            <strong>{{ number_format((int) ($summary['total'] ?? 0)) }}</strong>
        </div>
        <div>
            <span>Unread</span>
            <strong>{{ number_format((int) ($summary['unread'] ?? 0)) }}</strong>
        </div>
        <div>
            <span>Read</span>
            <strong>{{ number_format((int) ($summary['read'] ?? 0)) }}</strong>
        </div>
    </div>

    <div class="notification-center-card">
        <div class="notification-center-toolbar">
            <nav class="notification-center-tabs" aria-label="Notification filter">
                @foreach ($statusTabs as $key => $tab)
                    <a href="{{ $tabUrl($key) }}" class="{{ $status === $key ? 'active' : '' }}">
                        <span>{{ $tab['label'] }}</span>
                        <b>{{ number_format((int) $tab['count']) }}</b>
                    </a>
                @endforeach
            </nav>

            <form method="POST" action="{{ $routes['read_all'] }}" class="notification-center-read-all">
                @csrf
                <button type="submit" class="notification-center-action" {{ (int) ($summary['unread'] ?? 0) === 0 ? 'disabled' : '' }}>
                    Mark all as read
                </button>
            </form>
        </div>

        <div class="notification-center-list">
            @forelse ($notifications as $notification)
                <article
                    class="notification-center-item {{ $notification->read_at ? '' : 'is-unread' }} {{ $notification->url ? 'is-clickable' : '' }}"
                    data-notification-card
                    @if ($notification->url)
                        data-href="{{ $notification->url }}"
                        @unless ($notification->read_at)
                            data-read-url="{{ $readUrl($notification) }}"
                        @endunless
                        role="link"
                        tabindex="0"
                    @endif>
                    <span class="notification-center-dot" aria-hidden="true"></span>

                    <div class="notification-center-copy">
                        <div class="notification-center-item-head">
                            <span class="notification-center-type {{ $typeClass($notification->type) }}">
                                {{ $typeLabel($notification->type) }}
                            </span>
                            <time>{{ $notification->created_at?->diffForHumans() ?? '-' }}</time>
                        </div>

                        <h2>{{ $notification->title }}</h2>
                        <p>{{ $notification->body ?: 'Klik untuk melihat detail notifikasi.' }}</p>

                        <div class="notification-center-meta">
                            <span>{{ $notification->read_at ? 'Read' : 'Unread' }}</span>
                            <span>{{ $notification->created_at?->format('d M Y, H:i') ?? '-' }}</span>
                        </div>
                    </div>

                    @unless ($notification->read_at)
                        <div class="notification-center-actions">
                            <form method="POST" action="{{ $readUrl($notification) }}">
                                @csrf
                                <button type="submit">Mark as read</button>
                            </form>
                        </div>
                    @endunless
                </article>
            @empty
                <div class="notification-center-empty">
                    <strong>No notifications yet.</strong>
                    <span>Notifikasi non-chat akan muncul di sini saat ada aktivitas baru.</span>
                </div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div class="notification-center-pagination">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const csrfToken = @json(csrf_token());

    const bindNotificationCards = (root = document) => {
        root.querySelectorAll('[data-notification-card][data-href]').forEach((card) => {
            if (card.dataset.bound === '1') {
                return;
            }

            card.dataset.bound = '1';

            const openCard = async () => {
                const href = card.dataset.href;

                if (!href) {
                    return;
                }

                if (card.dataset.readUrl) {
                    try {
                        await fetch(card.dataset.readUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                        });
                    } catch (error) {
                        // Opening the destination matters more than the read-state update.
                    }
                }

                window.location.href = href;
            };

            card.addEventListener('click', (event) => {
                if (event.target.closest('a, button, form, input, select, textarea, label')) {
                    return;
                }

                openCard();
            });

            card.addEventListener('keydown', (event) => {
                if (!['Enter', ' '].includes(event.key)) {
                    return;
                }

                event.preventDefault();
                openCard();
            });
        });
    };

    const bindNotificationTabs = () => {
        const center = document.querySelector('.notification-center-page');

        if (!center) {
            return;
        }

        center.addEventListener('click', async (event) => {
            const tab = event.target.closest('.notification-center-tabs a');

            if (!tab || !center.contains(tab)) {
                return;
            }

            event.preventDefault();
            const card = center.querySelector('.notification-center-card');

            if (!card) {
                window.location.href = tab.href;
                return;
            }

            card.classList.add('is-loading');

            try {
                const response = await fetch(tab.href, {
                    headers: {
                        Accept: 'text/html',
                    },
                });

                if (!response.ok) {
                    throw new Error('Cannot load notifications.');
                }

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nextCard = doc.querySelector('.notification-center-card');

                if (!nextCard) {
                    throw new Error('Notification card is missing.');
                }

                card.replaceWith(nextCard);
                window.history.pushState({ notificationStatus: true }, '', response.url);
                bindNotificationCards(nextCard);
            } catch (error) {
                window.location.href = tab.href;
            } finally {
                const activeCard = center.querySelector('.notification-center-card');

                if (activeCard) {
                    activeCard.classList.remove('is-loading');
                }
            }
        });

        window.addEventListener('popstate', () => {
            window.location.reload();
        });
    };

    bindNotificationCards();
    bindNotificationTabs();
})();
</script>
@endpush
