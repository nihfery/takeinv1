(function () {
    'use strict';

    const storageKey = 'providerBookingsView';

    const bookingPage = (scope = document) => {
        if (scope instanceof Element && scope.matches('.provider-booking-category-page')) {
            return scope;
        }

        return scope.querySelector?.('.provider-booking-category-page') || null;
    };

    const savedView = () => {
        try {
            return window.localStorage.getItem(storageKey) === 'grid' ? 'grid' : 'list';
        } catch (error) {
            return 'list';
        }
    };

    const saveView = (view) => {
        try {
            window.localStorage.setItem(storageKey, view);
        } catch (error) {
            // The toggle must remain usable when browser storage is unavailable.
        }
    };

    const setBookingView = (page, view, persist = true) => {
        if (!page) return;

        const gridButton = page.querySelector('[data-booking-view="grid"]');
        const listButton = page.querySelector('[data-booking-view="list"]');
        const gridContainer = page.querySelector('#gridViewContainer');
        const listContainer = page.querySelector('#listViewContainer');

        if (!gridButton || !listButton || !gridContainer || !listContainer) return;

        const showList = view === 'list';

        gridButton.classList.toggle('active', !showList);
        listButton.classList.toggle('active', showList);
        gridButton.setAttribute('aria-pressed', String(!showList));
        listButton.setAttribute('aria-pressed', String(showList));

        gridContainer.hidden = showList;
        listContainer.hidden = !showList;
        gridContainer.style.display = showList ? 'none' : 'block';
        listContainer.style.display = showList ? 'block' : 'none';

        if (persist) saveView(showList ? 'list' : 'grid');
    };

    const initializeBookingPage = (scope = document) => {
        const page = bookingPage(scope);
        if (page) setBookingView(page, savedView(), false);
    };

    const closeFilterModal = () => {
        const overlay = document.getElementById('filterModalOverlay');
        if (!overlay) return;

        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.removeProperty('overflow');
    };

    document.addEventListener('click', (event) => {
        const viewButton = event.target.closest('[data-booking-view]');
        if (viewButton) {
            event.preventDefault();
            setBookingView(
                viewButton.closest('.provider-booking-category-page'),
                viewButton.dataset.bookingView
            );
            return;
        }

        if (event.target.closest('#btnOpenFilterModal')) {
            const overlay = document.getElementById('filterModalOverlay');
            if (overlay) {
                overlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }
            return;
        }

        if (event.target.closest('#filterModalClose')) {
            closeFilterModal();
            return;
        }

        if (event.target.id === 'filterModalOverlay') {
            closeFilterModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeFilterModal();
    });

    document.addEventListener('provider:content-loaded', (event) => {
        initializeBookingPage(document);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initializeBookingPage(document), { once: true });
    } else {
        initializeBookingPage(document);
    }
})();
