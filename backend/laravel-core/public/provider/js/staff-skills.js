(function() {
    initStaffSkillPage();
})();

function initStaffSkillPage() {
    const page = document.querySelector('[data-staff-skill-page]');

    if (!page) {
        return;
    }

    const searchInput = document.getElementById('staffSkillSearchInput');
    const branchFilter = document.getElementById('staffSkillBranchFilter');
    const coverageFilter = document.getElementById('staffSkillCoverageFilter');
    const countLabel = document.getElementById('staffSkillFilterCount');
    const showingLabel = document.getElementById('staffSkillShowing');
    const filterPanel = page.querySelector('.provider-staff-skill-filter-panel');
    const filterToggle = page.querySelector('[data-staff-skill-filter-toggle]');
    const resetButton = page.querySelector('[data-staff-skill-reset]');
    const applyButtons = page.querySelectorAll('[data-staff-skill-apply], [data-staff-skill-search]');
    const tabButtons = Array.from(page.querySelectorAll('[data-skill-tab]'));
    const rows = Array.from(page.querySelectorAll('[data-staff-skill-row]'));
    const cards = Array.from(page.querySelectorAll('[data-staff-skill-card]'));
    const emptyRow = page.querySelector('[data-staff-skill-empty]');
    const mobileEmpty = page.querySelector('[data-staff-skill-mobile-empty]');

    let activeTab = 'all';

    function normalize(value) {
        return String(value || '').trim().toLowerCase();
    }

    function matchesItem(item) {
        const keyword = normalize(searchInput ? searchInput.value : '');
        const branchValue = branchFilter ? branchFilter.value : 'all';
        const coverageValue = coverageFilter ? coverageFilter.value : 'all';
        const itemStatus = item.dataset.status || 'empty';
        const itemBranch = item.dataset.branch || 'none';
        const itemSearch = item.dataset.search || '';

        const matchesKeyword = keyword === '' || itemSearch.includes(keyword);
        const matchesBranch = branchValue === 'all' || itemBranch === branchValue;
        const tabValue = activeTab === 'all' ? coverageValue : activeTab;
        const matchesCoverage = tabValue === 'all' || itemStatus === tabValue;

        return matchesKeyword && matchesBranch && matchesCoverage;
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

    function setActiveTab(button) {
        activeTab = button.dataset.skillTab || 'all';

        tabButtons.forEach(function (tabButton) {
            tabButton.classList.toggle('active', tabButton === button);
        });

        if (coverageFilter && activeTab !== 'all') {
            coverageFilter.value = 'all';
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

    function owningContainer(form) {
        return form.closest('[data-staff-skill-row], [data-staff-skill-card]');
    }

    function updateFormState(form) {
        const container = owningContainer(form);
        const checkboxes = Array.from(form.querySelectorAll('[data-skill-checkbox]'));
        const selectedCount = checkboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;
        const totalCount = parseInt(form.dataset.totalServices, 10) || checkboxes.length;
        const percentage = totalCount > 0 ? Math.round((selectedCount / totalCount) * 100) : 0;
        const status = selectedCount > 0 ? 'configured' : 'empty';

        checkboxes.forEach(function (checkbox) {
            const label = checkbox.closest('.provider-staff-skill-option');

            if (label) {
                label.classList.toggle('is-checked', checkbox.checked);
            }
        });

        if (!container) {
            return;
        }

        container.dataset.status = status;

        container.querySelectorAll('[data-skill-selected-label]').forEach(function (label) {
            label.textContent = selectedCount + '/' + totalCount;
        });

        container.querySelectorAll('[data-skill-selected-count]').forEach(function (label) {
            label.textContent = selectedCount;
        });

        container.querySelectorAll('[data-skill-coverage-label]').forEach(function (label) {
            label.textContent = percentage + (label.tagName.toLowerCase() === 'small' ? '% service aktif' : '%');
        });

        container.querySelectorAll('[data-skill-progress-bar]').forEach(function (bar) {
            bar.style.width = percentage + '%';
        });

        container.querySelectorAll('[data-skill-status-badge]').forEach(function (badge) {
            badge.classList.toggle('success', status === 'configured');
            badge.classList.toggle('warning', status === 'empty');
            badge.textContent = status === 'configured' ? 'Sudah diatur' : 'Belum diatur';
        });
    }

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setActiveTab(button);
        });
    });

    [searchInput, branchFilter, coverageFilter].forEach(function (control) {
        if (!control) {
            return;
        }

        const eventName = control === searchInput ? 'input' : 'change';

        control.addEventListener(eventName, function () {
            if (coverageFilter && coverageFilter === control && control.value !== 'all') {
                activeTab = 'all';
                tabButtons.forEach(function (tabButton) {
                    tabButton.classList.toggle('active', tabButton.dataset.skillTab === 'all');
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
            if (coverageFilter) coverageFilter.value = 'all';

            activeTab = 'all';
            tabButtons.forEach(function (tabButton) {
                tabButton.classList.toggle('active', tabButton.dataset.skillTab === 'all');
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

    page.querySelectorAll('[data-skill-form]').forEach(function (form) {
        form.addEventListener('change', function (event) {
            if (event.target.matches('[data-skill-checkbox]')) {
                updateFormState(form);
            }
        });

        updateFormState(form);
    });

    page.querySelectorAll('[data-skill-select-all]').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = findFormFromButton(button);

            if (!form) {
                return;
            }

            form.querySelectorAll('[data-skill-checkbox]').forEach(function (checkbox) {
                checkbox.checked = true;
            });

            updateFormState(form);
            render();
        });
    });

    page.querySelectorAll('[data-skill-clear]').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = findFormFromButton(button);

            if (!form) {
                return;
            }

            form.querySelectorAll('[data-skill-checkbox]').forEach(function (checkbox) {
                checkbox.checked = false;
            });

            updateFormState(form);
            render();
        });
    });

    render();
}
