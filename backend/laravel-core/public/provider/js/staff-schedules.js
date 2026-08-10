(function() {
    initStaffSchedulePage();
})();

function initStaffSchedulePage() {
    const page = document.querySelector('[data-staff-schedule-page]');

    if (!page) {
        return;
    }

    const dayLabels = {
        monday: 'Sen',
        tuesday: 'Sel',
        wednesday: 'Rab',
        thursday: 'Kam',
        friday: 'Jum',
        saturday: 'Sab',
        sunday: 'Min',
    };

    const presetDays = {
        weekdays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        weekend: ['saturday', 'sunday'],
        all: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        clear: [],
    };

    const searchInput = document.getElementById('staffScheduleSearchInput');
    const branchFilter = document.getElementById('staffScheduleBranchFilter');
    const statusFilter = document.getElementById('staffScheduleStatusFilter');
    const countLabel = document.getElementById('staffScheduleFilterCount');
    const showingLabel = document.getElementById('staffScheduleShowing');
    const filterPanel = page.querySelector('.provider-staff-schedule-filter-panel');
    const filterToggle = page.querySelector('[data-staff-schedule-filter-toggle]');
    const resetButton = page.querySelector('[data-staff-schedule-reset]');
    const applyButtons = page.querySelectorAll('[data-staff-schedule-apply], [data-staff-schedule-search]');
    const tabButtons = Array.from(page.querySelectorAll('[data-schedule-tab]'));
    const rows = Array.from(page.querySelectorAll('[data-staff-schedule-row]'));
    const cards = Array.from(page.querySelectorAll('[data-staff-schedule-card]'));
    const emptyRow = page.querySelector('[data-staff-schedule-empty]');
    const mobileEmpty = page.querySelector('[data-staff-schedule-mobile-empty]');

    let activeTab = 'all';

    function normalize(value) {
        return String(value || '').trim().toLowerCase();
    }

    function matchesItem(item) {
        const keyword = normalize(searchInput ? searchInput.value : '');
        const branchValue = branchFilter ? branchFilter.value : 'all';
        const statusValue = statusFilter ? statusFilter.value : 'all';
        const itemStatus = item.dataset.status || 'empty';
        const itemBranch = item.dataset.branch || 'none';
        const itemSearch = item.dataset.search || '';

        const matchesKeyword = keyword === '' || itemSearch.includes(keyword);
        const matchesBranch = branchValue === 'all' || itemBranch === branchValue;
        const tabValue = activeTab === 'all' ? statusValue : activeTab;
        const matchesStatus = tabValue === 'all' || itemStatus === tabValue;

        return matchesKeyword && matchesBranch && matchesStatus;
    }

    function render() {
        let visibleRows = 0;

        rows.forEach(function (row) {
            const isVisible = matchesItem(row);
            row.hidden = !isVisible;

            if (isVisible) {
                visibleRows += 1;
            }
        });

        cards.forEach(function (card) {
            card.hidden = !matchesItem(card);
        });

        if (emptyRow) {
            emptyRow.hidden = rows.length === 0 || visibleRows > 0;
        }

        if (mobileEmpty) {
            mobileEmpty.hidden = cards.length === 0 || visibleRows > 0;
        }

        if (countLabel) {
            countLabel.textContent = visibleRows + ' staff';
        }

        if (showingLabel) {
            showingLabel.textContent = visibleRows;
        }
    }

    function selectedDaysFor(form) {
        return Array.from(form.querySelectorAll('[data-schedule-day]'))
            .filter(function (checkbox) {
                return checkbox.checked;
            })
            .map(function (checkbox) {
                return checkbox.value;
            });
    }

    function summaryFor(dayKeys) {
        const labels = dayKeys.map(function (day) {
            return dayLabels[day] || day;
        });

        if (labels.length === 0) {
            return 'Belum ada jadwal';
        }

        if (labels.length > 4) {
            return labels.slice(0, 4).join(', ') + ' +' + (labels.length - 4);
        }

        return labels.join(', ');
    }

    function associatedField(form, selector) {
        if (!form) {
            return null;
        }

        return form.querySelector(selector) || document.querySelector('[form="' + form.id + '"]' + selector);
    }

    function owningContainer(form) {
        return form.closest('[data-staff-schedule-row], [data-staff-schedule-card]');
    }

    function updateFormState(form) {
        const container = owningContainer(form);
        const dayCheckboxes = Array.from(form.querySelectorAll('[data-schedule-day]'));
        const selectedDays = selectedDaysFor(form);
        const selectedCount = selectedDays.length;
        const percentage = Math.round((selectedCount / 7) * 100);
        const status = selectedCount > 0 ? 'scheduled' : 'empty';
        const summary = summaryFor(selectedDays);
        const startInput = associatedField(form, '[data-schedule-start]');
        const endInput = associatedField(form, '[data-schedule-end]');
        const startTime = startInput ? startInput.value : '';
        const endTime = endInput ? endInput.value : '';

        dayCheckboxes.forEach(function (checkbox) {
            const label = checkbox.closest('.provider-staff-schedule-day');

            if (label) {
                label.classList.toggle('is-checked', checkbox.checked);
            }
        });

        if (!container) {
            return;
        }

        container.dataset.status = status;

        container.querySelectorAll('[data-schedule-count-label]').forEach(function (label) {
            label.textContent = selectedCount + '/7';
        });

        container.querySelectorAll('[data-schedule-summary]').forEach(function (label) {
            label.textContent = summary;
        });

        container.querySelectorAll('[data-schedule-progress-bar]').forEach(function (bar) {
            bar.style.width = percentage + '%';
        });

        container.querySelectorAll('[data-schedule-time-label]').forEach(function (label) {
            label.textContent = (startTime || '--:--') + ' - ' + (endTime || '--:--');
        });

        container.querySelectorAll('[data-schedule-status-badge]').forEach(function (badge) {
            badge.classList.toggle('success', status === 'scheduled');
            badge.classList.toggle('warning', status === 'empty');
            badge.textContent = status === 'scheduled' ? 'Sudah dijadwalkan' : 'Belum ada jadwal';
        });
    }

    function setActiveTab(button) {
        activeTab = button.dataset.scheduleTab || 'all';

        tabButtons.forEach(function (tabButton) {
            tabButton.classList.toggle('active', tabButton === button);
        });

        if (statusFilter && activeTab !== 'all') {
            statusFilter.value = 'all';
        }

        render();
    }

    function findFormFromButton(button) {
        const formId = button.getAttribute('form');

        if (formId) {
            return document.getElementById(formId);
        }

        return button.closest('form');
    }

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setActiveTab(button);
        });
    });

    [searchInput, branchFilter, statusFilter].forEach(function (control) {
        if (!control) {
            return;
        }

        const eventName = control === searchInput ? 'input' : 'change';

        control.addEventListener(eventName, function () {
            if (statusFilter && statusFilter === control && control.value !== 'all') {
                activeTab = 'all';
                tabButtons.forEach(function (tabButton) {
                    tabButton.classList.toggle('active', tabButton.dataset.scheduleTab === 'all');
                });
            }

            render();
        });
    });

    applyButtons.forEach(function (button) {
        button.addEventListener('click', render);
    });

    if (resetButton) {
        resetButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (searchInput) searchInput.value = '';
            if (branchFilter) branchFilter.value = 'all';
            if (statusFilter) statusFilter.value = 'all';

            activeTab = 'all';
            tabButtons.forEach(function (tabButton) {
                tabButton.classList.toggle('active', tabButton.dataset.scheduleTab === 'all');
            });

            render();
        });
    }

    if (filterToggle && filterPanel) {
        filterToggle.addEventListener('click', function () {
            const expanded = filterPanel.classList.toggle('is-expanded');
            filterToggle.classList.toggle('active', expanded);
            filterToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }

    page.querySelectorAll('[data-schedule-form]').forEach(function (form) {
        form.addEventListener('change', function (event) {
            if (event.target.matches('[data-schedule-day]')) {
                updateFormState(form);
                render();
            }
        });

        updateFormState(form);
    });

    page.querySelectorAll('[data-schedule-start], [data-schedule-end]').forEach(function (input) {
        input.addEventListener('input', function () {
            if (input.form) {
                updateFormState(input.form);
            }
        });
    });

    page.querySelectorAll('[data-schedule-preset]').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = findFormFromButton(button);
            const preset = button.dataset.schedulePreset || 'clear';
            const selected = presetDays[preset] || [];

            if (!form) {
                return;
            }

            form.querySelectorAll('[data-schedule-day]').forEach(function (checkbox) {
                checkbox.checked = selected.includes(checkbox.value);
            });

            updateFormState(form);
            render();
        });
    });

    render();
}
