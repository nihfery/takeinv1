(function initScheduledWalkIn() {
    const form = document.querySelector('.provider-walkin-form[data-walkin-availability-url]');
    if (!form || form.dataset.walkinInitialized === 'true') return;
    form.dataset.walkinInitialized = 'true';

    const branchSelect = form.querySelector('select[name="branch_id"]');
    const staffSelect = form.querySelector('select[name="staff_id"]');
    const dateInput = form.querySelector('input[name="booking_date"]');
    const startTimeInput = form.querySelector('input[name="start_time"]');
    const serviceCards = Array.from(form.querySelectorAll('.provider-walkin-service-card'));
    const serviceCheckboxes = Array.from(form.querySelectorAll('input[name="service_ids[]"]'));
    const selectedCount = form.querySelector('.provider-walkin-service-section .admin-booking-status');
    const slotsContainer = form.querySelector('[data-walkin-time-slots]');
    const slotSummary = form.querySelector('[data-walkin-slot-summary]');
    const durationLabel = form.querySelector('[data-walkin-duration]');
    const submitButton = form.querySelector('[data-walkin-submit]');
    const csrfToken = form.querySelector('input[name="_token"]')?.value || '';
    const availabilityUrl = form.dataset.walkinAvailabilityUrl;

    if (!branchSelect || !staffSelect || !dateInput || !startTimeInput || !slotsContainer || !availabilityUrl) return;

    let activeRequest = null;
    let requestSequence = 0;
    let initialStaffId = form.dataset.initialStaff || '';
    let initialTime = (form.dataset.initialTime || '').slice(0, 5);

    const selectedServiceIds = () => serviceCheckboxes
        .filter((checkbox) => checkbox.checked && checkbox.closest('.provider-walkin-service-card')?.style.display !== 'none')
        .map((checkbox) => Number(checkbox.value));

    function setSlotMessage(message, state = 'empty') {
        slotsContainer.replaceChildren();
        const status = document.createElement('div');
        status.className = `provider-walkin-slot-${state}`;
        status.textContent = message;
        slotsContainer.appendChild(status);
    }

    function clearSelectedTime() {
        startTimeInput.value = '';
        slotsContainer.querySelectorAll('.provider-walkin-time-button').forEach((button) => {
            button.classList.remove('is-selected');
            button.setAttribute('aria-pressed', 'false');
        });
    }

    function updateSubmitState(isLoading = false) {
        const ready = Boolean(
            branchSelect.value
            && selectedServiceIds().length
            && dateInput.value
            && staffSelect.value
            && startTimeInput.value
        );
        submitButton.disabled = isLoading || !ready;
        submitButton.classList.toggle('is-loading', isLoading);
    }

    function updateSelectedCount() {
        const count = selectedServiceIds().length;
        if (selectedCount) selectedCount.textContent = `${count} selected`;
        serviceCards.forEach((card) => {
            card.classList.toggle('is-selected', Boolean(card.querySelector('input[type="checkbox"]')?.checked));
        });
    }

    function serviceMatchesBranch(card, branchId) {
        if (!branchId) return true;
        if (card.dataset.allBranches === 'true') return true;

        try {
            return JSON.parse(card.dataset.branchIds || '[]')
                .map(String)
                .includes(String(branchId));
        } catch (error) {
            return false;
        }
    }

    function filterServicesByBranch() {
        serviceCards.forEach((card) => {
            const matches = serviceMatchesBranch(card, branchSelect.value);
            card.hidden = !matches;
            card.style.display = matches ? '' : 'none';
            if (!matches) {
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (checkbox) checkbox.checked = false;
            }
        });
        updateSelectedCount();
    }

    function resetAvailability(message) {
        activeRequest?.abort();
        staffSelect.replaceChildren(new Option(message, ''));
        staffSelect.disabled = true;
        clearSelectedTime();
        setSlotMessage(message);
        if (slotSummary) slotSummary.textContent = message;
        if (durationLabel) durationLabel.textContent = 'Duration will be calculated automatically';
        updateSubmitState();
    }

    function renderStaff(payload, preferredStaffId = '') {
        const availableStaffIds = new Set(
            (payload.available_slots || []).map((slot) => String(slot.staff_id))
        );
        const staff = (payload.eligible_staff || []).filter((member) => availableStaffIds.has(String(member.id)));

        staffSelect.replaceChildren(new Option(
            staff.length ? 'Select available staff' : 'No staff has an available slot',
            ''
        ));

        staff.forEach((member) => {
            staffSelect.appendChild(new Option(member.name, String(member.id)));
        });

        staffSelect.disabled = staff.length === 0;
        if (preferredStaffId && staff.some((member) => String(member.id) === String(preferredStaffId))) {
            staffSelect.value = String(preferredStaffId);
        }

        return staff;
    }

    function renderSlots(payload, preferredTime = '') {
        const staffId = String(staffSelect.value || '');
        const slots = (payload.available_slots || [])
            .filter((slot) => !staffId || String(slot.staff_id) === staffId)
            .filter((slot, index, all) => all.findIndex((candidate) => candidate.time === slot.time) === index);

        slotsContainer.replaceChildren();
        clearSelectedTime();

        if (!staffId) {
            setSlotMessage('Choose one available staff member to display their time slots.');
            if (slotSummary) slotSummary.textContent = 'Staff selection is required.';
            updateSubmitState();
            return;
        }

        if (!slots.length) {
            setSlotMessage('No time slots are available for this staff member on the selected date.');
            if (slotSummary) slotSummary.textContent = 'Try another staff member or date.';
            updateSubmitState();
            return;
        }

        slots.forEach((slot) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'provider-walkin-time-button';
            button.dataset.time = String(slot.time).slice(0, 5);
            button.setAttribute('aria-pressed', 'false');

            const time = document.createElement('strong');
            time.textContent = String(slot.time).slice(0, 5);
            const end = document.createElement('small');
            end.textContent = `until ${String(slot.estimated_end_time || '').slice(0, 5)}`;
            button.append(time, end);

            button.addEventListener('click', () => {
                slotsContainer.querySelectorAll('.provider-walkin-time-button').forEach((candidate) => {
                    const selected = candidate === button;
                    candidate.classList.toggle('is-selected', selected);
                    candidate.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });
                startTimeInput.value = button.dataset.time;
                if (slotSummary) slotSummary.textContent = `${button.dataset.time} selected for ${staffSelect.selectedOptions[0]?.textContent || 'staff'}.`;
                updateSubmitState();
            });

            slotsContainer.appendChild(button);
        });

        if (slotSummary) slotSummary.textContent = `${slots.length} available slots. Choose one time.`;

        const matchingButton = Array.from(slotsContainer.querySelectorAll('.provider-walkin-time-button'))
            .find((button) => button.dataset.time === String(preferredTime).slice(0, 5));
        matchingButton?.click();
        updateSubmitState();
    }

    function firstValidationMessage(response) {
        const errors = response?.errors || {};
        const firstError = Object.values(errors).flat()[0];
        return firstError || response?.message || 'Availability could not be loaded. Please try again.';
    }

    async function loadAvailability({ includeStaff = false, preferredStaffId = '', preferredTime = '' } = {}) {
        const services = selectedServiceIds();
        if (!branchSelect.value || !services.length || !dateInput.value) {
            resetAvailability('Select a branch, at least one service, and an appointment date first.');
            return;
        }

        activeRequest?.abort();
        activeRequest = new AbortController();
        const currentSequence = ++requestSequence;
        const requestedStaffId = includeStaff ? (preferredStaffId || staffSelect.value) : '';

        clearSelectedTime();
        setSlotMessage('Checking staff schedules and existing bookings…', 'loading');
        if (slotSummary) slotSummary.textContent = 'Checking live availability…';
        updateSubmitState(true);

        try {
            const response = await fetch(availabilityUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    branch_id: Number(branchSelect.value),
                    service_ids: services,
                    booking_date: dateInput.value,
                    staff_id: requestedStaffId ? Number(requestedStaffId) : null,
                }),
                signal: activeRequest.signal,
            });
            const body = await response.json();
            if (!response.ok) throw body;
            if (currentSequence !== requestSequence) return;

            const payload = body.data || {};
            if (durationLabel) {
                durationLabel.textContent = `${Number(payload.estimated_duration || 0)} minutes total duration`;
            }

            if (!includeStaff) {
                const staff = renderStaff(payload, preferredStaffId);
                const chosenStaffId = staffSelect.value;
                if (chosenStaffId) {
                    await loadAvailability({ includeStaff: true, preferredStaffId: chosenStaffId, preferredTime });
                    return;
                }

                setSlotMessage(staff.length
                    ? 'Choose one available staff member to display their time slots.'
                    : 'No staff and time combination is available for this date.');
                if (slotSummary) slotSummary.textContent = staff.length
                    ? 'Staff selection is required.'
                    : 'Try another date or change the selected services.';
            } else {
                renderSlots(payload, preferredTime);
            }
        } catch (error) {
            if (error?.name === 'AbortError') return;
            const message = firstValidationMessage(error);
            setSlotMessage(message, 'error');
            if (slotSummary) slotSummary.textContent = 'Availability check failed.';
            if (!includeStaff) {
                staffSelect.replaceChildren(new Option('No available staff', ''));
                staffSelect.disabled = true;
            }
        } finally {
            if (currentSequence === requestSequence) updateSubmitState();
        }
    }

    branchSelect.addEventListener('change', () => {
        initialStaffId = '';
        initialTime = '';
        filterServicesByBranch();
        loadAvailability();
    });

    dateInput.addEventListener('change', () => {
        initialTime = '';
        loadAvailability();
    });

    staffSelect.addEventListener('change', () => {
        loadAvailability({ includeStaff: true, preferredStaffId: staffSelect.value });
    });

    serviceCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            initialTime = '';
            updateSelectedCount();
            loadAvailability();
        });
    });

    form.addEventListener('submit', (event) => {
        if (submitButton.disabled || !startTimeInput.value || !staffSelect.value) {
            event.preventDefault();
            if (slotSummary) slotSummary.textContent = 'Choose an available staff member and time before saving.';
            form.querySelector('.provider-walkin-schedule-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        submitButton.disabled = true;
        submitButton.classList.add('is-loading');
    });

    filterServicesByBranch();
    updateSelectedCount();
    loadAvailability({ preferredStaffId: initialStaffId, preferredTime: initialTime });
})();
