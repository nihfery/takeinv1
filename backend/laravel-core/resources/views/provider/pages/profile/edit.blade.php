@extends('provider.layouts.dashboard')

@section('title', 'Edit Profile - Provider Dashboard')
@section('page_title', 'Edit Profile')
@section('page_subtitle', 'Edit profile provider.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/provider-profile.css') }}?v={{ filemtime(public_path('provider/css/provider-profile.css')) }}">
@endpush

@section('content')
@php
    $profileImage = $profile->image ? asset('storage/' . $profile->image) : null;
    $ktpImage = $ktpDocumentUrl ?? null;
    $nibDocument = $nibDocumentUrl ?? null;
    $businessImage = $profile->business_image ? asset('storage/' . $profile->business_image) : null;

    $accountStatus = $profile->status ?? 'active';
    $documentStatus = $profile->document_status ?? 'pending';
    $documentLocked = $documentStatus === 'verified';
@endphp

<section class="profile-page admin-category-page admin-booking-page provider-profile-page">
    <div class="admin-booking-route admin-category-route provider-profile-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <a href="{{ provider_route('provider.profile') }}">My Profile</a>
            <span>&rsaquo;</span>
            <strong>Edit</strong>
        </div>

        <a href="{{ provider_route('provider.profile') }}" class="admin-category-add-button secondary">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M19 12H5"></path>
                <path d="m12 19-7-7 7-7"></path>
            </svg>
            Back
        </a>
    </div>

    @if (session('success'))
        <div class="admin-booking-alert success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="admin-booking-alert danger">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="admin-booking-alert danger">
            Some data is invalid. Please check the form again.
        </div>
    @endif

    <div class="profile-edit-layout">
        <div class="profile-main">
            <form action="{{ provider_route('provider.profile.update') }}" method="POST" enctype="multipart/form-data" class="profile-card">
                @csrf
                @method('PUT')

                <div class="profile-card-title">
                    <div>
                        <h2>Profile Information</h2>
                        <p>Update the main provider account details.</p>
                    </div>
                </div>

                <div class="profile-upload-row">
                    <label for="profileImageInput" class="profile-upload-avatar">
                        @if ($profileImage)
                            <img src="{{ $profileImage }}" id="profileImagePreview" alt="Profile Image">
                            <span id="profileImagePlaceholder" class="hidden">Upload</span>
                        @else
                            <img src="" id="profileImagePreview" class="hidden" alt="Profile Image">
                            <span id="profileImagePlaceholder">Upload</span>
                        @endif
                    </label>

                    <input type="file" name="image" id="profileImageInput" accept="image/*" hidden>

                    <div>
                        <strong>Profile Photo</strong>
                        <p>JPG, PNG, or WEBP. Maximum 2 MB.</p>
                        @error('image') <small>{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="profile-form-grid">
                    <div class="profile-form-group">
                        <label>Name <span>*</span></label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" placeholder="Enter Name">
                        @error('name') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="profile-form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="{{ old('username', $user->username) }}" placeholder="Enter Username">
                        @error('username') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="profile-form-group">
                        <label>Email <span>*</span></label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" placeholder="Enter Email">
                        @error('email') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="profile-form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" value="{{ old('phone_number', $profile->phone_number) }}" placeholder="Enter Phone Number">
                        @error('phone_number') <small>{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="{{ provider_route('provider.profile') }}" class="profile-secondary-btn">
                        Cancel
                    </a>

                    <button type="submit" class="profile-primary-btn">
                        Save Profile
                    </button>
                </div>
            </form>

            @if ($canAccessSensitiveDocuments)
            <form action="{{ provider_route('provider.profile.documents.update') }}" method="POST" enctype="multipart/form-data" class="profile-card">
                @csrf

                <div class="profile-card-title">
                    <div>
                        <h2>Documents</h2>

                        @if ($documentLocked)
                            <p>Documents are already verified and can no longer be modified.</p>
                        @else
                            <p>Upload provider verification documents.</p>
                        @endif
                    </div>

                    <span class="status-badge {{ $documentStatus }}">
                        {{ ucfirst($documentStatus) }}
                    </span>
                </div>

                <div class="profile-document-grid">
                    <div class="profile-document-item">
                        <label
                            for="{{ $documentLocked ? '' : 'ktpImageInput' }}"
                            class="document-upload {{ $documentLocked ? 'locked' : '' }}"
                        >
                            @if ($ktpImage)
                                <img src="{{ $ktpImage }}" id="ktpImagePreview" alt="KTP Image">
                                <span id="ktpImagePlaceholder" class="hidden">
                                    {{ $documentLocked ? 'Verified' : 'Upload KTP' }}
                                </span>
                            @else
                                <img src="" id="ktpImagePreview" class="hidden" alt="KTP Image">
                                <span id="ktpImagePlaceholder">
                                    {{ $documentLocked ? 'Verified' : 'Upload KTP' }}
                                </span>
                            @endif

                            @if ($documentLocked)
                                <div class="document-locked-layer">
                                    Verified
                                </div>
                            @endif
                        </label>

                        <input
                            type="file"
                            name="ktp_image"
                            id="ktpImageInput"
                            accept="image/*"
                            {{ $documentLocked ? 'disabled' : '' }}
                            hidden
                        >

                        <h3>ID Card Photo</h3>
                        @error('ktp_image') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="profile-document-item">
                        <label
                            for="{{ $documentLocked ? '' : 'nibDocumentInput' }}"
                            class="document-upload document-file-upload {{ $documentLocked ? 'locked' : '' }}"
                        >
                            <span id="nibDocumentPlaceholder">
                                {{ $nibDocument ? 'Dokumen NIB tersimpan' : 'Upload dokumen NIB' }}
                            </span>

                            @if ($documentLocked)
                                <div class="document-locked-layer">Verified</div>
                            @endif
                        </label>

                        <input
                            type="file"
                            name="nib_document"
                            id="nibDocumentInput"
                            accept="application/pdf,image/jpeg,image/png,image/webp"
                            {{ $documentLocked ? 'disabled' : '' }}
                            hidden
                        >

                        <h3>Dokumen NIB</h3>
                        @if ($nibDocument)<a href="{{ $nibDocument }}" target="_blank" rel="noopener">Lihat dokumen</a>@endif
                        @error('nib_document') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="profile-document-item">
                        <label
                            for="{{ $documentLocked ? '' : 'businessImageInput' }}"
                            class="document-upload {{ $documentLocked ? 'locked' : '' }}"
                        >
                            @if ($businessImage)
                                <img src="{{ $businessImage }}" id="businessImagePreview" alt="Business Image">
                                <span id="businessImagePlaceholder" class="hidden">
                                    {{ $documentLocked ? 'Verified' : 'Upload Business' }}
                                </span>
                            @else
                                <img src="" id="businessImagePreview" class="hidden" alt="Business Image">
                                <span id="businessImagePlaceholder">
                                    {{ $documentLocked ? 'Verified' : 'Upload Business' }}
                                </span>
                            @endif

                            @if ($documentLocked)
                                <div class="document-locked-layer">
                                    Verified
                                </div>
                            @endif
                        </label>

                        <input
                            type="file"
                            name="business_image"
                            id="businessImageInput"
                            accept="image/*"
                            {{ $documentLocked ? 'disabled' : '' }}
                            hidden
                        >

                        <h3>Business Photo</h3>
                        @error('business_image') <small>{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="profile-form-group full" style="margin-top: 14px;">
                    <label>Nomor Induk Berusaha (NIB) <span>*</span></label>
                    <input type="text" name="nib_number" value="{{ old('nib_number', $profile->nib_number) }}" placeholder="Masukkan nomor NIB" {{ $documentLocked ? 'disabled' : 'required' }}>
                    @error('nib_number') <small>{{ $message }}</small> @enderror
                </div>

                @if ($documentLocked)
                    <div class="profile-help-box verified">
                        Documents have been <b>Verified</b> by admin. Provider cannot replace or re-upload these documents.
                    </div>
                @elseif ($documentStatus === 'submitted')
                    <div class="profile-help-box">
                        Documents have been <b>Submitted</b> and are waiting for admin verification.
                        If you re-upload documents, the status will remain <b>Submitted</b>.
                    </div>
                @elseif ($documentStatus === 'rejected')
                    <div class="profile-help-box rejected">
                        Previous documents were rejected. Please upload new documents.
                        After submission, the status will become <b>Submitted</b>.
                    </div>
                @else
                    <div class="profile-help-box">
                        After documents are submitted, the document status will become <b>Submitted</b>.
                    </div>
                @endif

                <div class="profile-actions">
                    @if ($documentLocked)
                        <button type="button" class="profile-primary-btn disabled" disabled>
                            Documents Verified
                        </button>
                    @else
                        <button type="submit" class="profile-primary-btn">
                            Submit Documents
                        </button>
                    @endif
                </div>
            </form>
            @endif

            <form action="{{ provider_route('provider.profile.password.update') }}" method="POST" class="profile-card">
                @csrf
                @method('PUT')

                <div class="profile-card-title">
                    <div>
                        <h2>Change Password</h2>
                        <p>Update the provider login password.</p>
                    </div>
                </div>

                <div class="profile-form-grid">
                    <div class="profile-form-group full">
                        <label>Current Password <span>*</span></label>
                        <input type="password" name="current_password" placeholder="Enter Current Password">
                        @error('current_password') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="profile-form-group">
                        <label>New Password <span>*</span></label>
                        <input type="password" name="password" placeholder="Enter New Password">
                        @error('password') <small>{{ $message }}</small> @enderror
                    </div>

                    <div class="profile-form-group">
                        <label>Confirm Password <span>*</span></label>
                        <input type="password" name="password_confirmation" placeholder="Confirm Password">
                    </div>
                </div>

                <div class="profile-actions">
                    <button type="submit" class="profile-primary-btn">
                        Update Password
                    </button>
                </div>
            </form>
        </div>

        <aside class="profile-card profile-side-card">
            <div class="profile-avatar">
                @if ($profileImage)
                    <img src="{{ $profileImage }}" alt="Profile Image">
                @else
                    <span>{{ strtoupper(substr($user->name ?? 'P', 0, 1)) }}</span>
                @endif
            </div>

            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>

            <div class="profile-status-list">
                <div>
                    <span>Account</span>
                    <strong class="status-badge {{ $accountStatus }}">
                        {{ ucfirst($accountStatus) }}
                    </strong>
                </div>

                <div>
                    <span>Document</span>
                    <strong class="status-badge {{ $documentStatus }}">
                        {{ ucfirst($documentStatus) }}
                    </strong>
                </div>
            </div>

            @if (!empty($profile->document_note))
                <div class="profile-note">
                    <strong>Admin Note</strong>
                    <p>{{ $profile->document_note }}</p>
                </div>
            @endif
        </aside>
    </div>
</section>

<script src="{{ asset('provider/js/provider-profile.js') }}?v={{ filemtime(public_path('provider/js/provider-profile.js')) }}"></script>
@endsection
