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

    document.querySelectorAll('[data-provider-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const providerId = button.dataset.providerToggle;
            const branchRow = document.getElementById('providerBranches-' + providerId);
            const parentRow = button.closest('.provider-parent-row');

            if (!branchRow || button.disabled) {
                return;
            }

            const shouldOpen = branchRow.hidden;

            branchRow.hidden = !shouldOpen;
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

            if (parentRow) {
                parentRow.classList.toggle('is-expanded', shouldOpen);
            }
        });
    });

});
