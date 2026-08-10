@php
    $authUser = auth()->user();
    $adminName = $authUser->name ?? 'Demo Admin';
    $adminEmail = $authUser->email ?? 'admin@mail.com';
    $adminProfile = $authUser?->adminProfile;

    $parts = collect(explode(' ', trim($adminName)))->filter()->values();

    $initials = $parts->count() >= 2
        ? strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1))
        : strtoupper(substr($adminName, 0, 1));

    $image = $adminProfile->avatar ?? ($authUser->image ?? null);
    $imageUrl = null;

    if (!empty($image)) {
        $image = ltrim($image, '/');
        if (\Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])) {
            $imageUrl = $image;
        } elseif (\Illuminate\Support\Str::startsWith($image, 'storage/')) {
            $imageUrl = asset($image);
        } else {
            $imageUrl = asset('storage/' . $image);
        }
    }

    $chatUnreadCount = $authUser ? \App\Modules\Chat\Application\Support\ChatUnreadCounter::forUser($authUser) : 0;
    $chatUnreadLabel = $chatUnreadCount > 99 ? '99+' : (string) $chatUnreadCount;

    $menuSections = [
        [
            'title' => 'Main',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'admin.dashboard',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.dashboard') ? route('admin.dashboard') : url('/admin/dashboard'),
                    'active' => request()->routeIs('admin.dashboard'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12 12 4l9 8"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path></svg>',
                ],
                [
                    'label' => 'Bookings',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.bookings.index') ? route('admin.bookings.index') : '#',
                    'active' => request()->routeIs('admin.bookings.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><path d="M5 5h14v16H5z"></path><path d="M3 10h18"></path></svg>',
                ],
                [
                    'label' => 'Calendar',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.calendar.index') ? route('admin.calendar.index') : '#',
                    'active' => request()->routeIs('admin.calendar.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>',
                ],
                [
                    'label' => 'Chat',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.chat.index') ? route('admin.chat.index') : '#',
                    'active' => request()->routeIs('admin.chat.*'),
                    'badge' => $chatUnreadCount,
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
                ],
                [
                    'label' => 'Chatbot',
                    'url' => '#',
                    'active' => false,
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="10" x="3" y="11" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" x2="8" y1="16" y2="16"/><line x1="16" x2="16" y1="16" y2="16"/></svg>',
                ],
            ],
        ],
        [
            'title' => 'Business',
            'items' => [
                [
                    'label' => 'Services',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.services.index') ? route('admin.services.index') : '#',
                    'active' => request()->routeIs('admin.services.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/></svg>',
                ],
                [
                    'label' => 'Categories',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.service-categories.index') ? route('admin.service-categories.index') : '#',
                    'active' => request()->routeIs('admin.service-categories.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path></svg>',
                ],
                [
                    'label' => 'Coupons',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.coupons.index') ? route('admin.coupons.index') : '#',
                    'active' => request()->routeIs('admin.coupons.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v8l3-3 4 4 4-4 3 3z"></path></svg>',
                ],
                [
                    'label' => 'Locations',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.countries.index') ? route('admin.countries.index') : '#',
                    'active' => request()->routeIs('admin.countries.*') || request()->routeIs('admin.states.*') || request()->routeIs('admin.cities.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
                ],
                [
                    'label' => 'Tax',
                    'url' => '#',
                    'active' => false,
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
                ],
            ],
        ],
        [
            'title' => 'People',
            'items' => [
                [
                    'label' => 'Providers',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.providers.index') ? route('admin.providers.index') : '#',
                    'active' => request()->routeIs('admin.providers.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"></circle><path d="M2 21a7 7 0 0 1 14 0"></path><path d="M17 11l2 2 4-4"></path></svg>',
                ],
                [
                    'label' => 'Users',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.users.index') ? route('admin.users.index') : '#',
                    'active' => request()->routeIs('admin.users.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>',
                ],
                [
                    'label' => 'Tickets',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.tickets.index') ? route('admin.tickets.index') : '#',
                    'active' => request()->routeIs('admin.tickets.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7z"></path></svg>',
                ],
            ],
        ],
        [
            'title' => 'Finance',
            'items' => [
                [
                    'label' => 'Transactions',
                    'url' => '#',
                    'active' => false,
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v14H4z"></path><path d="M4 9h16"></path><path d="M8 13h4"></path></svg>',
                ],
                [
                    'label' => 'Provider Request',
                    'url' => '#',
                    'active' => false,
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"></path><path d="M8 9h8"></path><path d="M8 13h8"></path></svg>',
                ],
            ],
        ],
        [
            'title' => 'System',
            'items' => [
                [
                    'label' => 'Settings',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.settings.general') ? route('admin.settings.general') : '#',
                    'active' => request()->routeIs('admin.settings.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
                ],
                [
                    'label' => 'Roles & Permissions',
                    'url' => \Illuminate\Support\Facades\Route::has('admin.roles.index') ? route('admin.roles.index') : '#',
                    'active' => request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*'),
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
                ],
            ],
        ],
    ];

    $activeGroup = null;
    $activeSubmenu = null;

    foreach ($menuSections as &$section) {
        $section['is_active'] = false;
        foreach ($section['items'] as $item) {
            if (!empty($item['active'])) {
                $section['is_active'] = true;
                $activeGroup = $section;
                $activeSubmenu = $item;
                break;
            }
        }
    }
    unset($section);

    if (!$activeGroup && !empty($menuSections)) {
        $menuSections[0]['is_active'] = true;
        $activeGroup = $menuSections[0];
        $activeSubmenu = $activeGroup['items'][0] ?? null;
    }

    \Illuminate\Support\Facades\View::share('topbarMainMenus', $menuSections);
    \Illuminate\Support\Facades\View::share('activeSubmenuKey', $activeSubmenu['label'] ?? null);
@endphp

<aside class="floating-sidebar" id="adminSidebar">
    <!-- Theme Toggle (Top Pill) -->
    <div class="sidebar-pill">
        <button type="button" class="sidebar-icon-btn toggle-theme active" title="Light Theme">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
        </button>
        <button type="button" class="sidebar-icon-btn toggle-theme" title="Dark Theme">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
    </div>

    <!-- Main Navigation (Middle Pill) -->
    <nav class="sidebar-main-nav sidebar-pill">
        @foreach ($activeGroup['items'] ?? [] as $submenu)
            <a href="{{ $submenu['url'] ?? '#' }}" 
               class="sidebar-icon-btn {{ ($submenu['label'] ?? '') === ($activeSubmenu['label'] ?? '') ? 'active' : '' }}" 
               title="{{ $submenu['label'] ?? '' }}">
                @if(!empty($submenu['icon']))
                    {!! $submenu['icon'] !!}
                @else
                    <span style="font-size: 16px; font-weight: 600; line-height: 1;">{{ substr($submenu['label'] ?? 'M', 0, 1) }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <!-- Bottom Nav (FAQ and Logout) -->
    <div class="sidebar-bottom-nav sidebar-pill">
        <a href="#" class="sidebar-icon-btn" title="Help / FAQ">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </a>
        <form action="{{ route('admin.logout') }}" method="POST" style="margin: 0; padding: 0;">
            @csrf
            <button type="submit" class="sidebar-icon-btn" title="Logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
            </button>
        </form>
    </div>
</aside>