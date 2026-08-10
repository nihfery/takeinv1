@php
    $mode = $mode ?? (($coupon->exists ?? false) ? 'edit' : 'create');
    $selectedProductType = old('product_type', $coupon->product_type ?? 'all');
    $selectedCouponType = old('coupon_type', $coupon->coupon_type ?? 'percentage');
    $selectedCouponValue = old('coupon_value', $coupon->coupon_value);
    $selectedQuantity = old('quantity', $coupon->quantity);
    $selectedStatus = old('status', $coupon->status ?? 'active');
    $selectedStartDate = old('start_date', $coupon->start_date?->format('Y-m-d'));
    $selectedEndDate = old('end_date', $coupon->end_date?->format('Y-m-d'));

    $selectedProductIds = old('product_ids', $coupon->product_ids ?? []);
    $selectedProductIds = is_array($selectedProductIds) ? $selectedProductIds : [];

    $couponMasterData = [
        'service' => $services->map(function ($service) {
            return [
                'id' => $service->id,
                'label' => $service->title,
            ];
        })->values(),

        'category' => $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'label' => $category->name,
            ];
        })->values(),
    ];

    $scopePreviewLabel = match ($selectedProductType) {
        'service' => 'Selected Services',
        'category' => 'Selected Categories',
        default => 'All Services',
    };

    $couponTypePreviewLabel = match ($selectedCouponType) {
        'fixed' => 'Fixed Amount',
        default => 'Percentage',
    };

    $previewAmount = is_numeric($selectedCouponValue) ? (float) $selectedCouponValue : 0;
    $previewValue = $selectedCouponType === 'fixed'
        ? 'Rp ' . number_format($previewAmount, 0, ',', '.')
        : rtrim(rtrim(number_format($previewAmount, 2, '.', ''), '0'), '.') . '%';

    $previewCode = old('code', $coupon->code) ?: 'SALONHEMAT';
    $previewQuota = blank($selectedQuantity) ? 'Unlimited' : number_format((int) $selectedQuantity);
    $previewPeriod = ($selectedStartDate && $selectedEndDate)
        ? $selectedStartDate . ' to ' . $selectedEndDate
        : 'Set active period';
@endphp

<div class="admin-coupon-form-shell">
    <div class="admin-coupon-form-main">
        <div class="coupon-form-card">
            <div class="coupon-form-title">
                <span class="coupon-form-title-icon">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7Z"></path>
                        <path d="m9 15 6-6"></path>
                        <path d="M9.5 9.5h.01"></path>
                        <path d="M14.5 14.5h.01"></path>
                    </svg>
                </span>

                <div>
                    <strong>{{ $mode === 'edit' ? 'Coupon Detail' : 'New Coupon Detail' }}</strong>
                    <span>Code, service scope, discount, quota, and coupon validity period.</span>
                </div>
            </div>

            <div class="coupon-form-section">
                <div class="coupon-section-heading">
                    <span>01</span>
                    <div>
                        <strong>Basic Information</strong>
                        <small>Coupon identity and services eligible for the promotion.</small>
                    </div>
                </div>

                <div class="coupon-form-grid">
                    <div class="form-group">
                        <label for="couponCode">Coupon Code <span class="required">*</span></label>
                        <input id="couponCode"
                               type="text"
                               name="code"
                               value="{{ old('code', $coupon->code) }}"
                               placeholder="Example: SALONSAVE"
                               autocomplete="off"
                               data-coupon-preview-source="code"
                               required>
                        <small class="coupon-field-hint">Use a short code without spaces so customers can use it easily.</small>
                        @error('code')
                            <small class="coupon-field-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="productTypeSelect">Product Type <span class="required">*</span></label>
                        <select name="product_type" id="productTypeSelect" required>
                            <option value="all" {{ $selectedProductType === 'all' ? 'selected' : '' }}>All Services</option>
                            <option value="service" {{ $selectedProductType === 'service' ? 'selected' : '' }}>Selected Services</option>
                            <option value="category" {{ $selectedProductType === 'category' ? 'selected' : '' }}>Selected Categories</option>
                        </select>
                        <small class="coupon-field-hint">Choose All Services for a global promotion.</small>
                        @error('product_type')
                            <small class="coupon-field-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group coupon-product-field coupon-field-wide"
                         id="couponProductField"
                         data-selected='@json(array_map("intval", $selectedProductIds))'>
                        <label>
                            <span id="couponProductLabel">Category</span>
                            <span class="required">*</span>
                        </label>

                        <div class="coupon-multiselect" id="couponProductSelector">
                            <div class="coupon-multiselect-control" id="couponProductControl">
                                <div class="coupon-selected-tags" id="couponSelectedTags"></div>

                                <input type="text"
                                       id="couponProductSearch"
                                       placeholder="Select data"
                                       autocomplete="off">
                            </div>

                            <div class="coupon-multiselect-dropdown" id="couponProductDropdown"></div>
                        </div>

                        <div id="couponProductHiddenInputs"></div>
                        <small class="coupon-field-hint">Choose one or more items according to the coupon scope.</small>
                        @error('product_ids')
                            <small class="coupon-field-error">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="coupon-form-section">
                <div class="coupon-section-heading">
                    <span>02</span>
                    <div>
                        <strong>Discount & Quota</strong>
                        <small>Promotion value and total coupon usage limit.</small>
                    </div>
                </div>

                <div class="coupon-form-grid">
                    <div class="form-group">
                        <label for="couponTypeSelect">Coupon Type <span class="required">*</span></label>
                        <select name="coupon_type" id="couponTypeSelect" required>
                            <option value="percentage" {{ $selectedCouponType === 'percentage' ? 'selected' : '' }}>Percentage</option>
                            <option value="fixed" {{ $selectedCouponType === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                        </select>
                        <small class="coupon-field-hint">Percentage memakai %, Fixed Amount memakai rupiah.</small>
                        @error('coupon_type')
                            <small class="coupon-field-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="couponValue">Coupon Value <span class="required">*</span></label>

                        <div class="input-with-suffix">
                            <input id="couponValue"
                                   type="number"
                                   step="0.01"
                                   min="0"
                                   name="coupon_value"
                                   value="{{ $selectedCouponValue }}"
                                   placeholder="Enter coupon value"
                                   data-coupon-preview-source="value"
                                   required>

                            <span id="couponValueSuffix">%</span>
                        </div>
                        <small class="coupon-field-hint">Example: 10 for 10% or 25000 for Rp 25,000.</small>
                        @error('coupon_value')
                            <small class="coupon-field-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group coupon-field-wide">
                        <label for="couponQuantity">Quantity</label>
                        <input id="couponQuantity"
                               type="number"
                               min="1"
                               step="1"
                               name="quantity"
                               value="{{ $selectedQuantity }}"
                               placeholder="Unlimited"
                               inputmode="numeric"
                               data-coupon-preview-source="quantity">
                        <small class="coupon-field-hint">Leave blank if the coupon has no usage limit.</small>
                        @error('quantity')
                            <small class="coupon-field-error">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="coupon-form-section">
                <div class="coupon-section-heading">
                    <span>03</span>
                    <div>
                        <strong>Validity & Status</strong>
                        <small>Active period and coupon visibility in the system.</small>
                    </div>
                </div>

                <div class="coupon-form-grid">
                    <div class="form-group">
                        <label for="couponStartDate">Start Date <span class="required">*</span></label>
                        <input id="couponStartDate"
                               type="date"
                               name="start_date"
                               value="{{ $selectedStartDate }}"
                               data-coupon-preview-source="start_date"
                               required>
                        @error('start_date')
                            <small class="coupon-field-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="couponEndDate">End Date <span class="required">*</span></label>
                        <input id="couponEndDate"
                               type="date"
                               name="end_date"
                               value="{{ $selectedEndDate }}"
                               data-coupon-preview-source="end_date"
                               required>
                        @error('end_date')
                            <small class="coupon-field-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group coupon-field-wide">
                        <label>Status <span class="required">*</span></label>

                        <div class="coupon-status-row">
                            <span>
                                <strong>Active Coupon</strong>
                                <small>The coupon can be used while the dates are valid and quota is still available.</small>
                            </span>

                            <label class="coupon-status-toggle" for="couponStatusToggle" aria-label="Toggle coupon status">
                                <input type="hidden" name="status" value="inactive">
                                <input id="couponStatusToggle"
                                       type="checkbox"
                                       name="status"
                                       value="active"
                                       data-coupon-preview-source="status"
                                       {{ $selectedStatus === 'active' ? 'checked' : '' }}>
                                <span></span>
                            </label>
                        </div>
                        @error('status')
                            <small class="coupon-field-error">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <aside class="admin-coupon-form-side" aria-label="Coupon summary">
        <div class="coupon-summary-card">
            <div class="coupon-summary-head">
                <span class="coupon-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7Z"></path>
                        <path d="m9 15 6-6"></path>
                    </svg>
                </span>

                <div>
                    <small>Coupon Preview</small>
                    <strong data-coupon-preview="code">{{ $previewCode }}</strong>
                </div>
            </div>

            <div class="coupon-summary-value" data-coupon-preview="value">{{ $previewValue }}</div>

            <dl class="coupon-summary-list">
                <div>
                    <dt>Discount Type</dt>
                    <dd data-coupon-preview="type">{{ $couponTypePreviewLabel }}</dd>
                </div>

                <div>
                    <dt>Scope</dt>
                    <dd data-coupon-preview="scope">{{ $scopePreviewLabel }}</dd>
                </div>

                <div>
                    <dt>Selected Items</dt>
                    <dd data-coupon-preview="items">{{ $selectedProductType === 'all' ? 'All available' : number_format(count($selectedProductIds)) . ' selected' }}</dd>
                </div>

                <div>
                    <dt>Quantity</dt>
                    <dd data-coupon-preview="quantity">{{ $previewQuota }}</dd>
                </div>

                <div>
                    <dt>Active Period</dt>
                    <dd data-coupon-preview="period">{{ $previewPeriod }}</dd>
                </div>

                <div>
                    <dt>Status</dt>
                    <dd>
                        <span class="coupon-preview-status {{ $selectedStatus === 'active' ? 'active' : 'inactive' }}" data-coupon-preview="status">
                            {{ $selectedStatus === 'active' ? 'Active' : 'Inactive' }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="coupon-side-panel">
            <div class="coupon-side-panel-head">
                <strong>Master Data</strong>
                <span>{{ number_format($services->count()) }} services, {{ number_format($categories->count()) }} categories</span>
            </div>

            <div class="coupon-side-stat-grid">
                <div>
                    <strong>{{ number_format($services->count()) }}</strong>
                    <span>Services</span>
                </div>

                <div>
                    <strong>{{ number_format($categories->count()) }}</strong>
                    <span>Categories</span>
                </div>
            </div>
        </div>

        <div class="coupon-side-panel">
            <div class="coupon-side-panel-head">
                <strong>Coupon Checklist</strong>
                <span>Quick validation before saving</span>
            </div>

            <div class="coupon-checklist">
                <span>
                    <i></i>
                    Unique, easy-to-read code.
                </span>

                <span>
                    <i></i>
                    Scope sesuai campaign promo.
                </span>

                <span>
                    <i></i>
                    The end date cannot be earlier than the start date.
                </span>
            </div>
        </div>
    </aside>
</div>

<script type="application/json" id="couponMasterData">
    @json($couponMasterData)
</script>
