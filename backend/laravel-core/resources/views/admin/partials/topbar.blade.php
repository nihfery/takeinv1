@php
    $authUser = auth()->user();
    $adminName = $authUser->name ?? 'Admin User';
    $adminEmail = $authUser->email ?? 'admin@mail.com';

    $nameParts = collect(explode(' ', trim($adminName)))->filter()->values();
    $initials = $nameParts->count() >= 2
        ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
        : strtoupper(substr($adminName, 0, 1));

    $adminProfile = $authUser?->adminProfile;
    $profileImage = $adminProfile->avatar ?? ($authUser->image ?? null);
    $profileImageUrl = null;

    if (!empty($profileImage)) {
        $profileImage = ltrim($profileImage, '/');
        if (\Illuminate\Support\Str::startsWith($profileImage, ['http://', 'https://'])) {
            $profileImageUrl = $profileImage;
        } elseif (\Illuminate\Support\Str::startsWith($profileImage, 'storage/')) {
            $profileImageUrl = asset($profileImage);
        } else {
            $profileImageUrl = asset('storage/' . $profileImage);
        }
    }

    $chatUnreadCount = $authUser ? \App\Modules\Chat\Application\Support\ChatUnreadCounter::forUser($authUser) : 0;
    $chatUnreadLabel = $chatUnreadCount > 99 ? '99+' : (string) $chatUnreadCount;

    $notificationConnection = config('broadcasting.connections.reverb', []);
    $notificationOptions = $notificationConnection['public_options'] ?? ($notificationConnection['options'] ?? []);
    $notificationScheme = (string) ($notificationOptions['scheme'] ?? 'http');
    $notificationHost = (string) ($notificationOptions['host'] ?? request()->getHost());
    
    $notificationConfig = [
        'userId' => $authUser ? (int) $authUser->id : null,
        'csrfToken' => csrf_token(),
        'indexUrl' => \Illuminate\Support\Facades\Route::has('admin.notifications.index') ? route('admin.notifications.index') : '#',
        'readAllUrl' => \Illuminate\Support\Facades\Route::has('admin.notifications.read-all') ? route('admin.notifications.read-all') : '#',
        'readUrlTemplate' => url('/admin/notifications/__ID__/read'),
        'authEndpoint' => url('/broadcasting/auth'),
        'broadcast' => [
            'key' => (string) ($notificationConnection['key'] ?? ''),
            'host' => $notificationHost !== '' ? $notificationHost : request()->getHost(),
            'port' => (int) ($notificationOptions['port'] ?? 8080),
            'scheme' => $notificationScheme,
        ],
    ];
@endphp

<header class="floating-topbar">
    <div class="topbar-left">
    </div>

    <div class="topbar-center">
        @if(count($topbarMainMenus ?? []) > 0)
            <nav class="topbar-pills">
                @foreach($topbarMainMenus ?? [] as $mainMenu)
                    @php
                        $firstItemUrl = $mainMenu['items'][0]['url'] ?? '#';
                    @endphp
                    <a href="{{ $firstItemUrl }}" class="pill-btn {{ !empty($mainMenu['is_active']) ? 'active' : '' }}">
                        {{ $mainMenu['title'] ?? '' }}
                    </a>
                @endforeach
            </nav>
        @endif
    </div>

    <div class="topbar-right">
        <div class="topbar-actions-pill">
            <div class="topbar-search-wrapper" id="topbarSearchWrapper">
                <button class="topbar-action-btn search-btn" id="topbarSearchBtn" type="button" aria-label="Search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
                <input type="text" class="topbar-search-input" id="topbarSearchInput" placeholder="Search...">
            </div>

            <div class="notification-shell" data-notification-root>
                <button class="topbar-action-btn notification-btn" type="button" data-notification-toggle aria-expanded="false" title="Notifications">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span class="notification-badge notification-dot is-hidden" data-notification-count>0</span>
                </button>

                <div class="notification-popover" data-notification-popover>
                    <div class="notification-popover-head">
                        <div>
                            <strong>Notifications</strong>
                            <span data-notification-subtitle>Loading...</span>
                        </div>
                        <button type="button" data-notification-read-all>Mark all read</button>
                    </div>
                    <div class="notification-list" data-notification-list></div>
                </div>

                <script type="application/json" data-notification-config>
                    {!! json_encode($notificationConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                </script>
            </div>
        </div>

        <div class="profile-dropdown admin-profile-dropdown" id="profileDropdown">
            <button class="profile-pill-btn" id="profileToggle" type="button">
                <span class="profile-avatar">
                    @if ($profileImageUrl)
                        <img src="{{ $profileImageUrl }}" alt="{{ $adminName }}">
                    @else
                        {{ $initials }}
                    @endif
                </span>
                <span class="profile-name">
                    <strong>{{ $adminName }}</strong>
                    <small>Administrator</small>
                </span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </button>

            <div class="profile-menu admin-profile-menu" id="profileMenu">
                <div class="profile-menu-head admin-profile-head">
                    <div class="profile-menu-avatar admin-profile-head-avatar">
                        @if ($profileImageUrl)
                            <img src="{{ $profileImageUrl }}" alt="{{ $adminName }}">
                        @else
                            {{ $initials }}
                        @endif
                    </div>
                    <div>
                        <strong>{{ $adminName }}</strong>
                        <span>{{ $adminEmail }}</span>
                    </div>
                </div>
                
                <a href="{{ \Illuminate\Support\Facades\Route::has('admin.profile') ? route('admin.profile') : '#' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    My Profile
                </a>
                
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Scroll functionality
        const contentArea = document.querySelector('.admin-main-content');
        const topbar = document.querySelector('.floating-topbar');
        
        if(contentArea && topbar) {
            contentArea.addEventListener('scroll', () => {
                if (contentArea.scrollTop > 10) {
                    topbar.classList.add('is-scrolled');
                } else {
                    topbar.classList.remove('is-scrolled');
                }
            });
        }

        // Search expand functionality
        const searchWrapper = document.getElementById('topbarSearchWrapper');
        const searchInput = document.getElementById('topbarSearchInput');

        if(searchWrapper && searchInput) {
            searchWrapper.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!searchWrapper.classList.contains('is-expanded')) {
                    searchWrapper.classList.add('is-expanded');
                    setTimeout(() => searchInput.focus(), 100);
                } else {
                    searchInput.focus();
                }
            });

            document.addEventListener('click', (e) => {
                if (!searchWrapper.contains(e.target) && searchWrapper.classList.contains('is-expanded')) {
                    searchWrapper.classList.remove('is-expanded');
                    searchInput.value = '';
                }
            });
            
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    searchWrapper.classList.remove('is-expanded');
                    searchInput.value = '';
                }
            });
        }
    });
</script>
