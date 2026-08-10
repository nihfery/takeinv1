@extends('provider.layouts.dashboard')

@section('title', ($mode ?? 'create') === 'edit' ? 'Edit Service - Provider Dashboard' : 'Add Service - Provider Dashboard')
@section('page_title', ($mode ?? 'create') === 'edit' ? 'Edit Service' : 'Add Service')
@section('page_subtitle', 'Complete service information, branch availability, and gallery media.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/my-service.css') }}">
@endpush

@section('content')
@php
    $mode = $mode ?? 'create';
    $service = $service ?? null;
    $step = request('step', $step ?? 'service');

    $draft = $draft ?? [];
    $branchDraft = $branchDraft ?? [];

    $categories = $categories ?? collect();
    $branches = $branches ?? collect();

    $isEdit = $mode === 'edit';

    $activeServiceTab = $step === 'service';
    $activeBranchTab = $step === 'branch';
    $activeGalleryTab = $step === 'gallery';

    $getValue = function ($field, $default = '') use ($draft, $service) {
        if (old($field) !== null) {
            return old($field);
        }

        if ($service && isset($service->{$field})) {
            return $service->{$field};
        }

        return $draft[$field] ?? $default;
    };

    $selectedCategoryId = (string) old('category_id', $getValue('category_id', ''));
    $selectedCategory = $categories
        ->flatMap(fn ($category) => $category->children ?? collect())
        ->first(fn ($category) => (string) $category->id === $selectedCategoryId);
    $selectedCategoryGroupId = (string) old('category_group_id', $selectedCategory?->parent_id ?? '');

    $normalizeJsonValue = function ($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    };

    $slots = old('slots', $getValue('slots', []));
    $additionalServices = old('additional_services', $getValue('additional_services', []));
    $holidays = old('holidays', $getValue('holidays', []));
    $selectedBranches = old('branch_ids', $branchDraft['branch_ids'] ?? $getValue('branch_ids', []));

    $slots = $normalizeJsonValue($slots);
    $additionalServices = $normalizeJsonValue($additionalServices);
    $holidays = $normalizeJsonValue($holidays);
    $selectedBranches = $normalizeJsonValue($selectedBranches);

    $slots = is_array($slots) ? $slots : [];
    $additionalServices = is_array($additionalServices) ? $additionalServices : [];
    $holidays = is_array($holidays) ? $holidays : [];
    $selectedBranches = is_array($selectedBranches) ? $selectedBranches : [];

    if (empty($additionalServices)) {
        $additionalServices = [
            [
                'name' => '',
                'price' => '',
                'description' => '',
            ],
        ];
    }

    if (empty($holidays)) {
        $holidays = [
            [
                'date' => '',
                'full_day' => 0,
            ],
        ];
    }

    $serviceTabUrl = $isEdit
        ? provider_route('provider.services.edit', $service->id)
        : provider_route('provider.services.create');

    $branchTabUrl = $isEdit
        ? provider_route('provider.services.edit', ['service' => $service->id, 'step' => 'branch'])
        : provider_route('provider.services.create', ['step' => 'branch']);

    $galleryTabUrl = $isEdit
        ? provider_route('provider.services.edit', ['service' => $service->id, 'step' => 'gallery'])
        : provider_route('provider.services.create', ['step' => 'gallery']);

    $serviceFormAction = $isEdit
        ? provider_route('provider.services.update', $service->id)
        : provider_route('provider.services.continue.information');

    $branchFormAction = $isEdit
        ? provider_route('provider.services.update.branch', $service->id)
        : provider_route('provider.services.continue.branch');

    $galleryFormAction = $isEdit
        ? provider_route('provider.services.update.gallery', $service->id)
        : provider_route('provider.services.store');

    $galleryImage = $getValue('gallery_image');
    $galleryImageUrl = null;

    if (!empty($galleryImage)) {
        $galleryImage = ltrim($galleryImage, '/');

        if (\Illuminate\Support\Str::startsWith($galleryImage, ['http://', 'https://'])) {
            $galleryImageUrl = $galleryImage;
        } elseif (\Illuminate\Support\Str::startsWith($galleryImage, 'storage/')) {
            $galleryImageUrl = asset($galleryImage);
        } else {
            $galleryImageUrl = asset('storage/' . $galleryImage);
        }
    }

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
@endphp

<section class="provider-service-create-page">
    <div class="service-create-header">
        <div>
            <h1>{{ $isEdit ? 'Edit Service' : 'Create Service' }}</h1>

            <div class="service-breadcrumb">
                <span>Dashboard</span>
                <span>›</span>
                <span>My Service</span>
                <span>›</span>
                <strong>{{ $isEdit ? 'Edit' : 'Create' }}</strong>
            </div>
        </div>

        <a href="{{ provider_route('provider.services.index') }}" class="service-header-back">
            <svg viewBox="0 0 24 24">
                <path d="m12 19-7-7 7-7"/>
                <path d="M19 12H5"/>
            </svg>
            Back
        </a>
    </div>

    <div class="service-tabs provider-service-form-tabs">
        <a href="{{ $serviceTabUrl }}" class="service-tab {{ $activeServiceTab ? 'active' : '' }}">
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 8h.01M11 12h1v5h1"/>
            </svg>
            Service Information
        </a>

        <a href="{{ $branchTabUrl }}"
           class="service-tab {{ $activeBranchTab ? 'active' : '' }} {{ !$isEdit && empty($draft) ? 'disabled' : '' }}">
            <svg viewBox="0 0 24 24">
                <path d="M4 21V5a2 2 0 0 1 2-2h10v18"/>
                <path d="M16 8h2a2 2 0 0 1 2 2v11"/>
                <path d="M8 7h4M8 11h4M8 15h4"/>
            </svg>
            Branch Information
        </a>

        <a href="{{ $galleryTabUrl }}"
           class="service-tab {{ $activeGalleryTab ? 'active' : '' }} {{ !$isEdit && (empty($draft) || empty($branchDraft)) ? 'disabled' : '' }}">
            <svg viewBox="0 0 24 24">
                <path d="M4 5h16v14H4z"/>
                <path d="m8 15 2.5-3 2 2.5L16 10l4 5"/>
                <circle cx="8" cy="8" r="1"/>
            </svg>
            Gallery
        </a>
    </div>

    @if (session('success'))
        <div class="service-alert success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="service-alert error">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="service-alert error">
            Some data is invalid. Please check the form again.
        </div>
    @endif

    @if ($activeServiceTab)
        <form action="{{ $serviceFormAction }}" method="POST">
            @csrf

            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="service-form-card">
                <div class="service-accordion active" data-setup-service-basic>
                    <button type="button" class="service-accordion-header" aria-expanded="true">
                        <span>Basic Information</span>
                        <span class="accordion-arrow" aria-hidden="true"></span>
                    </button>

                    <div class="service-accordion-body">
                        <div class="service-form-grid two">
                            <div class="service-form-group">
                                <label>Service Name <span>*</span></label>
                                <input type="text" name="title" placeholder="Enter Service Name" value="{{ $getValue('title') }}">
                                @error('title') <small>{{ $message }}</small> @enderror
                            </div>

                            <div class="service-form-group">
                                <label>Product Code</label>
                                <input type="text" name="code" placeholder="Enter Product Code" value="{{ $getValue('code') }}">
                                @error('code') <small>{{ $message }}</small> @enderror
                            </div>

                            <div class="service-form-group">
                                <label>Main Category <span>*</span></label>
                                <select name="category_group_id" id="serviceCategoryGroupSelect">
                                    <option value="">Select Main Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ $selectedCategoryGroupId === (string) $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="service-field-help">Example: Hair Salon, Nail, Beauty, or Wellness.</small>
                                @error('category_group_id') <small>{{ $message }}</small> @enderror
                            </div>

                            <div class="service-form-group">
                                <label>Service Type <span>*</span></label>
                                <select name="category_id" id="serviceCategorySelect">
                                    <option value="">Select Main Category First</option>
                                    @foreach ($categories as $category)
                                        @foreach ($category->children as $child)
                                            <option
                                                value="{{ $child->id }}"
                                                data-parent-id="{{ $category->id }}"
                                                data-category-name="{{ $child->name }}"
                                                {{ $selectedCategoryId === (string) $child->id ? 'selected' : '' }}
                                            >
                                                {{ $child->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                                <input type="hidden" name="category" id="serviceCategoryName" value="{{ $selectedCategory?->name ?? $getValue('category') }}">
                                <small class="service-field-help">Customers will find this service through this specific type.</small>
                                @error('category_id') <small>{{ $message }}</small> @enderror
                                @error('category') <small>{{ $message }}</small> @enderror
                            </div>

                        </div>

                        <div class="service-form-group full">
                            <div class="service-label-row">
                                <label>Description</label>

                                <button type="button" class="ai-btn">
                                    Generate AI Content
                                </button>
                            </div>

                            <textarea name="description" class="service-editor" placeholder="Enter Description">{{ $getValue('description') }}</textarea>
                            @error('description') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="service-form-group full">
                            <label>Includes</label>
                            <input type="text" name="includes" placeholder="Enter Includes" value="{{ $getValue('includes') }}">
                            @error('includes') <small>{{ $message }}</small> @enderror
                        </div>
                    </div>
                </div>

                <div class="service-accordion active" data-setup-service-pricing>
                    <button type="button" class="service-accordion-header" aria-expanded="true">
                        <span>Pricing</span>
                        <span class="accordion-arrow" aria-hidden="true"></span>
                    </button>

                    <div class="service-accordion-body">
                        <div class="service-form-grid pricing">
                            <div class="service-form-group">
                                <label>Price Type</label>

                                <select name="price_type">
                                    <option value="">Select Price Type</option>
                                    <option value="fixed" {{ $getValue('price_type') === 'fixed' ? 'selected' : '' }}>Fixed</option>
                                    <option value="hourly" {{ $getValue('price_type') === 'hourly' ? 'selected' : '' }}>Hourly</option>
                                </select>

                                @error('price_type') <small>{{ $message }}</small> @enderror
                            </div>

                            <div class="service-form-group">
                                <label>Price <span>*</span></label>
                                <input type="number" step="0.01" name="price" placeholder="Enter Service Price" value="{{ $getValue('price') }}">
                                @error('price') <small>{{ $message }}</small> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="service-accordion active" data-setup-service-slots>
                    <button type="button" class="service-accordion-header" aria-expanded="true">
                        <span>Add Service Slot</span>
                        <span class="accordion-arrow" aria-hidden="true"></span>
                    </button>

                    <div class="service-accordion-body">
                        <div class="service-slot-list">
                            @foreach ($days as $day)
                                @php
                                    $daySlots = $slots[$day] ?? [];
                                    $daySlots = is_array($daySlots) ? $daySlots : [];
                                    $dayChecked = !empty($daySlots);
                                @endphp

                                <div class="service-slot-day">
                                    <label>
                                        <input type="checkbox" class="slot-day-check" data-day="{{ $day }}" {{ $dayChecked ? 'checked' : '' }}>
                                        {{ $day }}
                                    </label>

                                    <button type="button" class="slot-add-btn" data-day="{{ $day }}">+</button>

                                    <div class="slot-time-wrapper" data-wrapper="{{ $day }}">
                                        @foreach ($daySlots as $slotIndex => $slot)
                                            <div class="slot-time-row">
                                                <input type="time" name="slots[{{ $day }}][{{ $slotIndex }}][start]" value="{{ $slot['start'] ?? '' }}">
                                                <input type="time" name="slots[{{ $day }}][{{ $slotIndex }}][end]" value="{{ $slot['end'] ?? '' }}">
                                                <button type="button" class="remove-slot-btn">Remove</button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="service-accordion active">
                    <button type="button" class="service-accordion-header" aria-expanded="true">
                        <span>Add Additional Services</span>
                        <span class="accordion-arrow" aria-hidden="true"></span>
                    </button>

                    <div class="service-accordion-body">
                        <div id="additionalServiceWrapper">
                            @foreach ($additionalServices as $index => $additional)
                                <div class="additional-service-row">
                                    <div class="additional-drag">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M8 6h.01M8 12h.01M8 18h.01M16 6h.01M16 12h.01M16 18h.01"/>
                                        </svg>
                                    </div>

                                    <div class="service-form-group">
                                        <label>Name <span>*</span></label>
                                        <input type="text" name="additional_services[{{ $index }}][name]" placeholder="Enter Service Name" value="{{ $additional['name'] ?? '' }}">
                                    </div>

                                    <div class="service-form-group">
                                        <label>Price <span>*</span></label>
                                        <input type="number" step="0.01" name="additional_services[{{ $index }}][price]" placeholder="Enter Service Price" value="{{ $additional['price'] ?? '' }}">
                                    </div>

                                    <div class="service-form-group">
                                        <label>Description <span>*</span></label>
                                        <input type="text" name="additional_services[{{ $index }}][description]" placeholder="Enter description" value="{{ $additional['description'] ?? '' }}">
                                    </div>

                                    <button type="button" class="remove-additional-btn">Remove</button>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="add-new-btn" id="addAdditionalService">
                            + Add New
                        </button>
                    </div>
                </div>

                <div class="service-accordion active">
                    <button type="button" class="service-accordion-header" aria-expanded="true">
                        <span>Holidays / Unavailability</span>
                        <span class="accordion-arrow" aria-hidden="true"></span>
                    </button>

                    <div class="service-accordion-body">
                        <button type="button" class="add-holiday-link" id="addServiceHoliday">
                            + Add Holiday
                        </button>

                        <div id="serviceHolidayWrapper">
                            @foreach ($holidays as $index => $holiday)
                                @php
                                    $holidayDate = is_array($holiday) ? ($holiday['date'] ?? '') : $holiday;
                                    $holidayFullDay = is_array($holiday) ? !empty($holiday['full_day']) : false;
                                @endphp

                                <div class="service-holiday-row">
                                    <input type="date" name="holidays[{{ $index }}][date]" value="{{ $holidayDate }}">

                                    <label class="full-day-check">
                                        <input type="checkbox" name="holidays[{{ $index }}][full_day]" value="1" {{ $holidayFullDay ? 'checked' : '' }}>
                                        Full Day
                                    </label>

                                    <button type="button" class="remove-service-holiday">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="service-form-actions" data-setup-service-continue>
                    <a href="{{ provider_route('provider.services.index') }}" class="service-back-btn">
                        Back
                    </a>

                    <button type="submit" class="service-submit-btn">
                        Continue
                    </button>
                </div>
            </div>
        </form>
    @endif

    @if ($activeBranchTab)
        <form action="{{ $branchFormAction }}" method="POST">
            @csrf

            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="service-form-card">
                <div class="service-card-header">
                    <h2>Select Branch</h2>
                </div>

                <div class="service-branch-list">
                    @forelse ($branches as $branch)
                        @php
                            $branchChecked = in_array((string) $branch->id, array_map('strval', $selectedBranches));
                        @endphp

                        <label class="service-branch-item">
                            <div class="branch-avatar">
                                @if ($branch->image)
                                    <img src="{{ asset('storage/' . $branch->image) }}" alt="{{ $branch->branch_name }}">
                                @else
                                    <span>{{ strtoupper(substr($branch->branch_name ?? 'B', 0, 1)) }}</span>
                                @endif
                            </div>

                            <div class="branch-info">
                                <strong>{{ $branch->branch_name }}</strong>

                                <p>
                                    {{ $branch->address }}
                                    {{ $branch->city_id ? ', ' . $branch->city_id : '' }}
                                    {{ $branch->state_id ? ', ' . $branch->state_id : '' }}
                                    {{ $branch->country_id ? ', ' . $branch->country_id : '' }}
                                    {{ $branch->zip_code ? ' - ' . $branch->zip_code : '' }}
                                </p>
                            </div>

                            <input
                                type="checkbox"
                                name="branch_ids[]"
                                value="{{ $branch->id }}"
                                {{ $branchChecked ? 'checked' : '' }}
                            >
                        </label>

                        @foreach ($branch->staffs ?? [] as $staff)
                            <div class="service-branch-staff">
                                <div class="staff-avatar">
                                    @if ($staff->image)
                                        <img src="{{ asset('storage/' . $staff->image) }}" alt="{{ $staff->full_name }}">
                                    @else
                                        <span>{{ strtoupper(substr($staff->full_name ?: 'S', 0, 1)) }}</span>
                                    @endif
                                </div>

                                <div>
                                    <strong>{{ $staff->full_name }}</strong>
                                    <p>{{ $staff->country_code }}{{ $staff->phone_number }}</p>
                                </div>
                            </div>
                        @endforeach
                    @empty
                        <div class="service-empty-branch">
                            No branch available
                        </div>
                    @endforelse
                </div>

                <div class="service-form-actions" data-setup-service-continue>
                    <a href="{{ $serviceTabUrl }}" class="service-back-btn">
                        Back
                    </a>

                    <button type="submit" class="service-submit-btn">
                        Continue
                    </button>
                </div>
            </div>
        </form>
    @endif

    @if ($activeGalleryTab)
        <form action="{{ $galleryFormAction }}" method="POST" enctype="multipart/form-data">
            @csrf

            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="service-form-card">
                <div class="service-card-header">
                    <h2>Add Photos</h2>
                </div>

                <div class="service-gallery-section">
                    <div class="service-form-group full">
                        <label>Add service photos</label>

                        <label for="galleryImageInput" class="service-gallery-upload">
                            @if ($galleryImageUrl)
                                <img src="{{ $galleryImageUrl }}" alt="Gallery Preview" id="galleryImagePreview">
                                <span id="galleryImagePlaceholder" class="service-gallery-upload-placeholder hidden">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 5h16v14H4z"/>
                                        <path d="m8 15 2.5-3 2 2.5L16 10l4 5"/>
                                        <circle cx="8" cy="8" r="1"/>
                                    </svg>
                                    Image
                                </span>
                            @else
                                <img src="" alt="Gallery Preview" id="galleryImagePreview" class="hidden">
                                <span id="galleryImagePlaceholder" class="service-gallery-upload-placeholder">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 5h16v14H4z"/>
                                        <path d="m8 15 2.5-3 2 2.5L16 10l4 5"/>
                                        <circle cx="8" cy="8" r="1"/>
                                    </svg>
                                    Image
                                </span>
                            @endif
                        </label>

                        <input type="file" name="gallery_image" id="galleryImageInput" accept="image/*" hidden>

                        @error('gallery_image')
                            <small>{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="service-form-group full">
                        <label>Video</label>
                        <input type="url" name="video_url" placeholder="Add video URL" value="{{ $getValue('video_url') }}">
                        @error('video_url') <small>{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="service-form-actions" data-setup-service-continue>
                    <a href="{{ $branchTabUrl }}" class="service-back-btn">
                        Back
                    </a>

                    <button type="submit" class="service-submit-btn">
                        {{ $isEdit ? 'Save Changes' : 'Save' }}
                    </button>
                </div>
            </div>
        </form>
    @endif
</section>

<script src="{{ asset('provider/js/my-service.js') }}"></script>
@endsection

