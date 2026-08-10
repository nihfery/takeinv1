document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.service-tab');
    const contents = document.querySelectorAll('.service-tab-content');

    const addAdditionalBtn = document.getElementById('addAdditionalService');
    const additionalServicesBody = document.getElementById('additionalServicesBody');

    const addHolidayBtn = document.getElementById('addHoliday');
    const holidayList = document.getElementById('holidayList');

    let additionalIndex = 1;
    let holidayIndex = 2;

    function openTab(targetId) {
        tabs.forEach(function (tab) {
            tab.classList.remove('active');
        });

        contents.forEach(function (content) {
            content.classList.remove('active');
        });

        const targetContent = document.getElementById(targetId);
        const targetTab = document.querySelector(`[data-tab-target="${targetId}"]`);

        if (targetContent) {
            targetContent.classList.add('active');
        }

        if (targetTab) {
            targetTab.classList.add('active');
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const targetId = tab.getAttribute('data-tab-target');

            if (targetId) {
                openTab(targetId);
            }
        });
    });

    document.querySelectorAll('[data-next-tab]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-next-tab');

            if (targetId) {
                openTab(targetId);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });

    document.querySelectorAll('[data-collapse]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-collapse');
            const target = document.getElementById(targetId);

            if (!target) {
                return;
            }

            target.classList.toggle('is-hidden');

            if (target.classList.contains('is-hidden')) {
                target.style.display = 'none';
                button.textContent = '›';
            } else {
                target.style.display = '';
                button.textContent = '⌄';
            }
        });
    });

    document.querySelectorAll('[data-add-time]').forEach(function (button) {
        button.addEventListener('click', function () {
            const day = button.getAttribute('data-add-time');
            const timeList = document.getElementById(`timeList-${day}`);

            if (!timeList) {
                return;
            }

            const row = document.createElement('div');
            row.className = 'time-row';
            row.innerHTML = `
                <input type="time" name="availability[${day}][start][]">
                <input type="time" name="availability[${day}][end][]">
                <button type="button" class="delete-time-btn">🗑</button>
            `;

            timeList.appendChild(row);
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('delete-time-btn')) {
            const row = event.target.closest('.time-row');

            if (row) {
                row.remove();
            }
        }

        if (event.target.classList.contains('delete-additional-btn')) {
            const row = event.target.closest('.additional-service-row');

            if (row) {
                row.remove();
            }
        }

        if (event.target.classList.contains('delete-holiday-btn')) {
            const row = event.target.closest('.holiday-row');

            if (row) {
                row.remove();
            }
        }
    });

    if (addAdditionalBtn && additionalServicesBody) {
        addAdditionalBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'additional-service-row';
            row.innerHTML = `
                <div class="upload-mini">▧</div>

                <div class="form-group">
                    <label>Name <span>*</span></label>
                    <input type="text" name="additional_services[${additionalIndex}][name]" placeholder="Enter Service Name">
                </div>

                <div class="form-group">
                    <label>price <span>*</span></label>
                    <input type="number" name="additional_services[${additionalIndex}][price]" placeholder="Enter Service Price">
                </div>

                <div class="form-group">
                    <label>Description <span>*</span></label>
                    <input type="text" name="additional_services[${additionalIndex}][description]" placeholder="Enter description">
                </div>

                <button type="button" class="delete-additional-btn">🗑</button>
            `;

            additionalServicesBody.insertBefore(row, addAdditionalBtn);
            additionalIndex++;
        });
    }

    if (addHolidayBtn && holidayList) {
        addHolidayBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'holiday-row';
            row.innerHTML = `
                <input type="date" name="holidays[${holidayIndex}][date]">

                <label class="full-day-check">
                    <input type="checkbox" name="holidays[${holidayIndex}][full_day]" value="1" checked>
                    <span>Full Day</span>
                </label>

                <button type="button" class="delete-holiday-btn">🗑</button>
            `;

            holidayList.appendChild(row);
            holidayIndex++;
        });
    }
});
