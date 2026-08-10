<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Admin Dashboard - JasaKu')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('admin/css/admin-dashboard.css') }}">
    
    @stack('styles')
    
    <link rel="stylesheet" href="{{ asset('admin/css/colorful-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('provider/css/provider-admin-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('provider/css/provider-sidebar-admin-parity.css') }}?v=admin-sidebar-parity-7">
    <link rel="stylesheet" href="{{ asset('provider/css/floating-island.css') }}">
</head>
<body class="admin-body provider-body">

    <div class="provider-app-shell admin-layout" id="adminLayout">
        @include('admin.partials.sidebar')

        <div class="provider-main-area admin-main-wrapper">
            @include('admin.partials.topbar')

            <main class="provider-content-area admin-main-content">
                @yield('content')
            </main>
        </div>

        <div class="provider-sidebar-overlay admin-sidebar-overlay" id="sidebarOverlay"></div>
    </div>

    <div class="category-modal" id="adminDeleteConfirmModal" aria-hidden="true">
        <div class="category-modal-dialog delete" role="dialog" aria-modal="true" aria-labelledby="adminDeleteConfirmTitle">
            <div class="delete-icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M5 7h14"></path>
                    <path d="M9 7V5h6v2"></path>
                    <path d="m7 7 1 14h8l1-14"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                </svg>
            </div>

            <h3 id="adminDeleteConfirmTitle">Delete Data?</h3>

            <p>
                <strong id="adminDeleteConfirmItem">This data</strong><br>
                <span id="adminDeleteConfirmMessage">The selected data will be deleted from the system.</span>
            </p>

            <div class="delete-actions">
                <button type="button" class="modal-cancel-btn" data-admin-delete-cancel>
                    Cancel
                </button>

                <button type="button" class="delete-confirm-btn" data-admin-delete-confirm>
                    Delete
                </button>
            </div>
        </div>
    </div>

    <script src="https://js.pusher.com/8.4.0/pusher.min.js" defer></script>
    <script src="{{ asset('js/realtime-notifications.js') }}" defer></script>
    <script src="{{ asset('admin/js/admin-layout.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
