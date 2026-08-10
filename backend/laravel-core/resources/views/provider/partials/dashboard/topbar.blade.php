@php
    use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
    use App\Modules\Provider\Application\Support\ProviderMenuAccess;

    $authUser = auth()->user();

    $providerName = $authUser->name ?? 'Provider User';
    $providerEmail = $authUser->email ?? 'provider@mail.com';

    $nameParts = collect(explode(' ', trim($providerName)))->filter()->values();

    $initials = $nameParts->count() >= 2
        ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
        : strtoupper(substr($providerName, 0, 1));

    $profile = $authUser
        ? ProviderProfile::where('user_id', ProviderMenuAccess::providerOwnerId($authUser))->first()
        : null;
    $canOpenProfile = ProviderMenuAccess::userCanAccess($authUser, 'profile');
    $canManageAccess = ProviderMenuAccess::userCanAccess($authUser, 'roles_permissions');
    $documentStatus = optional($profile)->document_status ?? 'pending';
    $isDocumentVerified = $documentStatus === 'verified';
    $documentGateUrl = provider_route('provider.verification');
    $dashboardUrl = $isDocumentVerified ? provider_route('provider.dashboard') : $documentGateUrl;


    $profileImage = optional($profile)->image;
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

    $notificationConnection = config('broadcasting.connections.reverb', []);
    $notificationOptions = $notificationConnection['public_options'] ?? ($notificationConnection['options'] ?? []);
    $notificationScheme = (string) ($notificationOptions['scheme'] ?? 'http');
    $notificationHost = (string) ($notificationOptions['host'] ?? request()->getHost());
    $notificationConfig = [
        'userId' => $authUser ? (int) $authUser->id : null,
        'csrfToken' => csrf_token(),
        'indexUrl' => provider_route('provider.notifications.index'),
        'readAllUrl' => provider_route('provider.notifications.read-all'),
        'readUrlTemplate' => provider_route('provider.notifications.read', ['notification' => '__ID__']),
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
            <nav class="topbar-pills" aria-label="Main navigation" style="position: relative;">
                <div class="sliding-indicator" id="topbarIndicator"></div>
                @foreach($topbarMainMenus ?? [] as $mainMenu)
                    @php
                        $firstItem = $mainMenu['items'][0] ?? null;
                        $isLocked = !empty($firstItem['locked_by_documents']);
                        $firstItemUrl = $isLocked ? '#' : ($firstItem['url'] ?? '#');
                    @endphp
                    <a href="{{ $firstItemUrl }}" 
                       class="pill-btn {{ !empty($mainMenu['is_active']) ? 'active' : '' }} {{ $isLocked ? 'locked' : '' }}" 
                       data-menu-url="{{ $firstItemUrl }}"
                       data-menu-group="{{ $mainMenu['id'] ?? '' }}"
                       @if($isLocked) onclick="event.preventDefault(); showLockedAlert('{{ $documentGateUrl }}');" @endif>
                        {{ $mainMenu['title'] ?? '' }}
                    </a>
                @endforeach
            </nav>
        @endif
    </div>

    <div class="topbar-right">
        <div class="topbar-actions-pill">
            <div class="topbar-search-wrapper" id="topbarSearchWrapper">
                <button class="topbar-action-btn search-trigger-btn" type="button" title="Search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
                <input type="text" class="topbar-search-input" id="topbarSearchInput" placeholder="Search...">
            </div>

            @if ($isDocumentVerified)
                <button
                    class="topbar-action-btn"
                    type="button"
                    data-provider-context-guide
                    title="Panduan menu ini"
                    aria-label="Buka panduan untuk menu ini"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.7 9a2.5 2.5 0 1 1 3.72 2.18c-.88.5-1.42.94-1.42 1.82"/><path d="M12 17h.01"/></svg>
                </button>
            @endif

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
                        <img src="{{ $profileImageUrl }}" alt="{{ $providerName }}">
                    @else
                        {{ $initials }}
                    @endif
                </span>
                <span class="profile-name">
                    <strong>{{ $providerName }}</strong>
                    <small>Provider</small>
                </span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </button>

            <div class="profile-menu admin-profile-menu" id="profileMenu">
                <div class="profile-menu-head admin-profile-head">
                    <div class="profile-menu-avatar admin-profile-head-avatar">
                        @if ($profileImageUrl)
                            <img src="{{ $profileImageUrl }}" alt="{{ $providerName }}">
                        @else
                            {{ $initials }}
                        @endif
                    </div>
                    <div>
                        <strong>{{ $providerName }}</strong>
                        <span>{{ $providerEmail }}</span>
                        <small class="profile-status-pill {{ $documentStatus }}">
                            {{ ucfirst($documentStatus) }}
                        </small>
                    </div>
                </div>

                @if ($canOpenProfile)
                    <a href="{{ provider_route('provider.profile') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/><path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/></svg>
                        My Profile
                    </a>
                @endif

                @if ($canManageAccess && $isDocumentVerified)
                    <a href="{{ provider_route('provider.roles-permissions.index') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                        Roles & Access
                    </a>
                @endif

                @if ($isDocumentVerified)
                <a href="#" data-provider-tutorial-trigger aria-haspopup="dialog">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                    Pusat panduan
                </a>
                @else
                <a href="{{ $documentGateUrl }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="M12 8v4M12 16h.01"/></svg>
                    Verifikasi mitra
                </a>
                @endif

                <form action="{{ provider_route('provider.logout') }}" method="POST">
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
        const contentArea = document.querySelector('.provider-content-area');
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
