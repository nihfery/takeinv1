@php
    use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
    use App\Modules\Provider\Application\Support\ProviderMenuAccess;
    use Illuminate\Support\Str;

    $authUser = auth()->user();
    $canSeeMenu = fn (array $item) => ProviderMenuAccess::userCanAccess($authUser, $item['key'] ?? null);
    $providerProfile = $authUser
        ? ProviderProfile::query()
            ->where('user_id', ProviderMenuAccess::providerOwnerId($authUser))
            ->first()
        : null;
    $documentStatus = optional($providerProfile)->document_status ?? 'pending';
    $isDocumentVerified = $documentStatus === 'verified';
    $documentGateUrl = provider_route('provider.verification');
    $verifiedUrl = fn (string $url) => $isDocumentVerified ? $url : $documentGateUrl;

    /*
     * Route names and permission keys remain unchanged. The navigation layer
     * only changes their labels and information architecture.
     */
    $navigationItems = [
        'dashboard' => [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'description' => 'Business overview',
            'url' => $verifiedUrl(provider_route('provider.dashboard')),
            'active' => ['provider.dashboard'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        ],
        'bookings' => [
            'key' => 'bookings',
            'label' => 'Bookings',
            'description' => 'All appointments',
            'url' => $verifiedUrl(provider_route('provider.bookings.index')),
            'active' => ['provider.bookings.*', 'provider.booking.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h3M13 14h3"/></svg>',
        ],
        'calendar' => [
            'key' => 'calendar',
            'label' => 'Calendar',
            'description' => 'Monthly appointments',
            'url' => $verifiedUrl(provider_route('provider.calendar.index')),
            'active' => ['provider.calendar.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>',
        ],
        'queue' => [
            'key' => 'queue',
            'label' => 'Queue',
            'description' => 'Front-desk queue',
            'url' => $verifiedUrl(provider_route('provider.queue.index')),
            'active' => ['provider.queue.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>',
        ],
        'walk_in' => [
            'key' => 'walk_in',
            'label' => 'Walk-in',
            'description' => 'Create offline booking',
            'url' => $verifiedUrl(provider_route('provider.walk-in.index')),
            'active' => ['provider.walk-in.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><path d="M13 4h3a2 2 0 0 1 2 2v14M2 20h3M13 20h9"/><path d="M13 4.6v16.1a1 1 0 0 1-1.2 1L5 20V5.6a2 2 0 0 1 1.5-2l4-1A2 2 0 0 1 13 4.6Z"/><path d="M10 12h.01"/></svg>',
        ],
        'services' => [
            'key' => 'services',
            'label' => 'Services',
            'description' => 'Catalog and pricing',
            'url' => $verifiedUrl(provider_route('provider.services.index')),
            'active' => ['provider.services.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9M4 16h16l1.3 2.6a1 1 0 0 1-.9 1.4H3.6a1 1 0 0 1-.9-1.4L4 16Z"/></svg>',
        ],
        'staffs' => [
            'key' => 'staffs',
            'label' => 'Team',
            'description' => 'Professional directory',
            'url' => $verifiedUrl(provider_route('provider.staffs.index')),
            'active' => ['provider.staffs.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>',
        ],
        'staff_skills' => [
            'key' => 'staff_skills',
            'label' => 'Skills',
            'description' => 'Service assignments',
            'url' => $verifiedUrl(provider_route('provider.staff.skills')),
            'active' => ['provider.staff.skills', 'provider.staff.skills.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="6"/><path d="m8.5 13-1.5 9 5-3 5 3-1.5-9"/></svg>',
        ],
        'staff_schedules' => [
            'key' => 'staff_schedules',
            'label' => 'Work schedules',
            'description' => 'Working days and hours',
            'url' => $verifiedUrl(provider_route('provider.staff.schedules')),
            'active' => ['provider.staff.schedules', 'provider.staff.schedules.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        ],
        'branch' => [
            'key' => 'branch',
            'label' => 'Locations',
            'description' => 'Branches and addresses',
            'url' => $verifiedUrl(provider_route('provider.branch.index')),
            'active' => ['provider.branch.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><path d="M4 21V4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v17M2 21h20M9 21v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/></svg>',
        ],
        'customers' => [
            'key' => 'customers',
            'label' => 'Customer directory',
            'description' => 'Customer booking history',
            'url' => $verifiedUrl(provider_route('provider.customers.index')),
            'active' => ['provider.customers.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M17 11a4 4 0 0 1 5 4v2"/></svg>',
        ],
        'reviews' => [
            'key' => 'reviews',
            'label' => 'Reviews',
            'description' => 'Ratings and feedback',
            'url' => $verifiedUrl(provider_route('provider.reviews.index')),
            'active' => ['provider.reviews.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/></svg>',
        ],
        'payments' => [
            'key' => 'payments',
            'label' => 'Transactions',
            'description' => 'Payments and invoices',
            'url' => $verifiedUrl(provider_route('provider.payments.index')),
            'active' => ['provider.payments.*', 'provider.transactions.*', 'provider.transaction.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M16 15h2"/></svg>',
        ],
        'chat' => [
            'key' => 'chat',
            'label' => 'Support chat',
            'description' => 'Internal assistance',
            'url' => $verifiedUrl(provider_route('provider.chat.index')),
            'active' => ['provider.chat.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/></svg>',
        ],
        'tickets' => [
            'key' => 'tickets',
            'label' => 'Help center',
            'description' => 'Tickets and FAQ',
            'url' => $verifiedUrl(provider_route('provider.tickets.index')),
            'active' => ['provider.tickets.*'],
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.7 2.7 0 0 1 5.2 1c0 2-2.7 2.5-2.7 4M12 18h.01"/></svg>',
        ],
    ];

    $item = fn (string $key) => $navigationItems[$key];
    $navigationGroups = [
        [
            'id' => 'overview',
            'title' => 'Overview',
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
            'items' => [$item('dashboard')],
        ],
        [
            'id' => 'appointments',
            'title' => 'Appointments',
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
            'items' => [$item('bookings'), $item('calendar'), $item('queue'), $item('walk_in')],
        ],
        [
            'id' => 'business',
            'title' => 'Business',
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><path d="M4 21V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14M8 5V3h8v2M2 21h20"/></svg>',
            'items' => [$item('services'), $item('staffs'), $item('staff_skills'), $item('staff_schedules'), $item('branch')],
        ],
        [
            'id' => 'customers',
            'title' => 'Customers',
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M17 11a4 4 0 0 1 5 4v2"/></svg>',
            'items' => [$item('customers'), $item('reviews')],
        ],
        [
            'id' => 'finance',
            'title' => 'Finance',
            'icon' => '<svg viewBox="0 0 24 24" fill="none"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
            'items' => [$item('payments')],
        ],
    ];

    $navigationGroups = collect($navigationGroups)
        ->map(function (array $group) use ($canSeeMenu, $isDocumentVerified) {
            $group['items'] = collect($group['items'])
                ->filter($canSeeMenu)
                ->map(function (array $item) use ($isDocumentVerified) {
                    if (!$isDocumentVerified && ($item['key'] ?? '') !== 'profile') {
                        $item['url'] = '#';
                        $item['locked_by_documents'] = true;
                    } else {
                        $item['locked_by_documents'] = false;
                    }
                    return $item;
                })
                ->values()
                ->all();

            return $group;
        })
        ->filter(fn (array $group) => $group['items'] !== [])
        ->values()
        ->all();

    $routePatterns = fn (array $patterns) => collect($patterns)
        ->flatMap(fn (string $pattern) => [
            $pattern,
            Str::startsWith($pattern, 'provider.')
                ? 'provider-branch.' . Str::after($pattern, 'provider.')
                : $pattern,
        ])
        ->unique()
        ->values()
        ->all();
    $isItemActive = fn (array $menuItem) => request()->routeIs(...$routePatterns($menuItem['active'] ?? []));

    $activeGroup = null;
    $activeSubmenu = null;

    foreach ($navigationGroups as &$group) {
        $group['is_active'] = false;

        foreach ($group['items'] as $menuItem) {
            if ($isItemActive($menuItem)) {
                $group['is_active'] = true;
                $activeGroup = $group;
                $activeSubmenu = $menuItem;
                break 2;
            }
        }
    }
    unset($group);

    $displayGroup = $activeGroup ?: ($navigationGroups[0] ?? ['title' => 'Overview', 'items' => []]);
    $isChatActive = request()->routeIs('provider.chat.*', 'provider-branch.chat.*');
    $isTicketsActive = request()->routeIs('provider.tickets.*', 'provider-branch.tickets.*');
    $showSidebarTooltips = true;

    \Illuminate\Support\Facades\View::share('topbarMainMenus', $navigationGroups);
    \Illuminate\Support\Facades\View::share('activeSubmenuKey', $activeSubmenu['key'] ?? null);
@endphp

<aside
    class="floating-sidebar"
    id="providerSidebar"
    aria-label="{{ $displayGroup['title'] }} submenu"
    @if($showSidebarTooltips) data-sidebar-tooltips @endif
>
    <div class="sidebar-pill" aria-label="Color theme">
        <button
            type="button"
            class="sidebar-icon-btn toggle-theme active"
            data-theme-value="light"
            data-sidebar-tooltip="Light theme"
            @if(!$showSidebarTooltips) title="Light theme" @endif
            aria-label="Use light theme"
            aria-pressed="true"
        >
            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/></svg>
            @if($showSidebarTooltips)<span class="sidebar-hover-label" aria-hidden="true">Light theme</span>@endif
        </button>
        <button
            type="button"
            class="sidebar-icon-btn toggle-theme"
            data-theme-value="dark"
            data-sidebar-tooltip="Dark theme"
            @if(!$showSidebarTooltips) title="Dark theme" @endif
            aria-label="Use dark theme"
            aria-pressed="false"
        >
            <svg viewBox="0 0 24 24" fill="none"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>
            @if($showSidebarTooltips)<span class="sidebar-hover-label" aria-hidden="true">Dark theme</span>@endif
        </button>
    </div>

    <!-- Main Navigation (Middle Pill) -->
    <nav class="sidebar-main-nav sidebar-pill" aria-label="{{ $displayGroup['title'] }} pages" style="position: relative;">
        <div class="sidebar-sliding-indicator" id="sidebarIndicator"></div>
        @foreach ($displayGroup['items'] ?? $activeGroup['items'] ?? [] as $submenu)
            @php $submenuIsActive = ($submenu['key'] ?? null) === ($activeSubmenu['key'] ?? null); @endphp
            <a
                href="{{ $submenu['url'] ?? '#' }}"
                class="sidebar-icon-btn {{ $submenuIsActive ? 'active' : '' }} {{ !empty($submenu['locked_by_documents']) ? 'locked' : '' }}"
                data-menu-key="{{ $submenu['key'] ?? '' }}"
                data-sidebar-tooltip="{{ $submenu['label'] ?? '' }}"
                @if(!$showSidebarTooltips) title="{{ $submenu['label'] ?? '' }}" @endif
                aria-label="{{ $submenu['label'] ?? '' }}"
                @if ($submenuIsActive) aria-current="page" @endif
                @if(!empty($submenu['locked_by_documents'])) onclick="event.preventDefault(); showLockedAlert('{{ $documentGateUrl }}');" @endif
            >
                @if(!empty($submenu['icon']))
                    {!! $submenu['icon'] !!}
                @else
                    <span style="font-size: 16px; font-weight: 600; line-height: 1;">{{ substr($submenu['label'] ?? 'M', 0, 1) }}</span>
                @endif
                @if($showSidebarTooltips)
                    <span class="sidebar-hover-label" aria-hidden="true">{{ $submenu['label'] ?? '' }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="sidebar-bottom-nav sidebar-pill">
        @if ($canSeeMenu($navigationItems['chat']))
            <a
                href="{{ $isDocumentVerified ? $navigationItems['chat']['url'] : '#' }}"
                class="sidebar-icon-btn {{ $isChatActive ? 'active' : '' }} {{ !$isDocumentVerified ? 'locked' : '' }}"
                data-sidebar-tooltip="{{ $navigationItems['chat']['label'] }}"
                @if(!$showSidebarTooltips) title="{{ $navigationItems['chat']['label'] }}" @endif
                aria-label="Support chat"
                @if(!$isDocumentVerified) onclick="event.preventDefault(); showLockedAlert('{{ $documentGateUrl }}');" @endif
            >
                {!! $navigationItems['chat']['icon'] !!}
                @if($showSidebarTooltips)<span class="sidebar-hover-label" aria-hidden="true">{{ $navigationItems['chat']['label'] }}</span>@endif
            </a>
        @endif

        @if ($canSeeMenu($navigationItems['tickets']))
            <a
                href="{{ $isDocumentVerified ? $navigationItems['tickets']['url'] : '#' }}"
                class="sidebar-icon-btn {{ $isTicketsActive ? 'active' : '' }} {{ !$isDocumentVerified ? 'locked' : '' }}"
                data-provider-help-menu
                data-sidebar-tooltip="{{ $navigationItems['tickets']['label'] }}"
                @if(!$showSidebarTooltips) title="{{ $navigationItems['tickets']['label'] }}" @endif
                aria-label="Help center"
                @if(!$isDocumentVerified) onclick="event.preventDefault(); showLockedAlert('{{ $documentGateUrl }}');" @endif
            >
                {!! $navigationItems['tickets']['icon'] !!}
                @if($showSidebarTooltips)<span class="sidebar-hover-label" aria-hidden="true">{{ $navigationItems['tickets']['label'] }}</span>@endif
            </a>
        @endif

        <form action="{{ provider_route('provider.logout') }}" method="POST" class="sidebar-logout-form">
            @csrf
            <button
                type="submit"
                class="sidebar-icon-btn"
                data-sidebar-tooltip="Logout"
                @if(!$showSidebarTooltips) title="Logout" @endif
                aria-label="Logout"
            >
                <svg viewBox="0 0 24 24" fill="none"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                @if($showSidebarTooltips)<span class="sidebar-hover-label" aria-hidden="true">Logout</span>@endif
            </button>
        </form>
    </div>
</aside>
