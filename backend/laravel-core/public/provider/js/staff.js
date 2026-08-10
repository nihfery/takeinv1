(function() {
    initStaffTable();
    initStaffModal();
    initStaffDeleteModal();
    initStaffImagePreview();
    initStaffCountryStateCityAndPhoneCode();
})();

function initStaffTable() {
    const table = document.getElementById('staffTable');
    const searchInput = document.getElementById('staffSearchInput');
    const entriesSelect = document.getElementById('staffEntriesSelect');
    const infoText = document.getElementById('staffInfoText');
    const pagination = document.getElementById('staffPagination');

    if (!table || !searchInput || !entriesSelect || !infoText || !pagination) {
        return;
    }

    const tbody = table.querySelector('tbody');
    const allRows = Array.from(tbody.querySelectorAll('tr')).filter(function (row) {
        return !row.classList.contains('staff-empty-row');
    });

    let currentPage = 1;
    let perPage = parseInt(entriesSelect.value, 10) || 10;
    let filteredRows = allRows;

    function render() {
        perPage = parseInt(entriesSelect.value, 10) || 10;

        const keyword = searchInput.value.trim().toLowerCase();

        filteredRows = allRows.filter(function (row) {
            return row.innerText.toLowerCase().includes(keyword);
        });

        const totalRows = filteredRows.length;
        const totalPages = Math.max(Math.ceil(totalRows / perPage), 1);

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const startIndex = (currentPage - 1) * perPage;
        const endIndex = startIndex + perPage;

        tbody.innerHTML = '';

        if (totalRows === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.className = 'staff-empty-row';

            const emptyCell = document.createElement('td');
            emptyCell.colSpan = table.querySelectorAll('thead th').length;
            emptyCell.textContent = 'No staff available';

            emptyRow.appendChild(emptyCell);
            tbody.appendChild(emptyRow);
        } else {
            filteredRows.slice(startIndex, endIndex).forEach(function (row) {
                tbody.appendChild(row);
            });
        }

        const first = totalRows === 0 ? 0 : startIndex + 1;
        const last = Math.min(endIndex, totalRows);

        infoText.textContent = `Showing ${first} to ${last} of ${totalRows} entries`;

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        pagination.innerHTML = '';

        const buttons = [
            { label: 'First', page: 'first' },
            { label: 'Previous', page: 'previous' },
        ];

        for (let page = 1; page <= totalPages; page++) {
            buttons.push({
                label: String(page),
                page: page,
            });
        }

        buttons.push(
            { label: 'Next', page: 'next' },
            { label: 'Last', page: 'last' }
        );

        buttons.forEach(function (item) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = item.label;

            if (item.page === currentPage) {
                button.classList.add('active');
            }

            if ((item.page === 'first' || item.page === 'previous') && currentPage === 1) {
                button.disabled = true;
            }

            if ((item.page === 'next' || item.page === 'last') && currentPage === totalPages) {
                button.disabled = true;
            }

            button.addEventListener('click', function () {
                if (item.page === 'first') {
                    currentPage = 1;
                } else if (item.page === 'previous') {
                    currentPage = Math.max(currentPage - 1, 1);
                } else if (item.page === 'next') {
                    currentPage = Math.min(currentPage + 1, totalPages);
                } else if (item.page === 'last') {
                    currentPage = totalPages;
                } else {
                    currentPage = item.page;
                }

                render();
            });

            pagination.appendChild(button);
        });
    }

    searchInput.addEventListener('input', function () {
        currentPage = 1;
        render();
    });

    entriesSelect.addEventListener('change', function () {
        currentPage = 1;
        render();
    });

    table.querySelectorAll('thead th[data-sort]').forEach(function (th, index) {
        let asc = true;

        th.addEventListener('click', function () {
            const type = th.dataset.sort;

            allRows.sort(function (a, b) {
                const aText = a.children[index] ? a.children[index].innerText.trim().toLowerCase() : '';
                const bText = b.children[index] ? b.children[index].innerText.trim().toLowerCase() : '';

                if (type === 'number') {
                    return asc
                        ? parseFloat(aText || 0) - parseFloat(bText || 0)
                        : parseFloat(bText || 0) - parseFloat(aText || 0);
                }

                return asc
                    ? aText.localeCompare(bText)
                    : bText.localeCompare(aText);
            });

            asc = !asc;
            currentPage = 1;
            render();
        });
    });

    render();
}

function initStaffModal() {
    const modal = document.getElementById('staffModal');
    const addButtons = document.querySelectorAll('[data-staff-add]');
    const closeBtn = document.getElementById('staffModalClose');
    const cancelBtn = document.getElementById('staffModalCancel');
    const form = document.getElementById('staffForm');
    const formMethod = document.getElementById('staffFormMethod');
    const modalTitle = document.getElementById('staffModalTitle');
    const editButtons = document.querySelectorAll('.staff-edit-btn');

    if (!modal || !form || !formMethod || !modalTitle) {
        return;
    }

    const storeUrl = form.getAttribute('action');

    function openModal() {
        modal.classList.add('active');
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    function resetForm() {
        form.reset();
        form.setAttribute('action', storeUrl);
        formMethod.value = '';
        modalTitle.textContent = 'Add Staff';

        setStaffImagePreview('', false);

        setSelectValue('staffPhoneCodeSelect', '+62');
        setSelectValue('staffCountrySelect', 'Indonesia');
        setSelectValue('staffStateSelect', '');
        setSelectValue('staffCitySelect', '');

        const phoneSelect = document.getElementById('staffPhoneCodeSelect');
        const countrySelect = document.getElementById('staffCountrySelect');
        const stateSelect = document.getElementById('staffStateSelect');
        const citySelect = document.getElementById('staffCitySelect');

        if (phoneSelect) phoneSelect.dataset.selected = '+62';
        if (countrySelect) countrySelect.dataset.selected = 'Indonesia';
        if (stateSelect) stateSelect.dataset.selected = '';
        if (citySelect) citySelect.dataset.selected = '';

        document.dispatchEvent(new CustomEvent('staff:location-reset', {
            detail: {
                country: 'Indonesia',
                state: '',
                city: '',
                phoneCode: '+62',
            },
        }));
    }

    function setInput(name, value) {
        const field = form.querySelector(`[name="${name}"]`);

        if (!field) return;

        field.value = value ?? '';
    }

    function setSelectValue(id, value) {
        const select = document.getElementById(id);

        if (!select) return;

        select.value = value ?? '';
        select.dataset.selected = value ?? '';
    }

    addButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            resetForm();
            openModal();
        });
    });

    // The onboarding overlay intentionally blocks normal page clicks. Expose
    // dedicated events so the guide can open and close this modal without
    // temporarily unlocking the rest of the dashboard.
    document.addEventListener('provider:staff-modal-open-create', function () {
        resetForm();
        openModal();
    });

    document.addEventListener('provider:staff-modal-close', closeModal);

    editButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const staff = JSON.parse(button.dataset.staff || '{}');

            resetForm();

            modalTitle.textContent = 'Edit Staff';
            form.setAttribute('action', staff.update_url);
            formMethod.value = 'PUT';

            setInput('first_name', staff.first_name);
            setInput('last_name', staff.last_name);
            setInput('email', staff.email);
            setInput('username', staff.username);
            setInput('phone_number', staff.phone_number);
            setInput('gender', staff.gender);
            setInput('date_of_birth', staff.date_of_birth);
            setInput('status', staff.status || 'active');
            setInput('postal_code', staff.postal_code);
            setInput('category_id', staff.category_id);
            setInput('branch_id', staff.branch_id);
            setInput('address', staff.address);
            setInput('bio', staff.bio);

            setStaffImagePreview(staff.image_url, !!staff.image_url);

            document.dispatchEvent(new CustomEvent('staff:location-reset', {
                detail: {
                    country: staff.country_id || '',
                    state: staff.state_id || '',
                    city: staff.city_id || '',
                    phoneCode: staff.country_code || '+62',
                },
            }));

            openModal();
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
}

function setStaffImagePreview(imageUrl, hasImage) {
    const preview = document.getElementById('staffImagePreview');
    const placeholder = document.getElementById('staffImagePlaceholder');

    if (!preview || !placeholder) {
        return;
    }

    if (hasImage && imageUrl) {
        preview.src = imageUrl;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        return;
    }

    preview.src = '';
    preview.classList.add('hidden');
    placeholder.classList.remove('hidden');
}

function initStaffDeleteModal() {
    const modal = document.getElementById('staffDeleteModal');
    const cancel = document.getElementById('staffDeleteCancel');
    const confirmForm = document.getElementById('staffDeleteConfirmForm');
    const triggers = document.querySelectorAll('.staff-delete-trigger');

    if (!modal || !cancel || !confirmForm) {
        return;
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            const deleteUrl = trigger.dataset.deleteUrl;

            if (!deleteUrl) {
                return;
            }

            confirmForm.setAttribute('action', deleteUrl);
            modal.classList.add('active');
        });
    });

    cancel.addEventListener('click', function () {
        modal.classList.remove('active');
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.remove('active');
        }
    });
}

function initStaffImagePreview() {
    const input = document.getElementById('staffImageInput');

    if (!input) {
        return;
    }

    input.addEventListener('change', function () {
        const file = input.files && input.files[0];

        if (!file) {
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            setStaffImagePreview(event.target.result, true);
        };

        reader.readAsDataURL(file);
    });
}

function initStaffCountryStateCityAndPhoneCode() {
    const API_BASE_URL = 'https://countriesnow.space/api/v0.1';

    const countrySelect = document.getElementById('staffCountrySelect');
    const stateSelect = document.getElementById('staffStateSelect');
    const citySelect = document.getElementById('staffCitySelect');
    const phoneCodeSelect = document.getElementById('staffPhoneCodeSelect');

    let countryCodeMap = {};

    if (!countrySelect || !stateSelect || !citySelect || !phoneCodeSelect) {
        return;
    }

    async function getJson(url, options = {}) {
        const response = await fetch(url, options);

        if (!response.ok) {
            throw new Error('Request failed: ' + response.status);
        }

        return response.json();
    }

    function resetSelect(select, placeholder) {
        if (!select) return;

        select.innerHTML = '';

        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;

        select.appendChild(option);
    }

    function addOption(select, value, label, selectedValue = '') {
        if (!select) return;

        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;

        if (selectedValue && selectedValue === value) {
            option.selected = true;
        }

        select.appendChild(option);
    }

    function normalizePhoneCode(code) {
        if (!code) return '';

        const cleanCode = String(code).trim();

        if (!cleanCode) return '';

        return cleanCode.startsWith('+') ? cleanCode : '+' + cleanCode;
    }

    function addPhoneCodeOption(phoneCode, label = '') {
        if (!phoneCodeSelect || !phoneCode) return;

        const normalizedCode = normalizePhoneCode(phoneCode);

        const exists = Array.from(phoneCodeSelect.options).some(function (option) {
            return option.value === normalizedCode;
        });

        if (exists) return;

        const option = document.createElement('option');
        option.value = normalizedCode;
        option.textContent = label ? label + ' ' + normalizedCode : normalizedCode;

        phoneCodeSelect.appendChild(option);
    }

    function setPhoneCode(phoneCode) {
        if (!phoneCodeSelect || !phoneCode) return;

        const normalizedCode = normalizePhoneCode(phoneCode);

        addPhoneCodeOption(normalizedCode);
        phoneCodeSelect.value = normalizedCode;
        phoneCodeSelect.dataset.selected = normalizedCode;
    }

    async function loadCountryCodes(selectedPhoneCode = '+62') {
        resetSelect(phoneCodeSelect, 'Loading codes...');

        try {
            const result = await getJson(API_BASE_URL + '/countries/codes');

            resetSelect(phoneCodeSelect, 'Select Code');

            if (!result.error && Array.isArray(result.data)) {
                result.data.forEach(function (country) {
                    const countryName = country.name || '';
                    const dialCode = country.dial_code || country.dialCode || country.code || '';

                    if (countryName && dialCode) {
                        countryCodeMap[countryName] = normalizePhoneCode(dialCode);
                        addPhoneCodeOption(dialCode, countryName);
                    }
                });
            }

            setPhoneCode(selectedPhoneCode || '+62');
        } catch (error) {
            console.error('Failed to load phone codes:', error);

            resetSelect(phoneCodeSelect, 'Failed to load codes');
            setPhoneCode(selectedPhoneCode || '+62');
        }
    }

    async function loadCountries(selectedCountry = 'Indonesia', selectedState = '', selectedCity = '') {
        resetSelect(countrySelect, 'Loading countries...');

        try {
            const result = await getJson(API_BASE_URL + '/countries/states');

            resetSelect(countrySelect, 'Select Country');

            if (!result.error && Array.isArray(result.data)) {
                result.data.forEach(function (country) {
                    if (!country.name) return;

                    addOption(countrySelect, country.name, country.name, selectedCountry);
                });
            }

            if (selectedCountry) {
                await loadStates(selectedCountry, selectedState, selectedCity);
            } else {
                resetSelect(stateSelect, 'Select Country First');
                resetSelect(citySelect, 'Select State First');
            }
        } catch (error) {
            console.error('Failed to load countries:', error);
            resetSelect(countrySelect, 'Failed to load countries');
            resetSelect(stateSelect, 'Select Country First');
            resetSelect(citySelect, 'Select State First');
        }
    }

    async function loadStates(countryName, selectedStateValue = '', selectedCityValue = '') {
        resetSelect(stateSelect, 'Loading states...');
        resetSelect(citySelect, 'Select State First');

        if (!countryName) {
            resetSelect(stateSelect, 'Select Country First');
            return;
        }

        try {
            const result = await getJson(API_BASE_URL + '/countries/states', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    country: countryName,
                }),
            });

            resetSelect(stateSelect, 'Select State');

            const states = result.data && Array.isArray(result.data.states)
                ? result.data.states
                : [];

            states.forEach(function (state) {
                const stateName = state.name || state;

                if (!stateName) return;

                addOption(stateSelect, stateName, stateName, selectedStateValue);
            });

            if (selectedStateValue) {
                await loadCities(countryName, selectedStateValue, selectedCityValue);
            }
        } catch (error) {
            console.error('Failed to load states:', error);
            resetSelect(stateSelect, 'Failed to load states');
            resetSelect(citySelect, 'Select State First');
        }
    }

    async function loadCities(countryName, stateName, selectedCityValue = '') {
        resetSelect(citySelect, 'Loading cities...');

        if (!countryName || !stateName) {
            resetSelect(citySelect, 'Select State First');
            return;
        }

        try {
            const result = await getJson(API_BASE_URL + '/countries/state/cities', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    country: countryName,
                    state: stateName,
                }),
            });

            resetSelect(citySelect, 'Select City');

            const cities = Array.isArray(result.data) ? result.data : [];

            cities.forEach(function (cityName) {
                if (!cityName) return;

                addOption(citySelect, cityName, cityName, selectedCityValue);
            });
        } catch (error) {
            console.error('Failed to load cities:', error);
            resetSelect(citySelect, 'Failed to load cities');
        }
    }

    countrySelect.addEventListener('change', async function () {
        const countryName = this.value;

        resetSelect(citySelect, 'Select State First');

        if (countryCodeMap[countryName]) {
            setPhoneCode(countryCodeMap[countryName]);
        }

        await loadStates(countryName);
    });

    stateSelect.addEventListener('change', async function () {
        const countryName = countrySelect.value;
        const stateName = this.value;

        await loadCities(countryName, stateName);
    });

    document.addEventListener('staff:location-reset', async function (event) {
        const detail = event.detail || {};

        const country = detail.country || 'Indonesia';
        const state = detail.state || '';
        const city = detail.city || '';
        const phoneCode = detail.phoneCode || '+62';

        countrySelect.dataset.selected = country;
        stateSelect.dataset.selected = state;
        citySelect.dataset.selected = city;
        phoneCodeSelect.dataset.selected = phoneCode;

        await loadCountryCodes(phoneCode);
        await loadCountries(country, state, city);
        setPhoneCode(phoneCode);
    });

    loadCountryCodes(phoneCodeSelect.dataset.selected || '+62');
    loadCountries(
        countrySelect.dataset.selected || 'Indonesia',
        stateSelect.dataset.selected || '',
        citySelect.dataset.selected || ''
    );
}
