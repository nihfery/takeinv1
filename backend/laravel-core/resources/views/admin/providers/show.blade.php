@extends('admin.layouts.app')

@section('title', 'Provider Details - JasaKu')
@section('page_title', 'Provider Details')

@section('content')
@php
    $profile = $provider->providerProfile;
    $accountStatus = $profile->status ?? 'inactive';
    $documentStatus = $profile->document_status ?? 'pending';

    $phoneNumber = $profile->phone_number ?? '-';
    $category = $profile->category ?? '-';
    $gender = $profile->gender ?? '-';
    $dateOfBirth = $profile->date_of_birth ?? '-';
    $address = $profile->address ?? '-';
    $city = $profile->city ?? null;
    $country = $profile->country ?? null;

    $initial = strtoupper(substr($provider->name ?? 'P', 0, 1));
    $profileImage = $profile->image ?? null;
@endphp

<section class="provider-detail-page admin-booking-page admin-people-detail-page">
    <div class="admin-booking-route admin-people-detail-route">
        <div class="admin-breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                <span>›</span>
                <a href="{{ route('admin.providers.index') }}">Providers</a>
                <span>›</span>
                <strong>Provider Details</strong>
            </div>
        <a href="{{ route('admin.providers.index') }}" class="detail-back-btn">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back
        </a>
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
            {{ $errors->first() }}
        </div>
    @endif

    <div class="provider-detail-layout">
        <aside class="provider-left-card">
            <div class="provider-mini-profile">
                <div class="provider-avatar-image">
                    @if ($profileImage)
                        <img src="{{ asset('storage/' . $profileImage) }}" alt="{{ $provider->name }}">
                    @else
                        {{ $initial }}
                    @endif
                </div>

                <div class="provider-mini-info">
                    <div class="provider-badges">
                        <span class="detail-badge badge-{{ $accountStatus }}">
                            {{ ucfirst($accountStatus) }}
                        </span>

                        <span class="detail-badge badge-{{ $documentStatus }}">
                            {{ ucfirst($documentStatus) }}
                        </span>
                    </div>

                    <h2>{{ $provider->name }}</h2>
                    <p>{{ $provider->email }}</p>
                </div>
            </div>

            <div class="left-section">
                <h3>Basic Information</h3>

                <div class="info-row">
                    <strong>Name</strong>
                    <span>{{ $provider->name }}</span>
                </div>

                <div class="info-row">
                    <strong>Gender</strong>
                    <span>{{ $gender }}</span>
                </div>

                <div class="info-row">
                    <strong>Date of Birth</strong>
                    <span>{{ $dateOfBirth }}</span>
                </div>
            </div>

            <div class="left-section">
                <h3>Primary Contact Info</h3>

                <div class="contact-item">
                    <div class="contact-icon">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6.6 10.8C8.2 13.9 10.7 16.4 13.8 18L16.2 15.6C16.5 15.3 17 15.2 17.4 15.4C18.7 15.8 20 16 21.4 16C21.7 16 22 16.3 22 16.6V20.4C22 20.7 21.7 21 21.4 21C11.2 21 3 12.8 3 2.6C3 2.3 3.3 2 3.6 2H7.4C7.7 2 8 2.3 8 2.6C8 4 8.2 5.3 8.6 6.6C8.8 7 8.7 7.5 8.4 7.8L6.6 10.8Z" fill="currentColor"/>
                        </svg>
                    </div>

                    <div>
                        <strong>Phone Number</strong>
                        <span>{{ $phoneNumber }}</span>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="2"/>
                            <path d="M4.5 7L12 12.5L19.5 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>

                    <div>
                        <strong>Email Address</strong>
                        <span>{{ $provider->email }}</span>
                    </div>
                </div>
            </div>
        </aside>

        <main class="provider-detail-main">
            <div class="provider-tab">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 12C14.2 12 16 10.2 16 8C16 5.8 14.2 4 12 4C9.8 4 8 5.8 8 8C8 10.2 9.8 12 12 12Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M4.5 20C5.4 16.6 8.3 14.5 12 14.5C15.7 14.5 18.6 16.6 19.5 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Provider Details
            </div>

            <div class="detail-panel">
                <div class="detail-panel-header">
                    Category Information
                </div>

                <div class="detail-panel-body">
                    <div class="category-box">
                        <div class="category-avatar">
                            {{ strtoupper(substr($category !== '-' ? $category : 'C', 0, 1)) }}
                        </div>

                        <div>
                            <strong>{{ $category }}</strong>
                            <span>Provider service category</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="detail-panel">
                <div class="detail-panel-header">
                    Address
                </div>

                <div class="detail-panel-body">
                    <div class="address-line">
                        <div class="contact-icon">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 21C12 21 18 15.8 18 10.5C18 7 15.3 4.5 12 4.5C8.7 4.5 6 7 6 10.5C6 15.8 12 21 12 21Z" stroke="currentColor" stroke-width="2"/>
                                <circle cx="12" cy="10.5" r="2.4" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </div>

                        <div>
                            <strong>Address</strong>
                            <p>
                                {{ $address }}<br>

                                @if ($city || $country)
                                    {{ $city }}{{ $city && $country ? ', ' : '' }}{{ $country }}
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="detail-panel">
                <div class="detail-panel-header">
                    ID / Documents
                </div>

                <div class="detail-panel-body">
                    @if ($profile && ($profile->ktp_image || $profile->nib_document || $profile->business_image))
                        <div class="document-grid">
                            @if ($profile->ktp_image)
                                <div class="document-card">
                                    <strong>Foto KTP</strong>

                                    <a href="{{ $ktpDocumentUrl }}" target="_blank" rel="noopener">
                                        <img src="{{ $ktpDocumentUrl }}" alt="KTP">
                                    </a>
                                </div>
                            @endif

                            @if ($profile->nib_document)
                                <div class="document-card document-file-card">
                                    <strong>Dokumen NIB</strong>
                                    <span class="document-number">Nomor: {{ $profile->nib_number ?: '-' }}</span>
                                    <a href="{{ $nibDocumentUrl }}" target="_blank" rel="noopener" class="document-open-link">
                                        Buka dokumen NIB
                                    </a>
                                </div>
                            @endif

                            @if ($profile->business_image)
                                <div class="document-card">
                                    <strong>Foto Usaha</strong>

                                    <a href="{{ asset('storage/' . $profile->business_image) }}" target="_blank">
                                        <img src="{{ asset('storage/' . $profile->business_image) }}" alt="Business">
                                    </a>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="no-document">
                            No documents available.
                        </div>
                    @endif

                    <form
                        action="{{ route('admin.providers.document-status', $provider->id) }}"
                        method="POST"
                        class="document-control-form"
                    >
                        @csrf
                        @method('PATCH')

                        <select name="document_status" class="document-status-select {{ $documentStatus }}">
                            <option value="pending" {{ $documentStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="submitted" {{ $documentStatus === 'submitted' ? 'selected' : '' }}>Submitted</option>
                            <option value="verified" {{ $documentStatus === 'verified' ? 'selected' : '' }}>Verified</option>
                            <option value="rejected" {{ $documentStatus === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>

                        <textarea
                            name="document_note"
                            placeholder="Catatan wajib jika dokumen ditolak"
                        >{{ $profile->document_note ?? '' }}</textarea>

                        <button type="submit">
                            Update
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</section>
@endsection
