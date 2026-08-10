<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Provider Dashboard - JasaKu')</title>

    <link rel="stylesheet" href="{{ asset('provider/css/provider-dashboard.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('admin/css/admin-dashboard.css') }}?v={{ time() }}">

    @stack('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/colorful-theme.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('provider/css/provider-admin-theme.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('provider/css/provider-sidebar-admin-parity.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('provider/css/floating-island.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('provider/css/driver.css') }}?v={{ filemtime(public_path('provider/css/driver.css')) }}" />
    <link rel="stylesheet" href="{{ asset('provider/css/provider-tutorial.css') }}?v={{ filemtime(public_path('provider/css/provider-tutorial.css')) }}" />
    <script>
        // Apply theme early to prevent FOUC
        (function() {
            var theme = 'light';

            try {
                theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
            } catch (error) {
                theme = 'light';
            }

            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }

            document.documentElement.style.colorScheme = theme;
        })();
    </script>
</head>
<body class="admin-body provider-body">
    <div class="provider-app-shell admin-layout" id="providerLayout">
        @include('provider.partials.dashboard.sidebar')

        <div class="provider-main-area admin-main-wrapper">
            @include('provider.partials.dashboard.topbar')

            <main class="provider-content-area admin-main-content">
                @yield('content')
            </main>
        </div>

        <div class="provider-sidebar-overlay admin-sidebar-overlay" id="providerSidebarOverlay"></div>
    </div>

    <script src="https://js.pusher.com/8.4.0/pusher.min.js" defer></script>
    <script src="{{ asset('provider/js/driver.js') }}?v={{ filemtime(public_path('provider/js/driver.js')) }}" defer></script>
    
    @php
        $onboardingStatus = 'completed';
        $documentsVerified = false;
        $setupState = [
            'hasBranches' => false,
            'hasServices' => false,
            'hasStaff' => false,
        ];
        if (Auth::check() && Auth::user()->role === 'provider') {
            $ownerId = \App\Modules\Provider\Application\Support\ProviderMenuAccess::providerOwnerId(Auth::user());
            $profile = \App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile::where('user_id', $ownerId)->first();
            if ($profile) {
                $onboardingStatus = $profile->onboarding_status;
                $onboardingCurrentStep = $profile->onboarding_current_step;
                $isPaid = $profile->hasActiveSubscription();
                $documentsVerified = $profile->document_status === 'verified';
            }

            if ($documentsVerified) {
                $setupState = [
                    'hasBranches' => \App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch::where('provider_id', $ownerId)->exists(),
                    'hasServices' => \App\Modules\Catalog\Infrastructure\Persistence\Models\Service::where('provider_id', $ownerId)->exists(),
                    'hasStaff' => \App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff::where('provider_id', $ownerId)->exists(),
                ];
            }
        }

        if (! $documentsVerified) {
            $onboardingStatus = 'completed';
            $onboardingCurrentStep = null;
        }
    @endphp
    <script>
        window.ProviderOnboarding = {
            status: "{{ $onboardingStatus }}",
            current_step: "{{ $onboardingCurrentStep ?? '' }}",
            isPaid: {{ isset($isPaid) && $isPaid ? 'true' : 'false' }},
            enabled: {{ $documentsVerified ? 'true' : 'false' }},
            setupState: @json($setupState),
            updateUrl: "{{ route('provider.profile.onboarding.update') }}",
            csrfToken: "{{ csrf_token() }}",
            routes: {
                dashboard: "{{ provider_route('provider.dashboard') }}",
                services: "{{ provider_route('provider.services.index') }}",
                serviceCreate: "{{ provider_route('provider.services.create') }}",
                branches: "{{ provider_route('provider.branch.index') }}",
                branchCreate: "{{ provider_route('provider.branch.create') }}",
                staffs: "{{ provider_route('provider.staffs.index') }}",
                skills: "{{ provider_route('provider.staff.skills') }}",
                schedules: "{{ provider_route('provider.staff.schedules') }}",
                bookings: "{{ provider_route('provider.bookings.index') }}",
                calendar: "{{ provider_route('provider.calendar.index') }}"
            }
        };
    </script>

    <script src="{{ asset('js/realtime-notifications.js') }}" defer></script>
    <script src="{{ asset('provider/js/provider-dashboard.js') }}?v={{ filemtime(public_path('provider/js/provider-dashboard.js')) }}" defer></script>
    <script src="{{ asset('provider/js/bookings.js') }}?v={{ filemtime(public_path('provider/js/bookings.js')) }}" defer></script>
    <script src="{{ asset('provider/js/spa-router.js') }}?v={{ filemtime(public_path('provider/js/spa-router.js')) }}" defer></script>
    <script src="{{ asset('provider/js/onboarding.js') }}?v={{ filemtime(public_path('provider/js/onboarding.js')) }}" defer></script>

    @stack('scripts')
</body>
</html>
