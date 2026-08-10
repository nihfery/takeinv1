(function() {
    const layout = document.getElementById('providerLayout');
    const sidebarToggle = document.getElementById('providerSidebarToggle');
    const sidebarOverlay = document.getElementById('providerSidebarOverlay');

    const profileDropdown = document.getElementById('providerProfileDropdown');
    const profileToggle = document.getElementById('providerProfileToggle');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            layout.classList.toggle('sidebar-open');
        })();
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            layout.classList.remove('sidebar-open');
        });
    }

    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('open');
        });

        document.addEventListener('click', function (e) {
            if (!profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('open');
            }
        });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 991) {
            layout.classList.remove('sidebar-open');
        }
    });
});