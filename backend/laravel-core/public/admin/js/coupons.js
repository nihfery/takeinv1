document.addEventListener('DOMContentLoaded', function () {
    const productTypeSelect = document.getElementById('productTypeSelect');
    const productField = document.getElementById('couponProductField');
    const productLabel = document.getElementById('couponProductLabel');
    const productSelector = document.getElementById('couponProductSelector');
    const productControl = document.getElementById('couponProductControl');
    const productSearch = document.getElementById('couponProductSearch');
    const productDropdown = document.getElementById('couponProductDropdown');
    const selectedTags = document.getElementById('couponSelectedTags');
    const hiddenInputs = document.getElementById('couponProductHiddenInputs');
    const masterDataScript = document.getElementById('couponMasterData');

    const couponTypeSelect = document.getElementById('couponTypeSelect');
    const couponValueSuffix = document.getElementById('couponValueSuffix');
    const couponValueInput = document.querySelector('[data-coupon-preview-source="value"]');
    const couponCodeInput = document.querySelector('[data-coupon-preview-source="code"]');
    const couponQuantityInput = document.querySelector('[data-coupon-preview-source="quantity"]');
    const couponStartDateInput = document.querySelector('[data-coupon-preview-source="start_date"]');
    const couponEndDateInput = document.querySelector('[data-coupon-preview-source="end_date"]');
    const couponStatusToggle = document.querySelector('[data-coupon-preview-source="status"]');

    const previewCode = document.querySelector('[data-coupon-preview="code"]');
    const previewValue = document.querySelector('[data-coupon-preview="value"]');
    const previewType = document.querySelector('[data-coupon-preview="type"]');
    const previewScope = document.querySelector('[data-coupon-preview="scope"]');
    const previewItems = document.querySelector('[data-coupon-preview="items"]');
    const previewQuantity = document.querySelector('[data-coupon-preview="quantity"]');
    const previewPeriod = document.querySelector('[data-coupon-preview="period"]');
    const previewStatus = document.querySelector('[data-coupon-preview="status"]');

    if (!productTypeSelect || !productField || !masterDataScript) {
        return;
    }

    const masterData = JSON.parse(masterDataScript.textContent || '{}');

    let selectedIds = [];

    try {
        selectedIds = JSON.parse(productField.getAttribute('data-selected') || '[]').map(Number);
    } catch (error) {
        selectedIds = [];
    }

    function formatNumber(value) {
        const number = Number(value || 0);

        if (!Number.isFinite(number)) {
            return '0';
        }

        return new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0,
        }).format(number);
    }

    function getCurrentType() {
        return productTypeSelect.value || 'all';
    }

    function getTypeLabel(type) {
        const labels = {
            service: 'Service',
            category: 'Category',
        };

        return labels[type] || '';
    }

    function getScopeLabel(type) {
        const labels = {
            all: 'All Services',
            service: 'Selected Services',
            category: 'Selected Categories',
        };

        return labels[type] || 'All Services';
    }

    function getCouponTypeLabel(type) {
        const labels = {
            percentage: 'Percentage',
            fixed: 'Fixed Amount',
        };

        return labels[type] || 'Percentage';
    }

    function getOptions(type) {
        return masterData[type] || [];
    }

    function isSelected(id) {
        return selectedIds.includes(Number(id));
    }

    function getDiscountPreview() {
        const value = couponValueInput ? couponValueInput.value : '';
        const type = couponTypeSelect ? couponTypeSelect.value : 'percentage';

        if (type === 'fixed') {
            return 'Rp ' + formatNumber(value);
        }

        const number = Number(value || 0);

        if (!Number.isFinite(number)) {
            return '0%';
        }

        return String(Number(number.toFixed(2))) + '%';
    }

    function updatePreview() {
        const type = getCurrentType();

        if (previewCode && couponCodeInput) {
            const code = couponCodeInput.value.trim();

            previewCode.textContent = code || 'SALONHEMAT';
        }

        if (previewValue) {
            previewValue.textContent = getDiscountPreview();
        }

        if (previewType && couponTypeSelect) {
            previewType.textContent = getCouponTypeLabel(couponTypeSelect.value);
        }

        if (previewScope) {
            previewScope.textContent = getScopeLabel(type);
        }

        if (previewItems) {
            previewItems.textContent = type === 'all' ? 'All available' : selectedIds.length + ' selected';
        }

        if (previewQuantity && couponQuantityInput) {
            const quantity = couponQuantityInput.value.trim();

            previewQuantity.textContent = quantity ? formatNumber(quantity) : 'Unlimited';
        }

        if (previewPeriod && couponStartDateInput && couponEndDateInput) {
            const startDate = couponStartDateInput.value;
            const endDate = couponEndDateInput.value;

            previewPeriod.textContent = startDate && endDate ? startDate + ' to ' + endDate : 'Set active period';
        }

        if (previewStatus && couponStatusToggle) {
            const isActive = couponStatusToggle.checked;

            previewStatus.textContent = isActive ? 'Active' : 'Inactive';
            previewStatus.classList.toggle('active', isActive);
            previewStatus.classList.toggle('inactive', !isActive);
        }
    }

    function render() {
        const type = getCurrentType();

        if (type === 'all') {
            productField.style.display = 'none';
            selectedIds = [];
            renderSelectedTags();
            renderHiddenInputs();
            renderDropdown();
            updatePreview();
            return;
        }

        productField.style.display = 'block';
        productLabel.textContent = getTypeLabel(type);

        const validIds = getOptions(type).map(function (item) {
            return Number(item.id);
        });

        selectedIds = selectedIds.filter(function (id) {
            return validIds.includes(Number(id));
        });

        productSearch.placeholder = 'Select ' + getTypeLabel(type);

        renderSelectedTags();
        renderHiddenInputs();
        renderDropdown();
        updatePreview();
    }

    function renderSelectedTags() {
        selectedTags.innerHTML = '';

        const type = getCurrentType();
        const options = getOptions(type);

        selectedIds.forEach(function (id) {
            const option = options.find(function (item) {
                return Number(item.id) === Number(id);
            });

            if (!option) {
                return;
            }

            const tag = document.createElement('span');
            tag.className = 'coupon-tag';

            const label = document.createElement('span');
            label.textContent = option.label;

            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.removeId = option.id;
            button.textContent = 'x';

            tag.appendChild(label);
            tag.appendChild(button);
            selectedTags.appendChild(tag);
        });

        selectedTags.querySelectorAll('[data-remove-id]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.stopPropagation();

                const id = Number(button.getAttribute('data-remove-id'));

                selectedIds = selectedIds.filter(function (selectedId) {
                    return Number(selectedId) !== id;
                });

                renderSelectedTags();
                renderHiddenInputs();
                renderDropdown();
                updatePreview();
            });
        });
    }

    function renderHiddenInputs() {
        hiddenInputs.innerHTML = '';

        selectedIds.forEach(function (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'product_ids[]';
            input.value = id;

            hiddenInputs.appendChild(input);
        });
    }

    function renderDropdown() {
        const type = getCurrentType();
        const options = getOptions(type);
        const keyword = productSearch.value.toLowerCase().trim();

        productDropdown.innerHTML = '';

        if (type === 'all') {
            return;
        }

        const filteredOptions = options.filter(function (item) {
            return item.label.toLowerCase().includes(keyword);
        });

        if (filteredOptions.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'coupon-option empty';
            empty.textContent = 'No data found';

            productDropdown.appendChild(empty);
            return;
        }

        filteredOptions.forEach(function (item) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'coupon-option';

            if (isSelected(item.id)) {
                option.classList.add('selected');
            }

            option.textContent = item.label;

            option.addEventListener('click', function () {
                const id = Number(item.id);

                if (isSelected(id)) {
                    selectedIds = selectedIds.filter(function (selectedId) {
                        return Number(selectedId) !== id;
                    });
                } else {
                    selectedIds.push(id);
                }

                productSearch.value = '';
                renderSelectedTags();
                renderHiddenInputs();
                renderDropdown();
                updatePreview();
                productSearch.focus();
            });

            productDropdown.appendChild(option);
        });
    }

    function openDropdown() {
        if (getCurrentType() === 'all') {
            return;
        }

        productSelector.classList.add('open');
        renderDropdown();
    }

    function closeDropdown() {
        productSelector.classList.remove('open');
    }

    productTypeSelect.addEventListener('change', function () {
        selectedIds = [];
        productSearch.value = '';
        render();
    });

    productControl.addEventListener('click', function () {
        productSearch.focus();
        openDropdown();
    });

    productSearch.addEventListener('focus', function () {
        openDropdown();
    });

    productSearch.addEventListener('input', function () {
        openDropdown();
        renderDropdown();
    });

    document.addEventListener('click', function (event) {
        if (!productSelector.contains(event.target)) {
            closeDropdown();
        }
    });

    function updateCouponSuffix() {
        if (!couponTypeSelect || !couponValueSuffix) {
            return;
        }

        if (couponTypeSelect.value === 'fixed') {
            couponValueSuffix.textContent = 'Rp';
        } else {
            couponValueSuffix.textContent = '%';
        }
    }

    if (couponTypeSelect) {
        couponTypeSelect.addEventListener('change', function () {
            updateCouponSuffix();
            updatePreview();
        });
        updateCouponSuffix();
    }

    [
        couponCodeInput,
        couponValueInput,
        couponQuantityInput,
        couponStartDateInput,
        couponEndDateInput,
    ].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });

    if (couponStatusToggle) {
        couponStatusToggle.addEventListener('change', updatePreview);
    }

    render();
});
