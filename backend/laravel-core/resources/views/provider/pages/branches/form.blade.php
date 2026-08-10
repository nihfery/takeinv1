@extends('provider.layouts.dashboard')

@section('title', ($mode ?? 'create') === 'edit' ? 'Edit Branch - Provider Dashboard' : 'Add Branch - Provider Dashboard')
@section('page_title', ($mode ?? 'create') === 'edit' ? 'Edit Branch' : 'Add Branch')
@section('page_subtitle', 'Complete branch details and choose staff for this branch.')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
    <link rel="stylesheet" href="{{ asset('provider/css/branch.css') }}">
    <style>
        .provider-branch-map-field .branch-map-wrap {
            position: relative;
        }
        .provider-branch-map-field .branch-map {
            height: 340px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--border-color, #e5e5e5);
            position: relative;
        }
        .provider-branch-map-field .branch-map-toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .provider-branch-map-field .branch-map-search-input {
            flex: 1 1 260px;
            min-width: 0;
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d9cfc2;
            border-radius: 999px;
            font-size: 13px;
            outline: none;
        }
        .provider-branch-map-field .branch-map-search-input:focus {
            border-color: #ff5c16;
        }
        .provider-branch-map-field .branch-map-search-field {
            position: relative;
            flex: 1 1 260px;
            min-width: 0;
        }
        .provider-branch-map-field .branch-map-suggestions {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 20;
            margin: 0;
            padding: 6px;
            list-style: none;
            background: #fff;
            border: 1px solid #ece4d9;
            border-radius: 14px;
            box-shadow: 0 18px 44px rgba(13, 13, 13, 0.16);
            max-height: 260px;
            overflow-y: auto;
        }
        .provider-branch-map-field .branch-map-suggestions[hidden] {
            display: none;
        }
        .provider-branch-map-field .branch-map-suggestions li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 9px 11px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 13px;
            color: #1a1718;
            line-height: 1.35;
        }
        .provider-branch-map-field .branch-map-suggestions li:hover,
        .provider-branch-map-field .branch-map-suggestions li.active {
            background: #f4eee7;
        }
        .provider-branch-map-field .branch-map-suggestions li svg {
            flex: 0 0 auto;
            margin-top: 2px;
            color: #ff5c16;
        }
        .provider-branch-map-field .branch-map-suggestion-empty {
            padding: 10px 11px;
            font-size: 12px;
            color: #8a8079;
        }
        .provider-branch-map-field .branch-map-search-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border: 0;
            border-radius: 999px;
            background: #1a1718;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            flex: 0 0 auto;
        }
        .provider-branch-map-field .branch-map-search-btn:hover {
            background: #322c2a;
        }
        .provider-branch-map-field .branch-map-search-btn:disabled {
            opacity: 0.6;
            cursor: default;
        }
        .provider-branch-map-field .maplibregl-marker { cursor: grab; }

        /* Custom zoom controls */
        .provider-branch-map-field .branch-map-zoom {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 3;
            display: flex;
            flex-direction: column;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 12px rgba(13, 13, 13, 0.12);
        }
        .provider-branch-map-field .branch-map-zoom button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 0;
            background: #fff;
            color: #1a1718;
            cursor: pointer;
        }
        .provider-branch-map-field .branch-map-zoom button:first-child {
            border-bottom: 1px solid #ececec;
        }
        .provider-branch-map-field .branch-map-zoom button:hover {
            background: #f4eee7;
        }

        /* Hide default zoom; keep attribution compact & subtle */
        .provider-branch-map-field .maplibregl-ctrl-bottom-right .maplibregl-ctrl-attrib,
        .provider-branch-map-field .maplibregl-ctrl-bottom-left .maplibregl-ctrl-attrib {
            font-size: 10px;
            background: rgba(255, 255, 255, 0.7);
        }

        /* Branch photo gallery (up to 5) */
        .branch-gallery {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .branch-gallery-item {
            position: relative;
            aspect-ratio: 4 / 3;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #ece4d9;
            background: #f4eee7;
        }
        .branch-gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .branch-gallery-remove {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            background: rgba(26, 23, 24, 0.78);
            color: #fff;
            cursor: pointer;
        }
        .branch-gallery-remove:hover { background: #e02424; }
        .branch-gallery-cover {
            position: absolute;
            left: 6px;
            bottom: 6px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #ff5c16;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .branch-gallery-add {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            aspect-ratio: 4 / 3;
            border: 1.5px dashed #d9cfc2;
            border-radius: 12px;
            color: #8a8079;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: border-color .15s, color .15s, background .15s;
        }
        .branch-gallery-add:hover {
            border-color: #ff5c16;
            color: #ff5c16;
            background: #fff7f2;
        }
        .branch-gallery-add.hidden { display: none; }

        .provider-branch-media-guide {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f9f5f0;
            border: 1px solid #ece4d9;
        }
        .provider-branch-media-guide strong {
            display: block;
            font-size: 12.5px;
            color: #1a1718;
            margin-bottom: 6px;
        }
        .provider-branch-media-guide ul {
            margin: 0;
            padding-left: 16px;
        }
        .provider-branch-media-guide li {
            font-size: 12px;
            color: #6c635c;
            line-height: 1.55;
        }
        .provider-branch-media-guide b { color: #1a1718; }
    </style>
@endpush

@section('content')
@php
    $mode = $mode ?? 'create';
    $branch = $branch ?? null;
    $step = $step ?? 'branch';
    $draft = $draft ?? [];
    $staffDraft = $staffDraft ?? [];

    $isEdit = $mode === 'edit';

    $activeBranchTab = true;
    $activeStaffTab = false;

    $getValue = function ($field, $default = '') use ($draft, $branch) {
        if (old($field) !== null) {
            return old($field);
        }

        if ($branch && isset($branch->{$field})) {
            return $branch->{$field};
        }

        return $draft[$field] ?? $default;
    };

    $workingDays = old('working_days', $getValue('working_days', []));
    $holidays = old('holidays', $getValue('holidays', []));

    $workingDays = is_array($workingDays) ? $workingDays : [];
    $holidays = is_array($holidays) ? $holidays : [];

    if (empty($holidays)) {
        $holidays = [''];
    }

    $selectedStaffs = [];

    $branchTabUrl = $isEdit
        ? provider_route('provider.branch.edit', $branch->id)
        : provider_route('provider.branch.create');

    $staffTabUrl = $isEdit
        ? provider_route('provider.branch.edit', ['branch' => $branch->id, 'step' => 'staff'])
        : provider_route('provider.branch.create', ['step' => 'staff']);

    $branchFormAction = $isEdit
        ? provider_route('provider.branch.update', $branch->id)
        : provider_route('provider.branch.store');

    $staffFormAction = $isEdit
        ? provider_route('provider.branch.staff.update', $branch->id)
        : provider_route('provider.branch.store');

    $resolveBranchImg = function ($path) {
        if (! $path) {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset(\Illuminate\Support\Str::startsWith($path, 'storage/') ? $path : 'storage/' . ltrim($path, '/'));
    };

    // Resolve the current gallery (up to 5). Priority: old input on validation error,
    // then the saved branch (edit), then the in-progress draft (create), then the
    // legacy single image as a one-item fallback.
    $branchImages = old('existing_images');

    if ($branchImages === null) {
        if ($branch && ! empty($branch->images)) {
            $branchImages = $branch->images;
        } elseif (! empty($draft['images'])) {
            $branchImages = $draft['images'];
        } elseif ($branch && $branch->image) {
            $branchImages = [$branch->image];
        } elseif (! empty($draft['image'])) {
            $branchImages = [$draft['image']];
        } else {
            $branchImages = [];
        }
    }

    $branchImages = array_values(array_filter(is_array($branchImages) ? $branchImages : []));
    $branchMaxImages = 5;

    $selectedCountry = $getValue('country_id', 'Indonesia');
    $selectedState = $getValue('state_id');
    $selectedCity = $getValue('city_id');
    $selectedPhoneCode = $getValue('phone_code', '+62');
    $selectedLatitude = $getValue('latitude');
    $selectedLongitude = $getValue('longitude');
    $workingDayOptions = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $selectedStaffCount = count($selectedStaffs);
    $availableStaffCount = isset($staffs) ? $staffs->count() : 0;
@endphp

<section class="admin-category-page admin-booking-page provider-branch-form-page provider-branch-editor-page">
    <div class="admin-booking-route admin-category-route provider-branch-form-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <a href="{{ provider_route('provider.branch.index') }}">Branch</a>
            <span>&rsaquo;</span>
            <strong>{{ $isEdit ? 'Edit' : 'Create' }}</strong>
        </div>

        <a href="{{ provider_route('provider.branch.index') }}" class="admin-category-add-button provider-branch-form-back">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m12 19-7-7 7-7"/>
                <path d="M19 12H5"/>
            </svg>
            Back to Branch
        </a>
    </div>

    <div class="provider-branch-form-heading">
        <div>
            <span>{{ $isEdit ? 'Branch editor' : 'New branch setup' }}</span>
            <h1>{{ $isEdit ? 'Edit Branch' : 'Create Branch' }}</h1>
            <p>Complete the branch details, operating schedule, and photos. Staff work locations are selected from the Add Staff form.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="admin-booking-alert success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="admin-booking-alert danger">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="admin-booking-alert danger">
            Some fields are invalid. Please check the form again.
        </div>
    @endif

    @if ($activeBranchTab)
        <form action="{{ $branchFormAction }}" method="POST" enctype="multipart/form-data" class="provider-branch-editor-form">
            @csrf

            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="admin-booking-card branch-form-card provider-branch-form-card">
                <div class="provider-branch-form-layout">
                    <div class="provider-branch-form-main">
                        <section class="provider-branch-form-section" data-setup-branch-basic>
                            <div class="provider-branch-section-head">
                                <span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 21V5a2 2 0 0 1 2-2h10v18"/>
                                        <path d="M16 8h2a2 2 0 0 1 2 2v11"/>
                                        <path d="M8 7h4M8 11h4M8 15h4"/>
                                    </svg>
                                </span>

                                <div>
                                    <h2>Branch Details</h2>
                                    <p>Branch name, contact details, and primary address.</p>
                                </div>
                            </div>

                            <div class="branch-form-grid two provider-branch-field-grid">
                                <div class="branch-form-group">
                                    <label>Branch Name <span>*</span></label>
                                    <input type="text" name="branch_name" placeholder="Enter branch name" value="{{ $getValue('branch_name') }}">
                                    @error('branch_name') <small>{{ $message }}</small> @enderror
                                </div>

                                <div class="branch-form-group">
                                    <label>Email <span>*</span></label>
                                    <input type="email" name="email" placeholder="branch@email.com" value="{{ $getValue('email') }}">
                                    @error('email') <small>{{ $message }}</small> @enderror
                                </div>

                                <div class="branch-form-group full">
                                    <label>Phone Number <span>*</span></label>

                                    <div class="branch-phone-row">
                                        <select
                                            name="phone_code"
                                            id="branchPhoneCodeSelect"
                                            data-selected="{{ $selectedPhoneCode }}"
                                        >
                                            <option value="">Loading codes...</option>
                                        </select>

                                        <input
                                            type="text"
                                            name="phone_number"
                                            placeholder="Enter phone number"
                                            value="{{ $getValue('phone_number') }}"
                                        >
                                    </div>

                                    @error('phone_code') <small>{{ $message }}</small> @enderror
                                    @error('phone_number') <small>{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </section>

                        <section class="provider-branch-form-section" data-setup-branch-location>
                            <div class="provider-branch-section-head">
                                <span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11z"/>
                                        <circle cx="12" cy="10" r="2.5"/>
                                    </svg>
                                </span>

                                <div>
                                    <h2>Location</h2>
                                    <p>Branch region and optional coordinates for the customer catalog.</p>
                                </div>
                            </div>

                            <div class="branch-form-grid three provider-branch-field-grid">
                                <div class="branch-form-group full">
                                    <label>Address <span>*</span></label>
                                    <input type="text" name="address" id="branchAddressInput" placeholder="Enter full address" value="{{ $getValue('address') }}">
                                    @error('address') <small>{{ $message }}</small> @enderror
                                </div>

                                <div class="branch-form-group">
                                    <label>Country <span>*</span></label>
                                    <input type="text" name="country_id" id="branchCountryInput" placeholder="Negara" value="{{ $selectedCountry }}">
                                    @error('country_id') <small>{{ $message }}</small> @enderror
                                </div>

                                <div class="branch-form-group">
                                    <label>State <span>*</span></label>
                                    <input type="text" name="state_id" id="branchStateInput" placeholder="Provinsi" value="{{ $selectedState }}">
                                    @error('state_id') <small>{{ $message }}</small> @enderror
                                </div>

                                <div class="branch-form-group">
                                    <label>City <span>*</span></label>
                                    <input type="text" name="city_id" id="branchCityInput" placeholder="Kota/Kabupaten" value="{{ $selectedCity }}">
                                    @error('city_id') <small>{{ $message }}</small> @enderror
                                </div>

                                <div class="branch-form-group">
                                    <label>ZIP Code <span>*</span></label>
                                    <input type="text" name="zip_code" id="branchZipInput" placeholder="Enter ZIP code" value="{{ $getValue('zip_code') }}">
                                    @error('zip_code') <small>{{ $message }}</small> @enderror
                                </div>

                                <input type="hidden" name="latitude" id="branchLatitudeInput" value="{{ $selectedLatitude }}">
                                <input type="hidden" name="longitude" id="branchLongitudeInput" value="{{ $selectedLongitude }}">

                                <div class="branch-form-group full branch-location-helper provider-branch-location-helper">
                                    <button type="button" id="branchUseCurrentLocation" class="branch-location-btn">
                                        Use My Current Position
                                    </button>
                                    <small>Pin lokasi di peta untuk mengisi alamat &amp; koordinat secara otomatis.</small>
                                </div>

                                <div class="branch-form-group full provider-branch-map-field">
                                    <label>Set Location on Map</label>
                                    <div class="branch-map-toolbar">
                                        <div class="branch-map-search-field">
                                            <input
                                                type="text"
                                                id="branchMapSearchInput"
                                                class="branch-map-search-input"
                                                placeholder="Ketik alamat atau nama tempat..."
                                                autocomplete="off"
                                            >
                                            <ul id="branchMapSuggestions" class="branch-map-suggestions" hidden></ul>
                                        </div>
                                        <button type="button" id="branchMapSearch" class="branch-map-search-btn">
                                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <circle cx="11" cy="11" r="7"/>
                                                <path d="m21 21-4.3-4.3"/>
                                            </svg>
                                            Search
                                        </button>
                                    </div>
                                    <div class="branch-map-wrap">
                                        <div id="branchLocationMap" class="branch-map"></div>
                                        <div class="branch-map-zoom">
                                            <button type="button" id="branchZoomIn" aria-label="Perbesar">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                            </button>
                                            <button type="button" id="branchZoomOut" aria-label="Perkecil">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <small>Klik peta atau geser pin untuk menandai lokasi cabang secara akurat. Titik inilah yang dilihat customer.</small>
                                </div>
                            </div>
                        </section>

                        <section class="provider-branch-form-section" data-setup-branch-schedule>
                            <div class="provider-branch-section-head">
                                <span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="M12 7v5l3 2"/>
                                    </svg>
                                </span>

                                <div>
                                    <h2>Operational Schedule</h2>
                                    <p>Opening hours, working days, and branch holidays.</p>
                                </div>
                            </div>

                            <div class="branch-form-grid two provider-branch-field-grid">
                                <div class="branch-form-group">
                                    <label>Working Start Hour <span>*</span></label>
                                    <input type="time" name="working_start_hour" value="{{ $getValue('working_start_hour') }}">
                                    @error('working_start_hour') <small>{{ $message }}</small> @enderror
                                </div>

                                <div class="branch-form-group">
                                    <label>Working End Hour <span>*</span></label>
                                    <input type="time" name="working_end_hour" value="{{ $getValue('working_end_hour') }}">
                                    @error('working_end_hour') <small>{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="branch-form-group full provider-branch-days-field">
                                <label>Working Day <span>*</span></label>

                                <div class="branch-days-list provider-branch-day-grid">
                                    @foreach ($workingDayOptions as $day)
                                        <label>
                                            <input type="checkbox" name="working_days[]" value="{{ $day }}" {{ in_array($day, $workingDays) ? 'checked' : '' }}>
                                            {{ $day }}
                                        </label>
                                    @endforeach
                                </div>

                                @error('working_days') <small>{{ $message }}</small> @enderror
                            </div>

                            <div class="branch-form-group full provider-branch-holiday-field">
                                <label>Holiday</label>

                                <div id="holidayWrapper" class="provider-branch-holiday-list">
                                    @foreach ($holidays as $holiday)
                                        <div class="branch-holiday-row">
                                            <input type="date" name="holidays[]" value="{{ is_array($holiday) ? ($holiday['date'] ?? '') : $holiday }}">
                                            <button type="button" class="remove-holiday-btn" aria-label="Remove holiday" title="Remove holiday">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M18 6 6 18"/>
                                                    <path d="m6 6 12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>

                                <button type="button" class="branch-add-holiday" id="addHolidayBtn">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 5v14"/>
                                        <path d="M5 12h14"/>
                                    </svg>
                                    <span>Add Holiday</span>
                                </button>
                            </div>
                        </section>
                    </div>

                    <aside class="provider-branch-form-aside">
                        <section class="provider-branch-form-section provider-branch-media-section">
                            <div class="provider-branch-section-head compact">
                                <span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="4" y="5" width="16" height="14" rx="2"/>
                                        <path d="m8 15 2.5-3 2 2.5L16 10l4 5"/>
                                        <circle cx="8" cy="8" r="1"/>
                                    </svg>
                                </span>

                                <div>
                                    <h2>Branch Image</h2>
                                    <p>Up to 5 photos for the customer catalog.</p>
                                </div>
                            </div>

                            <div class="branch-form-group full provider-branch-upload-field">
                                <label>Photos <span>*</span> <small style="color:#8a8079;font-weight:500;">(max {{ $branchMaxImages }})</small></label>

                                <div
                                    id="branchGallery"
                                    class="branch-gallery"
                                    data-max="{{ $branchMaxImages }}"
                                    data-input-name="images[]"
                                    data-existing-name="existing_images[]"
                                >
                                    @foreach ($branchImages as $imgPath)
                                        <div class="branch-gallery-item" data-existing="1">
                                            <img src="{{ $resolveBranchImg($imgPath) }}" alt="Branch photo">
                                            <input type="hidden" name="existing_images[]" value="{{ $imgPath }}">
                                            <button type="button" class="branch-gallery-remove" aria-label="Remove photo">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                            </button>
                                            @if ($loop->first)
                                                <span class="branch-gallery-cover">Cover</span>
                                            @endif
                                        </div>
                                    @endforeach

                                    <label for="branchImageInput" class="branch-gallery-add" id="branchGalleryAdd">
                                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                        <span>Add Photo</span>
                                    </label>
                                </div>

                                <input type="file" name="images[]" id="branchImageInput" accept="image/jpeg,image/png,image/webp" multiple hidden>

                                @error('images') <small>{{ $message }}</small> @enderror
                                @error('images.*') <small>{{ $message }}</small> @enderror
                                @error('existing_images') <small>{{ $message }}</small> @enderror
                            </div>

                            <div class="provider-branch-media-guide">
                                <strong>Panduan foto agar tampil rapi di halaman customer</strong>
                                <ul>
                                    <li>Rasio <b>4:3 (landscape)</b> — foto pertama jadi cover di kartu pencarian.</li>
                                    <li>Resolusi disarankan <b>1200 × 900 px</b> (min. 800 × 600 px).</li>
                                    <li>Format <b>JPG, PNG, atau WEBP</b>, ukuran maksimal <b>2 MB</b> per foto.</li>
                                    <li>Gunakan foto terang, fokus pada interior/fasad salon, hindari teks/watermark.</li>
                                    <li>Maksimal <b>5 foto</b>; urutan bisa diatur dengan menghapus lalu menambah ulang.</li>
                                </ul>
                            </div>
                        </section>
                    </aside>
                </div>

                <div class="branch-form-actions" data-setup-branch-save>
                    <a href="{{ provider_route('provider.branch.index') }}" class="branch-back-btn">
                        Back
                    </a>

                    <button type="submit" class="branch-submit-btn">
                        {{ $isEdit ? 'Save Changes' : 'Save Branch' }}
                    </button>
                </div>
            </div>
        </form>
    @endif

    @if ($activeStaffTab)
        <form action="{{ $staffFormAction }}" method="POST" class="provider-branch-editor-form">
            @csrf

            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="admin-booking-card branch-form-card provider-branch-form-card provider-branch-staff-card">
                <section class="provider-branch-form-section" data-setup-branch-staff>
                    <div class="provider-branch-section-head">
                        <span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </span>

                        <div>
                            <h2>Add Staff</h2>
                            <p>Select the staff assigned to this branch.</p>
                        </div>
                    </div>

                    <div class="provider-branch-staff-overview">
                        <div>
                            <span>Available Staff</span>
                            <strong>{{ number_format($availableStaffCount) }}</strong>
                        </div>

                        <div>
                            <span>Selected</span>
                            <strong>{{ number_format($selectedStaffCount) }}</strong>
                        </div>
                    </div>

                    <div class="branch-form-group full provider-branch-staff-field">
                        <label>Staffs</label>

                        <div class="branch-staff-multiselect" id="branchStaffMultiselect">
                            <button type="button" class="branch-staff-control" id="branchStaffControl" tabindex="0">
                                <div class="branch-staff-tags" id="branchStaffTags">
                                    <span class="branch-staff-placeholder" id="branchStaffPlaceholder">Select Staff</span>
                                </div>

                                <span class="branch-staff-arrow" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="m6 9 6 6 6-6"/>
                                    </svg>
                                </span>
                            </button>

                            <div class="branch-staff-menu">
                                @forelse ($staffs as $staff)
                                    @php
                                        $staffId = (string) $staff->id;
                                        $staffName = $staff->full_name ?? trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
                                        $staffPhone = trim(($staff->country_code ?? '') . ($staff->phone_number ?? ''));
                                        $checkedStaff = in_array($staffId, $selectedStaffs);
                                    @endphp

                                    <label class="branch-staff-option">
                                        <input
                                            type="checkbox"
                                            name="staff_ids[]"
                                            value="{{ $staff->id }}"
                                            data-name="{{ $staffName }}"
                                            {{ $checkedStaff ? 'checked' : '' }}
                                        >

                                        <div class="branch-staff-avatar">
                                            @if (!empty($staff->image))
                                                <img src="{{ asset('storage/' . $staff->image) }}" alt="{{ $staffName }}">
                                            @else
                                                {{ strtoupper(substr($staffName ?: 'S', 0, 1)) }}
                                            @endif
                                        </div>

                                        <div>
                                            <strong>{{ $staffName }}</strong>
                                            <small>{{ $staffPhone ?: ($staff->email ?? 'No contact') }}</small>
                                        </div>
                                    </label>
                                @empty
                                    <div class="branch-staff-empty">
                                        No staff available
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        @error('staff_ids') <small>{{ $message }}</small> @enderror
                    </div>
                </section>

                <div class="branch-form-actions" data-setup-branch-save>
                    <a href="{{ $branchTabUrl }}" class="branch-back-btn">
                        Back
                    </a>

                    <button type="submit" class="branch-submit-btn">
                        Save
                    </button>
                </div>
            </div>
        </form>
    @endif
</section>

<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
    <script src="{{ asset('provider/js/branch.js') }}"></script>
@endsection


