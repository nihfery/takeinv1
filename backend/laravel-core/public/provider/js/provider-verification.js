(function () {
    const page = document.querySelector('[data-verification-page]');

    if (!page || page.dataset.uploadStatusBound === 'true') {
        return;
    }

    page.dataset.uploadStatusBound = 'true';

    const form = page.querySelector('.verification-form');
    const completeness = page.querySelector('[data-verification-completeness]');
    const uploadBoxes = Array.from(page.querySelectorAll('[data-document-upload]'));

    const forceVerificationRepaint = (restoreScrollTop = null) => {
        const scrollArea = page.closest('.provider-content-area');

        if (!scrollArea) {
            return;
        }

        const currentScrollTop = Number.isFinite(restoreScrollTop)
            ? restoreScrollTop
            : scrollArea.scrollTop;
        scrollArea.classList.add('is-verification-repainting');
        void scrollArea.offsetHeight;

        window.requestAnimationFrame(() => {
            scrollArea.classList.remove('is-verification-repainting');
            scrollArea.scrollTop = currentScrollTop;
            void page.offsetHeight;
        });
    };

    const formatBytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '0 KB';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        const unitIndex = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / Math.pow(1024, unitIndex);

        return `${value >= 10 || unitIndex === 0 ? value.toFixed(0) : value.toFixed(1)} ${units[unitIndex]}`;
    };

    const updateCompleteness = () => {
        if (!completeness) {
            return;
        }

        const countNode = completeness.querySelector('[data-document-complete-count]');
        const titleNode = completeness.querySelector('[data-document-complete-title]');
        const messageNode = completeness.querySelector('[data-document-complete-message]');
        const selectedCount = uploadBoxes.filter((box) => box.dataset.hasSelectedFile === 'true').length;
        const availableCount = uploadBoxes.filter((box) => (
            box.dataset.hasSelectedFile === 'true' || box.dataset.hasStoredFile === 'true'
        )).length;

        completeness.classList.toggle('is-complete', availableCount === uploadBoxes.length);
        completeness.classList.toggle('has-new-files', selectedCount > 0);

        if (countNode) {
            countNode.textContent = `${availableCount}/${uploadBoxes.length}`;
        }

        if (selectedCount > 0) {
            if (titleNode) {
                titleNode.textContent = `${selectedCount} file baru siap diunggah`;
            }
            if (messageNode) {
                messageNode.textContent = availableCount === uploadBoxes.length
                    ? 'Semua dokumen siap, tetapi belum terkirim ke server. Klik tombol Kirim untuk diverifikasi.'
                    : `File belum terkirim ke server. Masih ada ${uploadBoxes.length - availableCount} dokumen yang belum dipilih.`;
            }
            return;
        }

        if (availableCount === uploadBoxes.length) {
            if (titleNode) {
                titleNode.textContent = 'Semua dokumen sudah tersimpan';
            }
            if (messageNode) {
                messageNode.textContent = 'Dokumen tersimpan di akun Anda. Pilih file baru hanya jika ingin menggantinya.';
            }
            return;
        }

        if (titleNode) {
            titleNode.textContent = `${availableCount} dari ${uploadBoxes.length} dokumen sudah tersimpan`;
        }
        if (messageNode) {
            messageNode.textContent = `Masih ada ${uploadBoxes.length - availableCount} dokumen yang belum diunggah.`;
        }
    };

    uploadBoxes.forEach((box) => {
        const input = box.querySelector('[data-document-input]');
        const field = box.closest('[data-document-field]') || box.closest('.verification-field');
        const status = field ? field.querySelector('[data-document-status]') : null;
        const action = box.querySelector('[data-document-action]');
        const fileName = box.querySelector('[data-document-file]');
        const feedback = field ? field.querySelector('[data-document-feedback]') : null;
        const hasStoredFile = box.dataset.hasStoredFile === 'true';
        const initialAction = action ? action.textContent.trim() : 'Pilih file';

        if (!input) {
            return;
        }

        box.dataset.hasSelectedFile = 'false';
        box.setAttribute('role', 'button');
        box.setAttribute('tabindex', '0');

        const openFileDialog = () => {
            if (input.dataset.fileDialogOpen === 'true') {
                return;
            }

            const originalParent = input.parentNode;
            const originalNextSibling = input.nextSibling;
            const scrollArea = page.closest('.provider-content-area');
            const scrollTopBeforeDialog = scrollArea ? scrollArea.scrollTop : null;
            let restored = false;

            if (!originalParent) {
                return;
            }

            input.dataset.fileDialogOpen = 'true';
            input.classList.add('verification-native-file-input');
            document.body.classList.add('verification-file-dialog-open');
            document.body.appendChild(input);

            // Commit the non-blurred, non-transformed state before Chromium
            // hands control to the native Windows file picker. Without this
            // synchronous layout flush, an old compositor layer can remain
            // visible and make the lower half of the scroll area look clipped.
            void document.body.offsetHeight;
            if (scrollArea) {
                void scrollArea.getBoundingClientRect();
            }

            const restoreInput = () => {
                if (restored) {
                    return;
                }

                restored = true;
                window.removeEventListener('focus', handleWindowFocus);
                input.removeEventListener('change', handleDialogChange);

                if (originalNextSibling && originalNextSibling.parentNode === originalParent) {
                    originalParent.insertBefore(input, originalNextSibling);
                } else {
                    originalParent.appendChild(input);
                }

                input.classList.remove('verification-native-file-input');
                delete input.dataset.fileDialogOpen;
                document.body.classList.remove('verification-file-dialog-open');

                window.requestAnimationFrame(() => {
                    forceVerificationRepaint(scrollTopBeforeDialog);
                });
            };

            const handleWindowFocus = () => {
                window.setTimeout(restoreInput, 120);
            };

            const handleDialogChange = () => {
                window.setTimeout(restoreInput, 0);
            };

            window.addEventListener('focus', handleWindowFocus, { once: true });
            input.addEventListener('change', handleDialogChange, { once: true });

            try {
                input.click();
            } catch (error) {
                restoreInput();
            }
        };

        box.addEventListener('click', (event) => {
            event.preventDefault();
            openFileDialog();
        });

        box.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            openFileDialog();
        });

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            const maxSize = Number(box.dataset.maxSize || 0);

            box.classList.remove('is-invalid', 'is-selected');
            field?.classList.remove('has-selected-document', 'has-invalid-document');
            status?.classList.remove('is-selected', 'is-invalid', 'is-stored', 'is-missing');
            box.dataset.hasSelectedFile = 'false';

            if (!file) {
                box.classList.toggle('has-file', hasStoredFile);
                field?.classList.toggle('has-stored-document', hasStoredFile);
                status?.classList.add(hasStoredFile ? 'is-stored' : 'is-missing');
                if (status) status.textContent = hasStoredFile ? 'Sudah diunggah' : 'Belum diunggah';
                if (action) action.textContent = initialAction;
                if (fileName) fileName.textContent = hasStoredFile ? 'File tersimpan di akun' : 'Belum ada file dipilih';
                if (feedback) feedback.textContent = '';
                updateCompleteness();
                forceVerificationRepaint();
                return;
            }

            if (maxSize > 0 && file.size > maxSize) {
                input.value = '';
                box.classList.add('is-invalid');
                field?.classList.add('has-invalid-document');
                status?.classList.add('is-invalid');
                if (status) status.textContent = 'File terlalu besar';
                if (action) action.textContent = 'Pilih file lain';
                if (fileName) fileName.textContent = `${file.name} • ${formatBytes(file.size)}`;
                if (feedback) feedback.textContent = `Ukuran maksimal ${formatBytes(maxSize)}.`;
                updateCompleteness();
                forceVerificationRepaint();
                return;
            }

            box.dataset.hasSelectedFile = 'true';
            box.classList.add('has-file', 'is-selected');
            field?.classList.add('has-selected-document');
            field?.classList.remove('has-stored-document');
            status?.classList.add('is-selected');
            if (status) status.textContent = hasStoredFile ? 'Pengganti siap' : 'Siap diunggah';
            if (action) action.textContent = 'Ganti file pilihan';
            if (fileName) fileName.textContent = `${file.name} • ${formatBytes(file.size)}`;
            if (feedback) feedback.textContent = 'File sudah dipilih, tetapi belum dikirim ke server.';
            updateCompleteness();
            forceVerificationRepaint();
        });
    });

    form?.addEventListener('submit', () => {
        const submitButton = form.querySelector('.verification-submit');
        const submitLabel = form.querySelector('[data-verification-submit-label]');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.classList.add('is-loading');
        }
        if (submitLabel) {
            submitLabel.textContent = 'Sedang mengunggah dokumen...';
        }
    });

    updateCompleteness();
})();
