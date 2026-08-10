(function() {
    initServiceCategoryHierarchy();
    initServiceAccordions();
    initAdditionalServices();
    initServiceHolidays();
    initServiceSlots();
    initGalleryPreview();
    initServiceDeleteModal();
    initAccordionAutoRefresh();
})();

function initServiceCategoryHierarchy() {
    const groupSelect = document.getElementById('serviceCategoryGroupSelect');
    const categorySelect = document.getElementById('serviceCategorySelect');
    const categoryName = document.getElementById('serviceCategoryName');

    if (!groupSelect || !categorySelect || !categoryName) return;

    const categoryOptions = Array.from(categorySelect.querySelectorAll('option[data-parent-id]'));
    const placeholder = categorySelect.querySelector('option:not([data-parent-id])');

    function syncOptions(preserveSelection) {
        const selectedGroup = groupSelect.value;
        const previousValue = preserveSelection ? categorySelect.value : '';

        categoryOptions.forEach(function (option) {
            const visible = Boolean(selectedGroup) && option.dataset.parentId === selectedGroup;
            option.hidden = !visible;
            option.disabled = !visible;
        });

        const previousOption = categoryOptions.find(function (option) {
            return option.value === previousValue && !option.disabled;
        });

        categorySelect.value = previousOption ? previousValue : '';
        categorySelect.disabled = !selectedGroup;
        placeholder.textContent = selectedGroup ? 'Select Service Type' : 'Select Main Category First';
        categoryName.value = categorySelect.selectedOptions[0]?.dataset.categoryName || '';
    }

    groupSelect.addEventListener('change', function () {
        syncOptions(false);
    });

    categorySelect.addEventListener('change', function () {
        categoryName.value = categorySelect.selectedOptions[0]?.dataset.categoryName || '';
    });

    syncOptions(true);
}

/* =========================================================
   ACCORDION
========================================================= */
function initServiceAccordions() {
    const accordions = document.querySelectorAll('.service-accordion');

    accordions.forEach(function (accordion) {
        const header = accordion.querySelector('.service-accordion-header');
        const body = accordion.querySelector('.service-accordion-body');

        if (!header || !body) {
            return;
        }

        ensureAccordionArrow(header);

        if (header.tagName.toLowerCase() !== 'button') {
            header.setAttribute('role', 'button');
            header.setAttribute('tabindex', '0');
        }

        header.setAttribute(
            'aria-expanded',
            accordion.classList.contains('active') ? 'true' : 'false'
        );

        if (accordion.classList.contains('active')) {
            body.style.opacity = '1';
            body.style.maxHeight = body.scrollHeight + 'px';
            queueAccordionRefresh(accordion);
        } else {
            body.style.opacity = '0';
            body.style.maxHeight = '0px';
        }

        header.addEventListener('click', function () {
            toggleAccordion(accordion);
        });

        header.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleAccordion(accordion);
            }
        });

        setupAccordionResizeObserver(accordion);
    });

    window.addEventListener('resize', function () {
        accordions.forEach(function (accordion) {
            refreshAccordionHeight(accordion);
        });
    });
}

function ensureAccordionArrow(header) {
    let arrow = header.querySelector('.accordion-arrow');

    if (!arrow) {
        arrow = document.createElement('span');
        arrow.className = 'accordion-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        header.appendChild(arrow);
    }
}

function toggleAccordion(accordion) {
    if (accordion.classList.contains('is-animating')) {
        return;
    }

    if (accordion.classList.contains('active')) {
        closeAccordion(accordion);
    } else {
        openAccordion(accordion);
    }
}

function openAccordion(accordion) {
    const header = accordion.querySelector('.service-accordion-header');
    const body = accordion.querySelector('.service-accordion-body');

    if (!body) {
        return;
    }

    accordion.classList.add('is-animating');
    accordion.classList.add('active');

    if (header) {
        header.setAttribute('aria-expanded', 'true');
    }

    body.style.overflow = 'hidden';
    body.style.opacity = '1';
    body.style.maxHeight = '0px';

    requestAnimationFrame(function () {
        body.style.maxHeight = body.scrollHeight + 'px';
    });

    queueAccordionRefresh(accordion);

    setTimeout(function () {
        accordion.classList.remove('is-animating');
        refreshAccordionHeight(accordion);
    }, 420);
}

function closeAccordion(accordion) {
    const header = accordion.querySelector('.service-accordion-header');
    const body = accordion.querySelector('.service-accordion-body');

    if (!body) {
        return;
    }

    accordion.classList.add('is-animating');

    body.style.overflow = 'hidden';
    body.style.maxHeight = body.scrollHeight + 'px';

    requestAnimationFrame(function () {
        accordion.classList.remove('active');

        if (header) {
            header.setAttribute('aria-expanded', 'false');
        }

        body.style.maxHeight = '0px';
        body.style.opacity = '0';
    });

    setTimeout(function () {
        accordion.classList.remove('is-animating');
    }, 420);
}

function refreshAccordionHeight(accordion) {
    const body = accordion.querySelector('.service-accordion-body');

    if (!body) {
        return;
    }

    if (accordion.classList.contains('active')) {
        body.style.opacity = '1';
        body.style.overflow = 'hidden';
        body.style.maxHeight = body.scrollHeight + 'px';
    } else {
        body.style.opacity = '0';
        body.style.overflow = 'hidden';
        body.style.maxHeight = '0px';
    }
}

function queueAccordionRefresh(accordion) {
    const delays = [0, 60, 140, 240, 380, 520];

    delays.forEach(function (delay) {
        setTimeout(function () {
            refreshAccordionHeight(accordion);
        }, delay);
    });
}

function refreshActiveAccordionHeight(element) {
    if (!element) {
        return;
    }

    const accordion = element.closest('.service-accordion');

    if (!accordion || !accordion.classList.contains('active')) {
        return;
    }

    queueAccordionRefresh(accordion);
}

function setupAccordionResizeObserver(accordion) {
    const body = accordion.querySelector('.service-accordion-body');

    if (!body || typeof ResizeObserver === 'undefined') {
        return;
    }

    const observer = new ResizeObserver(function () {
        if (accordion.classList.contains('active')) {
            refreshAccordionHeight(accordion);
        }
    });

    observer.observe(body);
}

function initAccordionAutoRefresh() {
    document.addEventListener('input', function (event) {
        refreshActiveAccordionHeight(event.target);
    });

    document.addEventListener('change', function (event) {
        refreshActiveAccordionHeight(event.target);
    });

    document.addEventListener('click', function (event) {
        if (
            event.target.closest('.slot-add-btn') ||
            event.target.closest('.remove-slot-btn') ||
            event.target.closest('.remove-additional-btn') ||
            event.target.closest('#addAdditionalService') ||
            event.target.closest('#addServiceHoliday') ||
            event.target.closest('.remove-service-holiday')
        ) {
            const target = event.target.closest(
                '.service-accordion, .additional-service-row, .service-holiday-row, .slot-time-row'
            );

            if (target) {
                refreshActiveAccordionHeight(target);
            }
        }
    });

    document.querySelectorAll('textarea').forEach(function (textarea) {
        textarea.addEventListener('input', function () {
            refreshActiveAccordionHeight(textarea);
        });
    });

    document.querySelectorAll('img').forEach(function (img) {
        img.addEventListener('load', function () {
            refreshActiveAccordionHeight(img);
        });
    });
}

/* =========================================================
   ADDITIONAL SERVICES
========================================================= */
function initAdditionalServices() {
    const addButton = document.getElementById('addAdditionalService');
    const wrapper = document.getElementById('additionalServiceWrapper');

    if (!addButton || !wrapper) {
        return;
    }

    let additionalIndex = wrapper.querySelectorAll('.additional-service-row').length || 0;

    addButton.addEventListener('click', function () {
        const row = createAdditionalServiceRow(additionalIndex);

        wrapper.appendChild(row);
        additionalIndex++;

        requestAnimationFrame(function () {
            row.classList.remove('is-adding');
            refreshActiveAccordionHeight(wrapper);
        });
    });

    wrapper.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-additional-btn');

        if (!button) {
            return;
        }

        const row = button.closest('.additional-service-row');

        if (!row) {
            return;
        }

        const rows = wrapper.querySelectorAll('.additional-service-row');

        if (rows.length <= 1) {
            row.querySelectorAll('input').forEach(function (input) {
                input.value = '';
            });

            refreshActiveAccordionHeight(wrapper);
            return;
        }

        row.classList.add('is-removing');

        setTimeout(function () {
            row.remove();
            refreshActiveAccordionHeight(wrapper);
        }, 180);
    });
}

function createAdditionalServiceRow(index) {
    const row = document.createElement('div');
    row.className = 'additional-service-row is-adding';

    row.innerHTML = `
        <div class="additional-drag">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M8 6h.01M8 12h.01M8 18h.01M16 6h.01M16 12h.01M16 18h.01"/>
            </svg>
        </div>

        <div class="service-form-group">
            <label>Name <span>*</span></label>
            <input type="text" name="additional_services[${index}][name]" placeholder="Enter Service Name">
        </div>

        <div class="service-form-group">
            <label>Price <span>*</span></label>
            <input type="number" step="0.01" name="additional_services[${index}][price]" placeholder="Enter Service Price">
        </div>

        <div class="service-form-group">
            <label>Description <span>*</span></label>
            <input type="text" name="additional_services[${index}][description]" placeholder="Enter description">
        </div>

        <button type="button" class="remove-additional-btn" title="Remove">
            Remove
        </button>
    `;

    return row;
}

/* =========================================================
   HOLIDAYS
========================================================= */
function initServiceHolidays() {
    const addButton = document.getElementById('addServiceHoliday');
    const wrapper = document.getElementById('serviceHolidayWrapper');

    if (!addButton || !wrapper) {
        return;
    }

    let holidayIndex = wrapper.querySelectorAll('.service-holiday-row').length || 0;

    addButton.addEventListener('click', function () {
        const row = createHolidayRow(holidayIndex);

        wrapper.appendChild(row);
        holidayIndex++;

        requestAnimationFrame(function () {
            row.classList.remove('is-adding');
            refreshActiveAccordionHeight(wrapper);
        });
    });

    wrapper.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-service-holiday');

        if (!button) {
            return;
        }

        const row = button.closest('.service-holiday-row');

        if (!row) {
            return;
        }

        const rows = wrapper.querySelectorAll('.service-holiday-row');

        if (rows.length <= 1) {
            const dateInput = row.querySelector('input[type="date"]');
            const checkInput = row.querySelector('input[type="checkbox"]');

            if (dateInput) {
                dateInput.value = '';
            }

            if (checkInput) {
                checkInput.checked = false;
            }

            refreshActiveAccordionHeight(wrapper);
            return;
        }

        row.classList.add('is-removing');

        setTimeout(function () {
            row.remove();
            refreshActiveAccordionHeight(wrapper);
        }, 180);
    });
}

function createHolidayRow(index) {
    const row = document.createElement('div');
    row.className = 'service-holiday-row is-adding';

    row.innerHTML = `
        <input type="date" name="holidays[${index}][date]">

        <label class="full-day-check">
            <input type="checkbox" name="holidays[${index}][full_day]" value="1">
            Full Day
        </label>

        <button type="button" class="remove-service-holiday" title="Remove">
            Remove
        </button>
    `;

    return row;
}

/* =========================================================
   SLOTS
========================================================= */
function initServiceSlots() {
    document.querySelectorAll('.slot-add-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const day = button.dataset.day;

            if (!day) {
                return;
            }

            const checkbox = document.querySelector('.slot-day-check[data-day="' + day + '"]');

            if (checkbox) {
                checkbox.checked = true;
            }

            addSlotRow(day);
        });
    });

    document.querySelectorAll('.slot-day-check').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const day = checkbox.dataset.day;
            const wrapper = getSlotWrapper(day);

            if (!wrapper) {
                return;
            }

            if (checkbox.checked) {
                if (wrapper.children.length === 0) {
                    addSlotRow(day);
                }
            } else {
                wrapper.innerHTML = '';
                refreshActiveAccordionHeight(wrapper);
            }
        });
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-slot-btn');

        if (!button) {
            return;
        }

        const row = button.closest('.slot-time-row');
        const wrapper = row ? row.closest('.slot-time-wrapper') : null;

        if (!row) {
            return;
        }

        row.classList.add('is-removing');

        setTimeout(function () {
            row.remove();

            if (wrapper) {
                const day = wrapper.dataset.wrapper;
                const checkbox = document.querySelector('.slot-day-check[data-day="' + day + '"]');

                if (checkbox && wrapper.children.length === 0) {
                    checkbox.checked = false;
                }

                refreshActiveAccordionHeight(wrapper);
            }
        }, 180);
    });
}

function getSlotWrapper(day) {
    if (!day) {
        return null;
    }

    return document.querySelector('[data-wrapper="' + day + '"]');
}

function addSlotRow(day) {
    const wrapper = getSlotWrapper(day);

    if (!wrapper) {
        return;
    }

    const row = document.createElement('div');
    row.className = 'slot-time-row is-adding';

    row.innerHTML = `
        <input type="time" name="slots[${day}][][start]">
        <input type="time" name="slots[${day}][][end]">

        <button type="button" class="remove-slot-btn" title="Remove">
            Remove
        </button>
    `;

    wrapper.appendChild(row);

    requestAnimationFrame(function () {
        row.classList.remove('is-adding');
        refreshActiveAccordionHeight(wrapper);
    });
}

/* =========================================================
   GALLERY
========================================================= */
function initGalleryPreview() {
    const input = document.getElementById('galleryImageInput');
    const preview = document.getElementById('galleryImagePreview');
    const placeholder = document.getElementById('galleryImagePlaceholder');

    if (!input || !preview) {
        return;
    }

    input.addEventListener('change', function () {
        const file = input.files && input.files[0];

        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            input.value = '';
            alert('File harus berupa gambar.');
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            preview.src = event.target.result;
            preview.classList.remove('hidden');

            if (placeholder) {
                placeholder.classList.add('hidden');
            }

            refreshActiveAccordionHeight(preview);
        };

        reader.readAsDataURL(file);
    });
}

/* =========================================================
   DELETE MODAL
========================================================= */
function initServiceDeleteModal() {
    const modal = document.getElementById('serviceDeleteModal');
    const overlay = document.getElementById('serviceDeleteModalOverlay');
    const deleteForm = document.getElementById('serviceDeleteForm');
    const cancelButtons = document.querySelectorAll('[data-service-delete-cancel]');

    if (!overlay || !deleteForm) {
        return;
    }

    document.querySelectorAll('[data-service-delete-url]').forEach(function (button) {
        button.addEventListener('click', function () {
            const deleteUrl = button.dataset.serviceDeleteUrl;

            if (!deleteUrl) {
                return;
            }

            deleteForm.setAttribute('action', deleteUrl);
            overlay.classList.add('active', 'show');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');

            if (modal) {
                modal.classList.add('active');
            }
        });
    });

    cancelButtons.forEach(function (button) {
        button.addEventListener('click', closeServiceDeleteModal);
    });

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeServiceDeleteModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeServiceDeleteModal();
        }
    });

    function closeServiceDeleteModal() {
        overlay.classList.remove('active', 'show');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');

        if (modal) {
            modal.classList.remove('active');
        }

        deleteForm.removeAttribute('action');
    }

    deleteForm.addEventListener('submit', function () {
        const button = deleteForm.querySelector('.delete-confirm-btn, .service-modal-delete');

        if (button) {
            button.disabled = true;
            button.textContent = 'Deleting...';
        }
    });
}
