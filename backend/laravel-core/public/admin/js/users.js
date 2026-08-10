document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('.admin-booking-mobile-filter-toggle');

        if (!toggle) {
            return;
        }

        const form = toggle.closest('.admin-booking-filter-panel');

        if (!form) {
            return;
        }

        event.preventDefault();

        form.classList.toggle('is-expanded');

        const isExpanded = form.classList.contains('is-expanded');

        toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        toggle.classList.toggle('active', isExpanded);
    });

});
