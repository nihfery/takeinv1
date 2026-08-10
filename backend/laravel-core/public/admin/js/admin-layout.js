document.addEventListener('DOMContentLoaded', function () {
    initAdminSidebarToggle();
    initAdminSidebarSearch();
    initAdminSubmenus();
    initAdminProfileDropdown();
    initAdminDashboardTabs();
    initAdminDeleteConfirm();
});

function initAdminSidebarToggle() {
    const sidebar = document.getElementById('sidebar');
    const desktopToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const scrollArea = document.getElementById('sidebarScroll');

    if (!sidebar) {
        return;
    }

    const collapsedKey = 'jasaku_admin_sidebar_collapsed';
    const scrollKey = 'jasaku_admin_sidebar_scroll';

    function isDesktop() {
        return window.innerWidth > 992;
    }

    function applySavedCollapsed() {
        if (!isDesktop()) {
            document.body.classList.remove('admin-sidebar-collapsed');
            sidebar.classList.remove('hover-expanded');
            return;
        }

        const isCollapsed = localStorage.getItem(collapsedKey) === '1';
        document.body.classList.toggle('admin-sidebar-collapsed', isCollapsed);
    }

    function setCollapsed(value) {
        if (!isDesktop()) {
            return;
        }

        document.body.classList.toggle('admin-sidebar-collapsed', value);
        sidebar.classList.remove('hover-expanded');
        localStorage.setItem(collapsedKey, value ? '1' : '0');
    }

    function openMobileSidebar() {
        sidebar.classList.add('show');
        document.body.classList.add('admin-mobile-sidebar-open');

        if (mobileToggle) {
            mobileToggle.setAttribute('aria-expanded', 'true');
        }

        if (overlay) {
            overlay.classList.add('show');
        }
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('show');
        document.body.classList.remove('admin-mobile-sidebar-open');

        if (mobileToggle) {
            mobileToggle.setAttribute('aria-expanded', 'false');
        }

        if (overlay) {
            overlay.classList.remove('show');
        }
    }

    function saveSidebarScroll() {
        if (!scrollArea) {
            return;
        }

        localStorage.setItem(scrollKey, String(scrollArea.scrollTop));
    }

    function restoreSidebarScroll() {
        if (!scrollArea) {
            return;
        }

        const savedScroll = Number(localStorage.getItem(scrollKey) || 0);

        requestAnimationFrame(function () {
            scrollArea.scrollTop = savedScroll;
        });
    }

    if (desktopToggle) {
        desktopToggle.addEventListener('click', function () {
            const nextState = !document.body.classList.contains('admin-sidebar-collapsed');
            setCollapsed(nextState);
        });
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', openMobileSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    sidebar.addEventListener('mouseenter', function () {
        if (isDesktop() && document.body.classList.contains('admin-sidebar-collapsed')) {
            sidebar.classList.add('hover-expanded');
        }
    });

    sidebar.addEventListener('mouseleave', function () {
        sidebar.classList.remove('hover-expanded');
    });

    if (scrollArea) {
        scrollArea.addEventListener('scroll', saveSidebarScroll);
    }

    sidebar.querySelectorAll('a.admin-menu-item, .admin-submenu a, .admin-current-link').forEach(function (menuLink) {
        menuLink.addEventListener('click', function () {
            saveSidebarScroll();

            if (!isDesktop()) {
                closeMobileSidebar();
            }
        });
    });

    window.addEventListener('resize', function () {
        applySavedCollapsed();
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

    applySavedCollapsed();
    restoreSidebarScroll();
    closeMobileSidebar();
}

function initAdminSidebarSearch() {
    const input = document.getElementById('sidebarSearchInput');
    const clearButton = document.getElementById('sidebarSearchClear');
    const emptyState = document.getElementById('sidebarSearchEmpty');
    const currentBox = document.getElementById('currentOpenBox');

    const menuItems = Array.from(document.querySelectorAll('.admin-menu-search-item'));
    const sectionTitles = Array.from(document.querySelectorAll('[data-section-title]'));

    if (!input || menuItems.length === 0) {
        return;
    }

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function resetSearch() {
        menuItems.forEach(function (item) {
            item.classList.remove('search-hidden');
        });

        sectionTitles.forEach(function (section) {
            section.classList.remove('search-hidden');
        });

        if (emptyState) {
            emptyState.classList.remove('show');
        }

        if (clearButton) {
            clearButton.classList.remove('show');
        }

        if (currentBox) {
            currentBox.classList.remove('searching');
        }
    }

    function filterMenu() {
        const keyword = normalize(input.value);

        if (!keyword) {
            resetSearch();
            return;
        }

        let visibleCount = 0;

        menuItems.forEach(function (item) {
            const itemText = normalize(item.innerText);
            const itemKeywords = normalize(item.dataset.keywords);
            const isMatch = (itemText + ' ' + itemKeywords).includes(keyword);

            item.classList.toggle('search-hidden', !isMatch);

            if (isMatch) {
                visibleCount++;

                if (item.classList.contains('admin-menu-group')) {
                    item.classList.add('open');
                }

                const parentGroup = item.closest('.admin-menu-group');

                if (parentGroup) {
                    parentGroup.classList.add('open');
                }
            }
        });

        sectionTitles.forEach(function (section) {
            section.classList.add('search-hidden');
        });

        if (emptyState) {
            emptyState.classList.toggle('show', visibleCount === 0);
        }

        if (clearButton) {
            clearButton.classList.add('show');
        }

        if (currentBox) {
            currentBox.classList.add('searching');
        }
    }

    input.addEventListener('input', filterMenu);

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            input.value = '';
            input.focus();
            resetSearch();
        });
    }

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            input.value = '';
            resetSearch();
            input.blur();
        }
    });
}

function initAdminSubmenus() {
    document.querySelectorAll('[data-submenu-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const group = button.closest('.admin-menu-group');

            if (!group) {
                return;
            }

            group.classList.toggle('open');
        });
    });
}

function initAdminProfileDropdown() {
    const toggle = document.getElementById('profileToggle');
    const menu = document.getElementById('profileMenu');

    if (!toggle || !menu) {
        return;
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        menu.classList.toggle('show');
    });

    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target) && !toggle.contains(event.target)) {
            menu.classList.remove('show');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            menu.classList.remove('show');
        }
    });
}

function initAdminDashboardTabs() {
    document.querySelectorAll('.admin-dashboard-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.admin-dashboard-tab').forEach(function (item) {
                item.classList.remove('active');
            });

            tab.classList.add('active');
        });
    });
}

function initAdminDeleteConfirm() {
    const modal = document.getElementById('adminDeleteConfirmModal');
    const title = document.getElementById('adminDeleteConfirmTitle');
    const item = document.getElementById('adminDeleteConfirmItem');
    const message = document.getElementById('adminDeleteConfirmMessage');
    const confirmButton = modal ? modal.querySelector('[data-admin-delete-confirm]') : null;
    let pendingForm = null;
    let previousFocus = null;

    function isDeleteForm(form) {
        if (!form) {
            return false;
        }

        if (form.hasAttribute('data-delete-form') || form.hasAttribute('data-admin-delete-form')) {
            return true;
        }

        if ((form.getAttribute('method') || '').toUpperCase() === 'DELETE') {
            return true;
        }

        const methodInput = form.querySelector('input[name="_method"]');

        return methodInput && methodInput.value.toUpperCase() === 'DELETE';
    }

    function shouldSkipForm(form) {
        if (!form) {
            return true;
        }

        if (form.dataset.deleteConfirmed === 'true' || form.dataset.adminDeleteSkip === 'true') {
            return true;
        }

        return Boolean(form.closest('.category-modal') && !form.hasAttribute('data-delete-form') && !form.hasAttribute('data-admin-delete-form'));
    }

    function submitPendingForm() {
        if (!pendingForm) {
            return;
        }

        pendingForm.dataset.deleteConfirmed = 'true';

        if (confirmButton) {
            confirmButton.disabled = true;
            confirmButton.textContent = pendingForm.dataset.deleteLoadingLabel || 'Deleting...';
        }

        HTMLFormElement.prototype.submit.call(pendingForm);
    }

    function openModal(form) {
        if (!form) {
            return;
        }

        if (!modal || !confirmButton) {
            if (confirm(form.dataset.deleteFallbackMessage || 'Are you sure you want to delete this data?')) {
                form.dataset.deleteConfirmed = 'true';
                HTMLFormElement.prototype.submit.call(form);
            }

            return;
        }

        pendingForm = form;
        previousFocus = document.activeElement;

        if (title) {
            title.textContent = form.dataset.deleteTitle || 'Delete Data?';
        }

        if (item) {
            item.textContent = form.dataset.deleteItem || form.dataset.deleteName || 'this data';
        }

        if (message) {
            message.textContent = form.dataset.deleteMessage || 'The selected data will be deleted from the system.';
        }

        confirmButton.disabled = false;
        confirmButton.textContent = form.dataset.deleteConfirmLabel || 'Delete';
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        confirmButton.focus();
    }

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        pendingForm = null;

        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !isDeleteForm(form) || shouldSkipForm(form)) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        openModal(form);
    }, true);

    document.addEventListener('click', function (event) {
        const target = event.target instanceof Element ? event.target : null;

        if (!target) {
            return;
        }

        if (target.closest('[data-admin-delete-cancel]')) {
            event.preventDefault();
            closeModal();
            return;
        }

        if (target.closest('[data-admin-delete-confirm]')) {
            event.preventDefault();
            submitPendingForm();
            return;
        }

        if (modal && target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && modal.classList.contains('show')) {
            closeModal();
        }
    });
}
