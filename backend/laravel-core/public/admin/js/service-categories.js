document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('.admin-category-page');

    function getModals() {
        return document.querySelectorAll('.category-modal');
    }

    function closeAllModals() {
        getModals().forEach(function (modal) {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        });

        document.body.classList.remove('modal-open');
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);

        if (!modal) {
            return;
        }

        closeAllModals();
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        const focusTarget = modal.querySelector('input:not([type="hidden"]), textarea, select, button');

        if (focusTarget) {
            window.requestAnimationFrame(function () {
                focusTarget.focus({ preventScroll: true });
            });
        }
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.category-modal.show')) {
            document.body.classList.remove('modal-open');
        }
    }

    function generateSlug(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    }

    function formatFileSize(bytes) {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '';
        }

        if (bytes < 1024 * 1024) {
            return `${Math.ceil(bytes / 1024)} KB`;
        }

        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function resetFilePreview(input, uploadField) {
        const preview = uploadField.querySelector('.upload-preview');
        const meta = uploadField.querySelector('[data-upload-meta]');

        if (preview) {
            preview.removeAttribute('src');
        }

        if (meta) {
            meta.textContent = meta.dataset.defaultText || 'No file selected';
        }

        uploadField.classList.remove('has-preview');
        input.value = '';
    }

    function handleFilePreview(input) {
        const file = input.files && input.files[0];
        const uploadField = input.closest('.upload-field, .upload-box');

        if (!uploadField) {
            return;
        }

        const preview = uploadField.querySelector('.upload-preview');
        const meta = uploadField.querySelector('[data-upload-meta]');

        if (!file) {
            resetFilePreview(input, uploadField);
            return;
        }

        if (!preview) {
            return;
        }

        const isImage = file.type.startsWith('image/') || file.name.toLowerCase().endsWith('.svg');

        if (!isImage) {
            window.alert('The file must be an image.');
            resetFilePreview(input, uploadField);
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            window.alert('Maximum file size is 2MB.');
            resetFilePreview(input, uploadField);
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            preview.src = event.target.result;

            if (meta) {
                const label = input.dataset.uploadLabel || 'File';
                const size = formatFileSize(file.size);
                meta.textContent = `${label}: ${file.name}${size ? ` (${size})` : ''}`;
            }

            uploadField.classList.add('has-preview');
        };

        reader.readAsDataURL(file);
    }

    function syncMobileFilterToggle(form) {
        const toggle = form.querySelector('.admin-booking-mobile-filter-toggle');

        if (!toggle) {
            return;
        }

        const isExpanded = form.classList.contains('is-expanded');
        toggle.classList.toggle('active', isExpanded);
        toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    }

    document.addEventListener('click', function (event) {
        const openButton = event.target.closest('[data-modal-open]');
        const closeButton = event.target.closest('[data-modal-close]');
        const mobileFilterToggle = event.target.closest('.admin-booking-mobile-filter-toggle');

        if (openButton) {
            event.preventDefault();
            openModal(openButton.getAttribute('data-modal-open'));
            return;
        }

        if (closeButton) {
            event.preventDefault();
            closeModal(closeButton.closest('.category-modal'));
            return;
        }

        if (mobileFilterToggle && page && page.contains(mobileFilterToggle)) {
            const form = mobileFilterToggle.closest('.admin-booking-filter-panel');

            if (form) {
                event.preventDefault();
                form.classList.toggle('is-expanded');
                syncMobileFilterToggle(form);
            }
        }
    });

    getModals().forEach(function (modal) {
        modal.setAttribute('aria-hidden', modal.classList.contains('show') ? 'false' : 'true');

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });

    document.addEventListener('input', function (event) {
        const slugTarget = event.target.closest('[data-slug-target]');

        if (slugTarget) {
            slugTarget.dataset.slugTouched = 'true';
            return;
        }

        const source = event.target.closest('[data-slug-source]');

        if (!source) {
            return;
        }

        const form = source.closest('form');
        const target = form ? form.querySelector('[data-slug-target]') : null;

        if (!target || target.dataset.slugTouched === 'true') {
            return;
        }

        target.value = generateSlug(source.value);
    });

    document.addEventListener('change', function (event) {
        const input = event.target.closest('[data-file-input], [data-upload-input]');

        if (input) {
            handleFilePreview(input);
        }
    });

    document.querySelectorAll('.upload-field, .upload-box').forEach(function (uploadField) {
        const input = uploadField.querySelector('[data-file-input], [data-upload-input]');
        const meta = uploadField.querySelector('[data-upload-meta]');

        if (meta) {
            meta.dataset.defaultText = meta.textContent.trim();
        }

        if (!input) {
            return;
        }

        uploadField.addEventListener('dragover', function (event) {
            event.preventDefault();
            uploadField.classList.add('drag-over');
        });

        uploadField.addEventListener('dragleave', function () {
            uploadField.classList.remove('drag-over');
        });

        uploadField.addEventListener('drop', function (event) {
            event.preventDefault();
            uploadField.classList.remove('drag-over');

            if (!event.dataTransfer.files || event.dataTransfer.files.length === 0) {
                return;
            }

            input.files = event.dataTransfer.files;
            handleFilePreview(input);
        });
    });

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('.delete-category-form');

        if (!form) {
            return;
        }

        const button = form.querySelector('.delete-confirm-btn');

        if (button) {
            button.disabled = true;
            button.textContent = 'Deleting...';
        }
    });
});
