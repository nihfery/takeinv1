(function() {
    initCountryStateCityAndPhoneCode();
    initBranchCoordinates();
    initBranchLocationMap();
    initBranchImagePreview();
    initBranchHoliday();
    initBranchStaffMultiselect();
    initBranchTable();
    initBranchDeleteModal();
})();

function initCountryStateCityAndPhoneCode() {
    const API_BASE_URL = 'https://countriesnow.space/api/v0.1';

    const countrySelect = document.getElementById('countrySelect');
    const stateSelect = document.getElementById('stateSelect');
    const citySelect = document.getElementById('citySelect');
    const phoneCodeSelect = document.getElementById('branchPhoneCodeSelect');

    const selectedCountry = countrySelect ? countrySelect.dataset.selected : '';
    const selectedState = stateSelect ? stateSelect.dataset.selected : '';
    const selectedCity = citySelect ? citySelect.dataset.selected : '';
    const selectedPhoneCode = phoneCodeSelect ? phoneCodeSelect.dataset.selected : '+62';

    let countryCodeMap = {};

    if (!countrySelect && !stateSelect && !citySelect && !phoneCodeSelect) {
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
    }

    async function loadCountryCodes() {
        if (!phoneCodeSelect) return;

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

    async function loadCountries() {
        if (!countrySelect) return;

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
        if (!stateSelect || !citySelect) return;

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
        if (!citySelect) return;

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

    if (countrySelect) {
        countrySelect.addEventListener('change', async function () {
            const countryName = this.value;

            if (stateSelect) {
                stateSelect.dataset.selected = '';
            }

            if (citySelect) {
                citySelect.dataset.selected = '';
            }

            resetSelect(citySelect, 'Select State First');

            if (countryCodeMap[countryName]) {
                setPhoneCode(countryCodeMap[countryName]);
            }

            await loadStates(countryName);
        });
    }

    if (stateSelect) {
        stateSelect.addEventListener('change', async function () {
            const countryName = countrySelect ? countrySelect.value : '';
            const stateName = this.value;

            if (citySelect) {
                citySelect.dataset.selected = '';
            }

            await loadCities(countryName, stateName);
        });
    }

    loadCountryCodes();
    loadCountries();
}

function initBranchCoordinates() {
    const latitudeInput = document.getElementById('branchLatitudeInput');
    const longitudeInput = document.getElementById('branchLongitudeInput');
    const useCurrentLocationButton = document.getElementById('branchUseCurrentLocation');

    if (!latitudeInput || !longitudeInput || !useCurrentLocationButton) {
        return;
    }

    if (!navigator.geolocation) {
        useCurrentLocationButton.disabled = true;
        useCurrentLocationButton.textContent = 'Location Not Available';
        return;
    }

    useCurrentLocationButton.addEventListener('click', function () {
        const originalText = useCurrentLocationButton.textContent;

        useCurrentLocationButton.disabled = true;
        useCurrentLocationButton.textContent = 'Locating...';

        navigator.geolocation.getCurrentPosition(
            function (position) {
                latitudeInput.value = position.coords.latitude.toFixed(7);
                longitudeInput.value = position.coords.longitude.toFixed(7);
                latitudeInput.dispatchEvent(new Event('change', { bubbles: true }));
                useCurrentLocationButton.textContent = 'Position Added';

                setTimeout(function () {
                    useCurrentLocationButton.disabled = false;
                    useCurrentLocationButton.textContent = originalText;
                }, 1600);
            },
            function () {
                useCurrentLocationButton.disabled = false;
                useCurrentLocationButton.textContent = 'Location Failed';

                setTimeout(function () {
                    useCurrentLocationButton.textContent = originalText;
                }, 1600);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000,
            }
        );
    });
}

function initBranchLocationMap() {
    const mapEl = document.getElementById('branchLocationMap');
    const latitudeInput = document.getElementById('branchLatitudeInput');
    const longitudeInput = document.getElementById('branchLongitudeInput');

    if (!mapEl || !latitudeInput || !longitudeInput || typeof maplibregl === 'undefined') {
        return;
    }

    const DEFAULT_CENTER = [113.9213, -0.7893]; // Indonesia
    const startLat = parseFloat(latitudeInput.value);
    const startLng = parseFloat(longitudeInput.value);
    const hasStart = Number.isFinite(startLat) && Number.isFinite(startLng);

    const map = new maplibregl.Map({
        container: mapEl,
        style: {
            version: 8,
            sources: {
                osm: {
                    type: 'raster',
                    tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                    tileSize: 256,
                    attribution: '© OpenStreetMap',
                },
            },
            layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
        },
        center: hasStart ? [startLng, startLat] : DEFAULT_CENTER,
        zoom: hasStart ? 15 : 4.2,
        attributionControl: false,
    });

    map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');

    const zoomInBtn = document.getElementById('branchZoomIn');
    const zoomOutBtn = document.getElementById('branchZoomOut');
    if (zoomInBtn) zoomInBtn.addEventListener('click', function () { map.zoomIn(); });
    if (zoomOutBtn) zoomOutBtn.addEventListener('click', function () { map.zoomOut(); });

    const marker = new maplibregl.Marker({ draggable: true, color: '#ff5c16' })
        .setLngLat(hasStart ? [startLng, startLat] : DEFAULT_CENTER)
        .addTo(map);

    // Keep the canvas sized correctly once visible.
    const resizeObserver = new ResizeObserver(() => map.resize());
    resizeObserver.observe(mapEl);
    map.on('load', () => map.resize());

    function setInputs(lng, lat) {
        latitudeInput.value = Number(lat).toFixed(7);
        longitudeInput.value = Number(lng).toFixed(7);
    }

    function placeMarker(lng, lat, fly) {
        marker.setLngLat([lng, lat]);
        if (fly) {
            map.flyTo({ center: [lng, lat], zoom: Math.max(map.getZoom(), 14), duration: 600 });
        }
    }

    // Set a plain text field value if present.
    function setFieldValue(id, value) {
        const el = document.getElementById(id);
        if (el && value) {
            el.value = value;
        }
    }

    // Reverse geocode the pinned point and auto-fill the location fields.
    async function applyReverseGeocode(lng, lat) {
        try {
            const response = await fetch(
                'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng + '&accept-language=id&addressdetails=1&zoom=18',
                { headers: { Accept: 'application/json' } }
            );
            if (!response.ok) return;

            const data = await response.json();
            const address = data.address || {};

            const addressInput = document.getElementById('branchAddressInput');
            if (addressInput) {
                const line = [
                    address.road,
                    address.house_number,
                    address.suburb || address.village || address.neighbourhood || address.city_district,
                ].filter(Boolean).join(' ').trim();
                addressInput.value = line || (data.display_name || '').split(',').slice(0, 2).join(', ').trim();
            }

            setFieldValue('branchZipInput', address.postcode);
            setFieldValue('branchCountryInput', address.country || 'Indonesia');
            setFieldValue('branchStateInput', address.state || address.region || '');
            // City/Regency level in Indonesia comes from `city` (kota) or `county`
            // (kabupaten). town/municipality/village are sub-levels (kecamatan/desa).
            setFieldValue(
                'branchCityInput',
                address.city || address.county || address.town || address.municipality || ''
            );
        } catch (error) {
            console.error('Reverse geocode failed:', error);
        }
    }

    function commit(lng, lat) {
        setInputs(lng, lat);
        applyReverseGeocode(lng, lat);
    }

    // Drag the pin -> update inputs + auto-fill location.
    marker.on('dragend', function () {
        const pos = marker.getLngLat();
        commit(pos.lng, pos.lat);
    });

    // Click the map -> move pin + auto-fill location.
    map.on('click', function (event) {
        placeMarker(event.lngLat.lng, event.lngLat.lat, false);
        commit(event.lngLat.lng, event.lngLat.lat);
    });

    // Manual coordinate edits or geolocation (dispatches change) -> move pin + auto-fill.
    function syncFromInputs() {
        const lat = parseFloat(latitudeInput.value);
        const lng = parseFloat(longitudeInput.value);
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            placeMarker(lng, lat, true);
            applyReverseGeocode(lng, lat);
        }
    }

    latitudeInput.addEventListener('change', syncFromInputs);
    longitudeInput.addEventListener('change', syncFromInputs);

    // Search a typed address/place on the map (Nominatim geocoding).
    const searchButton = document.getElementById('branchMapSearch');
    const searchInput = document.getElementById('branchMapSearchInput');

    async function runMapSearch() {
        const query = searchInput ? searchInput.value.trim() : '';

        // Only the dedicated search box drives the pin. The Address field is left
        // independent so it can be edited freely without moving the map pin.
        if (!query || !searchButton) return;

        const original = searchButton.innerHTML;
        searchButton.disabled = true;
        searchButton.textContent = 'Mencari...';

        try {
            const response = await fetch(
                'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language=id&q=' + encodeURIComponent(query),
                { headers: { Accept: 'application/json' } }
            );
            const data = await response.json();
            if (Array.isArray(data) && data[0]) {
                const lng = parseFloat(data[0].lon);
                const lat = parseFloat(data[0].lat);
                placeMarker(lng, lat, true);
                commit(lng, lat);
            } else {
                searchButton.textContent = 'Tidak ditemukan';
                setTimeout(function () { searchButton.innerHTML = original; }, 1400);
                searchButton.disabled = false;
                return;
            }
        } catch (error) {
            console.error('Geocode failed:', error);
        } finally {
            searchButton.disabled = false;
            searchButton.innerHTML = original;
        }
    }

    if (searchButton) {
        searchButton.addEventListener('click', runMapSearch);
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                runMapSearch();
            }
        });
    }

    // Live autocomplete suggestions while typing.
    const suggestionsEl = document.getElementById('branchMapSuggestions');
    let suggestTimer = null;
    let suggestController = null;

    function hideSuggestions() {
        if (suggestionsEl) {
            suggestionsEl.hidden = true;
            suggestionsEl.innerHTML = '';
        }
    }

    function renderSuggestions(items) {
        if (!suggestionsEl) return;

        suggestionsEl.innerHTML = '';

        if (!items.length) {
            const empty = document.createElement('li');
            empty.className = 'branch-map-suggestion-empty';
            empty.textContent = 'Alamat tidak ditemukan.';
            suggestionsEl.appendChild(empty);
            suggestionsEl.hidden = false;
            return;
        }

        items.forEach(function (item) {
            const li = document.createElement('li');
            const icon = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';
            const label = document.createElement('span');
            label.textContent = item.display_name || '';
            li.innerHTML = icon;
            li.appendChild(label);

            li.addEventListener('mousedown', function (event) {
                event.preventDefault();
                const lng = parseFloat(item.lon);
                const lat = parseFloat(item.lat);
                if (searchInput) {
                    searchInput.value = (item.display_name || '').split(',').slice(0, 3).join(', ').trim();
                }
                placeMarker(lng, lat, true);
                commit(lng, lat);
                hideSuggestions();
            });

            suggestionsEl.appendChild(li);
        });

        suggestionsEl.hidden = false;
    }

    async function fetchSuggestions(query) {
        if (suggestController) {
            suggestController.abort();
        }
        suggestController = new AbortController();

        try {
            const response = await fetch(
                'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=6&addressdetails=1&accept-language=id&countrycodes=id&q=' + encodeURIComponent(query),
                { headers: { Accept: 'application/json' }, signal: suggestController.signal }
            );
            const data = await response.json();
            renderSuggestions(Array.isArray(data) ? data : []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Suggestion fetch failed:', error);
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = searchInput.value.trim();
            if (suggestTimer) clearTimeout(suggestTimer);

            if (query.length < 3) {
                hideSuggestions();
                return;
            }

            suggestTimer = setTimeout(function () {
                fetchSuggestions(query);
            }, 300);
        });

        searchInput.addEventListener('blur', function () {
            setTimeout(hideSuggestions, 150);
        });
    }
}

function initBranchImagePreview() {
    const gallery = document.getElementById('branchGallery');
    const input = document.getElementById('branchImageInput');
    const addBtn = document.getElementById('branchGalleryAdd');

    if (!gallery || !input || !addBtn) return;

    const maxImages = parseInt(gallery.dataset.max || '5', 10);
    // Source of truth for newly uploaded files (existing images live in the DOM as
    // .branch-gallery-item[data-existing] with their own existing_images[] inputs).
    const dt = new DataTransfer();

    const removeIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

    function existingItems() {
        return Array.from(gallery.querySelectorAll('.branch-gallery-item[data-existing]'));
    }

    function totalCount() {
        return existingItems().length + dt.files.length;
    }

    function refreshState() {
        // Cover badge always on the very first photo in the grid.
        gallery.querySelectorAll('.branch-gallery-cover').forEach((el) => el.remove());
        const firstItem = gallery.querySelector('.branch-gallery-item');
        if (firstItem) {
            const badge = document.createElement('span');
            badge.className = 'branch-gallery-cover';
            badge.textContent = 'Cover';
            firstItem.appendChild(badge);
        }
        addBtn.classList.toggle('hidden', totalCount() >= maxImages);
    }

    function renderNewPreviews() {
        gallery.querySelectorAll('.branch-gallery-item[data-new]').forEach((el) => el.remove());

        Array.from(dt.files).forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'branch-gallery-item';
            item.dataset.new = '1';

            const img = document.createElement('img');
            img.alt = 'Branch photo';
            const reader = new FileReader();
            reader.onload = (event) => { img.src = event.target.result; };
            reader.readAsDataURL(file);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'branch-gallery-remove';
            remove.setAttribute('aria-label', 'Remove photo');
            remove.innerHTML = removeIcon;
            remove.addEventListener('click', () => {
                const next = new DataTransfer();
                Array.from(dt.files).forEach((f, i) => { if (i !== index) next.items.add(f); });
                dt.items.clear();
                Array.from(next.files).forEach((f) => dt.items.add(f));
                input.files = dt.files;
                renderNewPreviews();
                refreshState();
            });

            item.appendChild(img);
            item.appendChild(remove);
            gallery.insertBefore(item, addBtn);
        });

        refreshState();
    }

    function bindExistingRemovers() {
        existingItems().forEach((item) => {
            const btn = item.querySelector('.branch-gallery-remove');
            if (!btn || btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => {
                item.remove();
                refreshState();
            });
        });
    }

    input.addEventListener('change', () => {
        const selected = Array.from(input.files || []);
        let room = maxImages - totalCount();

        selected.forEach((file) => {
            if (room <= 0) return;
            dt.items.add(file);
            room -= 1;
        });

        input.files = dt.files;
        renderNewPreviews();
    });

    bindExistingRemovers();
    refreshState();
}

function initBranchHoliday() {
    const holidayWrapper = document.getElementById('holidayWrapper');
    const addHolidayBtn = document.getElementById('addHolidayBtn');
    const removeIcon = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
        </svg>
    `;

    function bindRemoveHolidayButtons() {
        document.querySelectorAll('.remove-holiday-btn').forEach(function (button) {
            button.onclick = function () {
                const rows = document.querySelectorAll('.branch-holiday-row');

                if (rows.length > 1) {
                    this.closest('.branch-holiday-row').remove();
                    return;
                }

                const input = this.closest('.branch-holiday-row').querySelector('input');

                if (input) {
                    input.value = '';
                }
            };
        });
    }

    if (holidayWrapper && addHolidayBtn) {
        addHolidayBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'branch-holiday-row';

            row.innerHTML = `
                <input type="date" name="holidays[]">
                <button type="button" class="remove-holiday-btn" aria-label="Remove holiday" title="Remove holiday">${removeIcon}</button>
            `;

            holidayWrapper.appendChild(row);
            bindRemoveHolidayButtons();
        });

        bindRemoveHolidayButtons();
    }
}

function initBranchStaffMultiselect() {
    const staffMultiselect = document.getElementById('branchStaffMultiselect');
    const staffControl = document.getElementById('branchStaffControl');
    const staffTags = document.getElementById('branchStaffTags');

    function renderStaffTags() {
        if (!staffTags) return;

        const checkedStaffInputs = document.querySelectorAll('.branch-staff-option input[type="checkbox"]:checked');

        staffTags.innerHTML = '';

        if (checkedStaffInputs.length === 0) {
            const placeholder = document.createElement('span');
            placeholder.className = 'branch-staff-placeholder';
            placeholder.id = 'branchStaffPlaceholder';
            placeholder.textContent = 'Select Staff';
            staffTags.appendChild(placeholder);
            return;
        }

        checkedStaffInputs.forEach(function (input) {
            const staffId = input.value;
            const staffName = input.dataset.name || 'Staff';

            const tag = document.createElement('span');
            tag.className = 'branch-staff-tag';
            tag.dataset.staffId = staffId;

            const text = document.createElement('span');
            text.textContent = staffName;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.setAttribute('aria-label', 'Remove ' + staffName);
            removeBtn.innerHTML = `
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            `;
            removeBtn.dataset.removeStaffId = staffId;

            tag.appendChild(text);
            tag.appendChild(removeBtn);
            staffTags.appendChild(tag);
        });
    }

    function syncStaffOptions() {
        document.querySelectorAll('.branch-staff-option').forEach(function (option) {
            const input = option.querySelector('input[type="checkbox"]');

            if (!input) return;

            if (input.checked) {
                option.classList.add('selected');
            } else {
                option.classList.remove('selected');
            }
        });
    }

    if (staffMultiselect && staffControl) {
        staffControl.addEventListener('click', function () {
            staffMultiselect.classList.toggle('open');
        });

        staffControl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                staffMultiselect.classList.toggle('open');
            }

            if (event.key === 'Escape') {
                staffMultiselect.classList.remove('open');
            }
        });

        document.querySelectorAll('.branch-staff-option input[type="checkbox"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                syncStaffOptions();
                renderStaffTags();
            });
        });

        document.addEventListener('click', function (event) {
            if (!staffMultiselect.contains(event.target)) {
                staffMultiselect.classList.remove('open');
            }
        });

        if (staffTags) {
            staffTags.addEventListener('click', function (event) {
                const removeBtn = event.target.closest('[data-remove-staff-id]');

                if (!removeBtn) return;

                event.stopPropagation();

                const staffId = removeBtn.dataset.removeStaffId;
                const input = document.querySelector('.branch-staff-option input[type="checkbox"][value="' + staffId + '"]');

                if (input) {
                    input.checked = false;
                }

                syncStaffOptions();
                renderStaffTags();
            });
        }

        syncStaffOptions();
        renderStaffTags();
    }
}

function initBranchTable() {
    const table = document.getElementById('branchTable');
    const searchInput = document.getElementById('branchSearchInput');
    const entriesSelect = document.getElementById('branchEntriesSelect');
    const infoText = document.getElementById('branchInfoText');
    const pagination = document.getElementById('branchPagination');
    const statusTabs = document.querySelectorAll('[data-branch-status-filter]');
    const resetButton = document.getElementById('branchResetFilter');
    const mobileSearchSubmit = document.querySelector('.provider-branch-filter-panel .admin-booking-mobile-search-submit');
    const mobileList = document.getElementById('branchMobileList');
    const mobileEmpty = document.getElementById('branchMobileEmpty');

    if (!table || !searchInput || !entriesSelect || !infoText || !pagination) {
        return;
    }

    const tbody = table.querySelector('tbody');
    const allRows = Array.from(tbody.querySelectorAll('tr')).filter(function (row) {
        return !row.classList.contains('branch-empty-row');
    });
    const mobileCards = mobileList ? Array.from(mobileList.querySelectorAll('[data-branch-card]')) : [];
    const mobileCardMap = new Map(mobileCards.map(function (card) {
        return [card.dataset.branchId, card];
    }));

    let currentPage = 1;
    let perPage = parseInt(entriesSelect.value, 10) || 10;
    let filteredRows = allRows;
    let currentStatus = 'all';
    let sortState = {
        index: null,
        asc: true,
    };

    function rowMatchesFilters(row, keyword) {
        const status = row.dataset.branchStatus || 'active';
        const statusMatches = currentStatus === 'all' || status === currentStatus;
        const keywordMatches = !keyword || row.innerText.toLowerCase().includes(keyword);

        return statusMatches && keywordMatches;
    }

    function emptyMarkup(message) {
        return `
            <div>
                <span>
                    <svg viewBox="0 0 24 24">
                        <path d="M4 21V5a2 2 0 0 1 2-2h10v18"></path>
                        <path d="M16 8h2a2 2 0 0 1 2 2v11"></path>
                        <path d="M8 7h4"></path>
                        <path d="M8 11h4"></path>
                        <path d="M8 15h4"></path>
                    </svg>
                </span>
                <strong>No branch available.</strong>
                <p>${message}</p>
            </div>
        `;
    }

    function renderMobileCards(visibleRows) {
        if (!mobileList) {
            return;
        }

        mobileCards.forEach(function (card) {
            card.hidden = true;
        });

        if (visibleRows.length === 0) {
            if (mobileEmpty) {
                mobileEmpty.hidden = false;
            }

            return;
        }

        if (mobileEmpty) {
            mobileEmpty.hidden = true;
        }

        const fragment = document.createDocumentFragment();

        visibleRows.forEach(function (row) {
            const card = mobileCardMap.get(row.dataset.branchId);

            if (!card) {
                return;
            }

            card.hidden = false;
            fragment.appendChild(card);
        });

        if (mobileEmpty) {
            mobileList.insertBefore(fragment, mobileEmpty);
        } else {
            mobileList.appendChild(fragment);
        }
    }

    function renderInfo(first, last, totalRows) {
        infoText.innerHTML = `<strong>${first}-${last}</strong><span>/ ${totalRows}</span>`;
    }

    function syncStatusTabs() {
        statusTabs.forEach(function (tab) {
            const isActive = (tab.dataset.branchStatusFilter || 'all') === currentStatus;

            tab.classList.toggle('active', isActive);
        });
    }

    function syncSortIcons(activeTh, asc) {
        table.querySelectorAll('.admin-booking-sort').forEach(function (button) {
            button.classList.remove('active');

            button.querySelectorAll('.admin-booking-sort-icons span').forEach(function (icon) {
                icon.classList.remove('active');
            });
        });

        if (!activeTh) {
            return;
        }

        const button = activeTh.querySelector('.admin-booking-sort');
        const icons = button ? button.querySelectorAll('.admin-booking-sort-icons span') : [];

        if (button) {
            button.classList.add('active');
        }

        if (icons.length >= 2) {
            icons[asc ? 0 : 1].classList.add('active');
        }
    }

    function render() {
        perPage = parseInt(entriesSelect.value, 10) || 10;

        const keyword = searchInput.value.trim().toLowerCase();

        filteredRows = allRows.filter(function (row) {
            return rowMatchesFilters(row, keyword);
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
            emptyRow.className = 'branch-empty-row';

            const emptyCell = document.createElement('td');
            emptyCell.colSpan = table.querySelectorAll('thead th').length;
            emptyCell.className = 'admin-booking-empty';
            emptyCell.innerHTML = emptyMarkup('Ubah keyword pencarian atau tab status branch.');

            emptyRow.appendChild(emptyCell);
            tbody.appendChild(emptyRow);
            renderMobileCards([]);
        } else {
            const visibleRows = filteredRows.slice(startIndex, endIndex);

            visibleRows.forEach(function (row) {
                tbody.appendChild(row);
            });

            renderMobileCards(visibleRows);
        }

        const first = totalRows === 0 ? 0 : startIndex + 1;
        const last = Math.min(endIndex, totalRows);

        renderInfo(first, last, totalRows);

        renderPagination(totalPages);
        syncStatusTabs();
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

    if (mobileSearchSubmit) {
        mobileSearchSubmit.addEventListener('click', function () {
            currentPage = 1;
            render();
        });
    }

    entriesSelect.addEventListener('change', function () {
        currentPage = 1;
        render();
    });

    statusTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            currentStatus = tab.dataset.branchStatusFilter || 'all';
            currentPage = 1;
            render();
        });
    });

    if (resetButton) {
        resetButton.addEventListener('click', function () {
            searchInput.value = '';
            entriesSelect.value = '10';
            currentStatus = 'all';
            currentPage = 1;
            render();
        });
    }

    table.querySelectorAll('thead th[data-sort]').forEach(function (th, index) {
        th.addEventListener('click', function () {
            const type = th.dataset.sort;
            const nextAsc = sortState.index === index ? !sortState.asc : true;

            sortState = {
                index: index,
                asc: nextAsc,
            };

            allRows.sort(function (a, b) {
                const aText = a.children[index] ? a.children[index].innerText.trim().toLowerCase() : '';
                const bText = b.children[index] ? b.children[index].innerText.trim().toLowerCase() : '';

                if (type === 'number') {
                    const aNumber = parseFloat((aText.match(/-?\d+(\.\d+)?/) || ['0'])[0]);
                    const bNumber = parseFloat((bText.match(/-?\d+(\.\d+)?/) || ['0'])[0]);

                    return nextAsc
                        ? aNumber - bNumber
                        : bNumber - aNumber;
                }

                return nextAsc
                    ? aText.localeCompare(bText)
                    : bText.localeCompare(aText);
            });

            currentPage = 1;
            syncSortIcons(th, nextAsc);
            render();
        });
    });

    render();
}

function initBranchDeleteModal() {
    const modal = document.getElementById('branchDeleteModal') || document.getElementById('deleteBranchModal');
    const confirmForm = document.getElementById('branchDeleteConfirmForm') || document.getElementById('deleteBranchForm');
    const cancelButton = document.getElementById('branchDeleteCancel');

    if (!modal || !confirmForm) {
        return;
    }

    document.querySelectorAll('.branch-delete-trigger, .branch-delete-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const deleteUrl = this.dataset.deleteUrl;
            const form = this.closest('.branch-delete-form');

            if (deleteUrl) {
                confirmForm.action = deleteUrl;
            } else if (form) {
                confirmForm.action = form.action;
            } else {
                return;
            }

            modal.classList.add('active');
        });
    });

    if (cancelButton) {
        cancelButton.addEventListener('click', function () {
            modal.classList.remove('active');
        });
    }

    document.querySelectorAll('[data-close-branch-delete]').forEach(function (button) {
        button.addEventListener('click', function () {
            modal.classList.remove('active');
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.remove('active');
        }
    });
}
