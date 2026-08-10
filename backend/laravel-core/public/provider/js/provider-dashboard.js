(function() {
    initProviderTheme();
    initProviderSidebar();
    initProviderSidebarSearch();
    initProviderSubmenus();
    initProviderProfileDropdown();
    initProviderDashboardTabs();
    initProviderDashboardTableSearch();
    initAnalyticsDashboardTooltips();
    initProviderAdminFilterPanels();
})();

function initProviderSidebar() {
    const sidebar = document.getElementById('providerSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const overlay = document.getElementById('providerSidebarOverlay');
    const menuScroll = document.getElementById('providerSidebarMenuScroll');

    if (!sidebar) {
        return;
    }

    const collapsedStorageKey = 'provider_sidebar_collapsed';
    const scrollStorageKey = 'provider_sidebar_menu_scroll';

    function isDesktop() {
        return window.innerWidth > 992;
    }

    function setCollapsedClasses(value) {
        document.body.classList.toggle('sidebar-collapsed', value);
        document.body.classList.toggle('admin-sidebar-collapsed', value);
    }

    function removeCollapsedClasses() {
        document.body.classList.remove('sidebar-collapsed');
        document.body.classList.remove('admin-sidebar-collapsed');
    }

    function hasCollapsedClass() {
        return document.body.classList.contains('sidebar-collapsed')
            || document.body.classList.contains('admin-sidebar-collapsed');
    }

    function openMobileSidebar() {
        if (!sidebar || !overlay) return;

        sidebar.classList.add('show');
        overlay.classList.add('show');
    }

    function closeMobileSidebar() {
        if (!sidebar || !overlay) return;

        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }

    function saveSidebarScroll() {
        if (!menuScroll) return;

        try {
            localStorage.setItem(scrollStorageKey, String(menuScroll.scrollTop));
        } catch (error) {
            console.warn('Cannot save sidebar scroll.', error);
        }
    }

    function restoreSidebarScroll() {
        if (!menuScroll) return;

        let savedScroll = '0';

        try {
            savedScroll = localStorage.getItem(scrollStorageKey) || '0';
        } catch (error) {
            savedScroll = '0';
        }

        requestAnimationFrame(function () {
            menuScroll.scrollTop = parseInt(savedScroll, 10) || 0;
        });
    }

    function setCollapsed(isCollapsed) {
        if (!isDesktop()) {
            removeCollapsedClasses();
            sidebar.classList.remove('hover-expanded');
            return;
        }

        setCollapsedClasses(isCollapsed);
        sidebar.classList.remove('hover-expanded');

        try {
            localStorage.setItem(collapsedStorageKey, isCollapsed ? '1' : '0');
        } catch (error) {
            console.warn('Cannot save sidebar state.', error);
        }
    }

    function loadCollapsedState() {
        if (!isDesktop()) {
            removeCollapsedClasses();
            sidebar.classList.remove('hover-expanded');
            return;
        }

        let saved = '0';

        try {
            saved = localStorage.getItem(collapsedStorageKey) || '0';
        } catch (error) {
            saved = '0';
        }

        setCollapsedClasses(saved === '1');
    }

    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', openMobileSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (!isDesktop()) {
                closeMobileSidebar();
                return;
            }

            const nextCollapsed = !hasCollapsedClass();
            setCollapsed(nextCollapsed);
        });
    }

    sidebar.addEventListener('mouseenter', function () {
        if (hasCollapsedClass() && isDesktop()) {
            sidebar.classList.add('hover-expanded');
        }
    });

    sidebar.addEventListener('mouseleave', function () {
        sidebar.classList.remove('hover-expanded');
    });

    if (menuScroll) {
        menuScroll.addEventListener('scroll', saveSidebarScroll);

        sidebar.querySelectorAll('a.admin-menu-item, .admin-current-link, .admin-submenu a').forEach(function (link) {
            link.addEventListener('click', function () {
                saveSidebarScroll();

                if (!isDesktop()) {
                    closeMobileSidebar();
                }
            });
        });

        window.addEventListener('beforeunload', saveSidebarScroll);
    }

    window.addEventListener('resize', function () {
        loadCollapsedState();
        restoreSidebarScroll();

        if (isDesktop()) {
            closeMobileSidebar();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMobileSidebar();
        }
    });

    loadCollapsedState();
    restoreSidebarScroll();
}

function initProviderSidebarSearch() {
    const searchInput = document.getElementById('providerSidebarSearch');
    const clearButton = document.getElementById('providerSidebarSearchClear');
    const nav = document.getElementById('providerSidebarNav');
    const emptyState = document.getElementById('providerSidebarSearchEmpty');
    const currentMenu = document.getElementById('providerSidebarCurrent');

    if (!searchInput || !nav) {
        return;
    }

    const menuItems = Array.from(nav.querySelectorAll('.admin-menu-search-item'));
    const sections = Array.from(nav.querySelectorAll('[data-section-title]'));

    function normalizeText(value) {
        return String(value || '').toLowerCase().trim();
    }

    function getItemText(item) {
        const text = item.innerText || '';
        const keywords = item.dataset.keywords || '';
        return normalizeText(text + ' ' + keywords);
    }

    function showAll() {
        menuItems.forEach(function (item) {
            item.classList.remove('search-hidden');
        });

        sections.forEach(function (section) {
            section.classList.remove('search-hidden');
        });

        if (emptyState) {
            emptyState.classList.remove('show');
        }

        if (clearButton) {
            clearButton.classList.remove('show');
        }

        if (currentMenu) {
            currentMenu.classList.remove('searching');
        }
    }

    function filterMenus() {
        const keyword = normalizeText(searchInput.value);

        if (!keyword) {
            showAll();
            return;
        }

        let visibleCount = 0;

        menuItems.forEach(function (item) {
            const isMatch = getItemText(item).includes(keyword);

            item.classList.toggle('search-hidden', !isMatch);

            if (isMatch) {
                visibleCount += 1;

                const group = item.closest('.admin-menu-group');

                if (group) {
                    group.classList.add('open');
                }
            }
        });

        sections.forEach(function (section) {
            section.classList.add('search-hidden');
        });

        if (emptyState) {
            emptyState.classList.toggle('show', visibleCount === 0);
        }

        if (clearButton) {
            clearButton.classList.add('show');
        }

        if (currentMenu) {
            currentMenu.classList.add('searching');
        }
    }

    searchInput.addEventListener('input', filterMenus);

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            searchInput.value = '';
            searchInput.focus();
            showAll();
        });
    }

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            searchInput.value = '';
            showAll();
            searchInput.blur();
        }
    });
}

function initProviderSubmenus() {
    document.querySelectorAll('[data-submenu-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const group = button.closest('.admin-menu-group');

            if (!group) return;

            group.classList.toggle('open');
        });
    });
}

function initProviderProfileDropdown() {
    const profileToggle = document.getElementById('profileToggle');
    const profileMenu = document.getElementById('profileMenu');

    if (!profileToggle || !profileMenu) {
        return;
    }

    profileToggle.addEventListener('click', function (event) {
        event.stopPropagation();
        profileMenu.classList.toggle('show');
    });

    document.addEventListener('click', function (event) {
        if (!profileMenu.contains(event.target) && !profileToggle.contains(event.target)) {
            profileMenu.classList.remove('show');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            profileMenu.classList.remove('show');
        }
    });
}

function initProviderDashboardTabs() {
    document.querySelectorAll('.dashboard-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.dashboard-tab').forEach(function (item) {
                item.classList.remove('active');
            });

            tab.classList.add('active');
        });
    });
}

function initProviderDashboardTableSearch() {
    document.querySelectorAll('[data-dashboard-table-search]').forEach(function (input) {
        const card = input.closest('.dashboard-table-card');
        const table = card ? card.querySelector('[data-dashboard-table]') : null;

        if (!table) {
            return;
        }

        const rows = Array.from(table.querySelectorAll('tbody tr'));

        input.addEventListener('input', function () {
            const keyword = input.value.trim().toLowerCase();

            rows.forEach(function (row) {
                row.style.display = row.innerText.toLowerCase().includes(keyword) ? '' : 'none';
            });
        });
    });
}

function initAnalyticsDashboardTooltips() {
    const tooltip = document.querySelector('[data-dashboard-tooltip]');

    if (!tooltip) {
        return;
    }

    function showTooltip(target, event) {
        const text = target.getAttribute('data-tooltip');

        if (!text) {
            return;
        }

        tooltip.textContent = text;
        tooltip.classList.add('is-visible');
        moveTooltip(event);
    }

    function moveTooltip(event) {
        if (!event) {
            return;
        }

        const spacing = 16;
        const tooltipRect = tooltip.getBoundingClientRect();
        const targetRect = event.target && event.target.getBoundingClientRect ? event.target.getBoundingClientRect() : null;
        const anchorX = typeof event.clientX === 'number' ? event.clientX : (targetRect ? targetRect.left + targetRect.width / 2 : 0);
        const anchorY = typeof event.clientY === 'number' ? event.clientY : (targetRect ? targetRect.top : 0);
        let left = anchorX + spacing;
        let top = anchorY + spacing;

        if (left + tooltipRect.width > window.innerWidth - 12) {
            left = anchorX - tooltipRect.width - spacing;
        }

        if (top + tooltipRect.height > window.innerHeight - 12) {
            top = anchorY - tooltipRect.height - spacing;
        }

        tooltip.style.left = Math.max(12, left) + 'px';
        tooltip.style.top = Math.max(12, top) + 'px';
    }

    function hideTooltip() {
        tooltip.classList.remove('is-visible');
    }

    document.querySelectorAll('[data-tooltip]').forEach(function (target) {
        target.addEventListener('mouseenter', function (event) {
            showTooltip(target, event);
        });

        target.addEventListener('mousemove', moveTooltip);
        target.addEventListener('mouseleave', hideTooltip);

        target.addEventListener('focus', function (event) {
            showTooltip(target, event);
        });

        target.addEventListener('blur', hideTooltip);
    });
}

function initProviderAdminFilterPanels() {
    document.querySelectorAll('.admin-booking-mobile-filter-toggle').forEach(function (button) {
        const panel = button.closest('.admin-booking-filter-panel');

        if (!panel) {
            return;
        }

        button.addEventListener('click', function () {
            const expanded = !panel.classList.contains('is-expanded');

            panel.classList.toggle('is-expanded', expanded);
            button.classList.toggle('active', expanded);
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    });
}

function initTableResizer() {
    const tables = document.querySelectorAll('.admin-booking-table, .role-account-table, table.detailed, .dashboard-table-card table');
    
    tables.forEach(table => {
        const cols = table.querySelectorAll('th');
        if (!cols.length) return;
        
        cols.forEach((col, index) => {
            // Skip the last column (actions usually don't need resizing)
            if (index === cols.length - 1) return;

            // Create a resizer element
            const resizer = document.createElement('div');
            resizer.classList.add('table-resizer');
            
            // Add to the header
            col.style.position = 'relative';
            col.appendChild(resizer);

            let x = 0;
            let w = 0;

            const mouseDownHandler = function(e) {
                x = e.clientX;
                
                const styles = window.getComputedStyle(col);
                w = parseInt(styles.width, 10);

                document.addEventListener('mousemove', mouseMoveHandler);
                document.addEventListener('mouseup', mouseUpHandler);
                
                resizer.classList.add('resizing');
            };

            const mouseMoveHandler = function(e) {
                const dx = e.clientX - x;
                col.style.width = `${w + dx}px`;
            };

            const mouseUpHandler = function() {
                resizer.classList.remove('resizing');
                document.removeEventListener('mousemove', mouseMoveHandler);
                document.removeEventListener('mouseup', mouseUpHandler);
            };

            resizer.addEventListener('mousedown', mouseDownHandler);
        });
    });
}

function initProviderTheme() {
    const themeButtons = document.querySelectorAll('.toggle-theme');
    if (!themeButtons.length) return;

    const html = document.documentElement;

    let savedTheme = 'light';

    try {
        savedTheme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
    } catch (error) {
        savedTheme = 'light';
    }

    function applyTheme(theme) {
        const normalizedTheme = theme === 'dark' ? 'dark' : 'light';

        if (normalizedTheme === 'dark') {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }

        html.style.colorScheme = normalizedTheme;

        themeButtons.forEach(btn => {
            const isActive = btn.dataset.themeValue === normalizedTheme;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', String(isActive));
        });
    }

    applyTheme(savedTheme);

    themeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const newTheme = btn.dataset.themeValue === 'dark' ? 'dark' : 'light';

            applyTheme(newTheme);

            try {
                localStorage.setItem('theme', newTheme);
            } catch (error) {
                // The selected theme still applies for the current page.
            }
        });
    });
}

window.showLockedAlert = function(verificationUrl = '/provider/verification') {
    let existing = document.getElementById('customLockedAlert');
    if(existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'customLockedAlert';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;backdrop-filter:blur(4px);opacity:0;transition:opacity 0.3s ease;';

    const modal = document.createElement('div');
    modal.style.cssText = 'background:var(--card-bg, #ffffff);padding:30px;border-radius:24px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);text-align:center;max-width:400px;width:90%;transform:scale(0.95);transition:transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);border:1px solid var(--border-color, #f1f5f9);';

    modal.innerHTML = `
        <div style="width:64px;height:64px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;color:#ef4444;box-shadow:0 0 0 8px #fff0f0;">
            <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </div>
        <h3 style="margin:0 0 12px;font-size:20px;font-weight:700;color:var(--text-main, #0f172a);font-family:'Outfit', sans-serif;">Menu Terkunci</h3>
        <p style="margin:0 0 24px;font-size:15px;color:var(--text-muted, #64748b);line-height:1.6;">Lengkapi KTP, NIB, dan foto usaha lalu tunggu persetujuan admin. Semua menu akan terbuka otomatis setelah akun Anda terverifikasi.</p>
        <div style="display:flex;gap:10px;">
            <button type="button" data-close-locked-alert style="flex:1;background:var(--bg-light,#f1f5f9);color:var(--text-main,#0f172a);border:none;padding:14px;border-radius:14px;font-weight:600;font-size:15px;cursor:pointer;">Nanti</button>
            <a href="${verificationUrl}" style="flex:1;background:#0f172a;color:#fff;padding:14px;border-radius:14px;font-weight:600;font-size:15px;text-decoration:none;">Buka verifikasi</a>
        </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    const closeAlert = () => {
        overlay.style.opacity = '0';
        modal.style.transform = 'scale(0.9)';
        setTimeout(() => overlay.remove(), 300);
    };
    modal.querySelector('[data-close-locked-alert]')?.addEventListener('click', closeAlert);
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) closeAlert();
    });

    requestAnimationFrame(() => {
        overlay.style.opacity = '1';
        modal.style.transform = 'scale(1)';
    });
};

(function() {
    document.querySelectorAll('.provider-service-status-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const hasOngoing = this.getAttribute('data-has-ongoing') === 'true';
            const status = this.getAttribute('data-status');

            if (status === 'active' && hasOngoing) {
                e.preventDefault();
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Peringatan!',
                        text: 'Masih ada pesanan (booking) yang sedang berjalan menggunakan data ini. Apakah Anda yakin ingin menonaktifkannya? Menonaktifkan data ini tidak akan membatalkan pesanan yang ada, tapi akan menyembunyikannya dari pelanggan baru.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ef4444',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Ya, Nonaktifkan',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    })();
                } else {
                    if (confirm('Peringatan! Masih ada pesanan (booking) yang sedang berjalan menggunakan data ini. Apakah Anda yakin ingin menonaktifkannya?')) {
                        this.submit();
                    }
                }
            }
        });
    });
});
