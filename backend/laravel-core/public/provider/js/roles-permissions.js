(function() {
    initRoleFilters();
    initRolePermissionForm();
    initRoleDeleteConfirm();
})();

function initRoleFilters() {
    const searchInput = document.querySelector('[data-role-search]');
    const statusFilter = document.querySelector('[data-role-status-filter]');
    const branchFilter = document.querySelector('[data-role-branch-filter]');
    const resetButton = document.querySelector('[data-role-filter-reset]');
    const emptyState = document.querySelector('[data-role-filter-empty]');
    const rows = Array.from(document.querySelectorAll('[data-role-row]'));

    if (!rows.length) {
        return;
    }

    function normalized(value) {
        return String(value || '').trim().toLowerCase();
    }

    function applyFilters() {
        const keyword = normalized(searchInput?.value);
        const status = normalized(statusFilter?.value);
        const branch = String(branchFilter?.value || '');
        let visibleCount = 0;

        rows.forEach(function (row) {
            const label = normalized(row.dataset.roleLabel);
            const rowStatus = normalized(row.dataset.roleStatus);
            const rowBranch = String(row.dataset.roleBranch || '');
            const matchesKeyword = keyword === '' || label.includes(keyword);
            const matchesStatus = status === '' || rowStatus === status;
            const matchesBranch = branch === '' || rowBranch === branch;
            const isVisible = matchesKeyword && matchesStatus && matchesBranch;

            row.classList.toggle('is-hidden', !isVisible);

            if (isVisible && row.tagName === 'TR') {
                visibleCount += 1;
            }
        });

        if (emptyState) {
            emptyState.classList.toggle('is-hidden', visibleCount > 0);
        }
    }

    [searchInput, statusFilter, branchFilter].forEach(function (control) {
        if (control) {
            control.addEventListener('input', applyFilters);
            control.addEventListener('change', applyFilters);
        }
    });

    if (resetButton) {
        resetButton.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
            }

            if (statusFilter) {
                statusFilter.value = '';
            }

            if (branchFilter) {
                branchFilter.value = '';
            }

            applyFilters();
        });
    }

    applyFilters();
}

function initRolePermissionForm() {
    const form = document.getElementById('roleForm');
    const methodInput = document.getElementById('roleFormMethod');
    const title = document.getElementById('roleFormTitle');
    const state = document.getElementById('roleFormState');
    const submitBtn = document.getElementById('roleSubmitBtn');
    const resetBtn = document.getElementById('roleResetBtn');
    const cancelBtn = document.getElementById('roleCancelEditBtn');
    const selectAllMenusBtn = document.getElementById('roleSelectAllMenus');
    const passwordInput = document.getElementById('accountPasswordInput');
    const passwordRequired = document.getElementById('accountPasswordRequired');
    const editButtons = document.querySelectorAll('.role-edit-btn');
    const modal = document.getElementById('roleModal');
    const closeBtn = document.getElementById('roleModalClose');

    if (!form || !methodInput || !title || !state || !submitBtn) {
        return;
    }

    const storeUrl = form.dataset.storeUrl || form.getAttribute('action');

    function openModal() {
        if (!modal) {
            return;
        }

        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('role-modal-open');
    }

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('role-modal-open');
    }

    function menuInputs() {
        return Array.from(form.querySelectorAll('input[name="menu_keys[]"]'));
    }

    function syncPermissionButtons() {
        document.querySelectorAll('[data-section-toggle]').forEach(function (button) {
            const section = button.closest('.role-permission-group') || button.closest('.role-menu-section');

            if (!section) {
                return;
            }

            const inputs = Array.from(section.querySelectorAll('input[name="menu_keys[]"]'));
            const hasUnchecked = inputs.some(function (input) {
                return !input.checked;
            });

            button.textContent = hasUnchecked ? 'Pilih' : 'Kosongkan';
        });

        if (selectAllMenusBtn) {
            const inputs = menuInputs();
            const hasUnchecked = inputs.some(function (input) {
                return !input.checked;
            });

            selectAllMenusBtn.textContent = hasUnchecked ? 'Select all' : 'Clear all';
        }
    }

    function setFormMode(mode) {
        const isEdit = mode === 'edit';

        title.textContent = isEdit ? 'Edit Branch Account' : 'Create Branch Account';
        state.textContent = isEdit ? 'Editing' : 'New';
        submitBtn.textContent = isEdit ? 'Update Branch Account' : 'Save Branch Account';

        methodInput.disabled = !isEdit;

        if (passwordInput) {
            passwordInput.required = !isEdit;
            passwordInput.placeholder = isEdit ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter';
            passwordInput.value = '';
        }

        if (passwordRequired) {
            passwordRequired.hidden = isEdit;
        }
    }

    function resetForm() {
        form.reset();
        form.setAttribute('action', storeUrl);
        methodInput.disabled = true;
        setFormMode('create');

        Array.from(form.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]), textarea')).forEach(function (input) {
            input.value = '';
        });

        Array.from(form.querySelectorAll('select')).forEach(function (select) {
            select.value = '';
        });

        menuInputs().forEach(function (input) {
            input.checked = false;
        });

        const statusSelect = document.getElementById('roleStatusSelect');

        if (statusSelect) {
            statusSelect.value = 'active';
        }

        syncPermissionButtons();
    }

    function setInputValue(id, value) {
        const input = document.getElementById(id);

        if (input) {
            input.value = value ?? '';
        }
    }

    function setCheckedValues(name, values) {
        const selected = (values || []).map(function (value) {
            return String(value);
        });

        Array.from(form.querySelectorAll(`input[name="${name}[]"]`)).forEach(function (input) {
            input.checked = selected.includes(String(input.value));
        });

        syncPermissionButtons();
    }

    setFormMode('create');
    syncPermissionButtons();

    editButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const role = JSON.parse(button.dataset.role || '{}');

            resetForm();
            setFormMode('edit');

            form.setAttribute('action', role.update_url);
            methodInput.disabled = false;

            setInputValue('accountNameInput', role.account_name);
            setInputValue('accountEmailInput', role.account_email);
            setInputValue('accountPasswordInput', '');
            setInputValue('roleNameInput', role.role_name);
            setInputValue('roleBranchSelect', role.branch_id || '');
            setInputValue('roleStatusSelect', role.status || 'active');
            setInputValue('roleDescriptionInput', role.description || '');

            setCheckedValues('menu_keys', role.menu_keys || []);

            openModal();
        });
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            resetForm();
            openModal();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    document.querySelectorAll('[data-section-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const section = button.closest('.role-permission-group') || button.closest('.role-menu-section');

            if (!section) {
                return;
            }

            const inputs = Array.from(section.querySelectorAll('input[name="menu_keys[]"]'));
            const shouldCheck = inputs.some(function (input) {
                return !input.checked;
            });

            inputs.forEach(function (input) {
                input.checked = shouldCheck;
            });

            syncPermissionButtons();
        });
    });

    if (selectAllMenusBtn) {
        selectAllMenusBtn.addEventListener('click', function () {
            const inputs = menuInputs();
            const shouldCheck = inputs.some(function (input) {
                return !input.checked;
            });

            inputs.forEach(function (input) {
                input.checked = shouldCheck;
            });

            syncPermissionButtons();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    if (modal && modal.dataset.openOnErrors === '1') {
        openModal();
    }

    menuInputs().forEach(function (input) {
        input.addEventListener('change', function () {
            syncPermissionButtons();
        });
    });
}

function initRoleDeleteConfirm() {
    document.querySelectorAll('[data-confirm-delete]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            const confirmed = window.confirm('Non-aktifkan / Hapus Role?\n\nJika Role ini belum pernah digunakan (terkait dengan Akun Login atau Staff), maka Role akan DIHAPUS PERMANEN.\n\nNamun jika sudah pernah digunakan, Role ini hanya akan DINONAKTIFKAN.');

            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
}
