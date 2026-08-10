@extends('provider.layouts.dashboard')

@section('title', 'My Profile - Provider Dashboard')
@section('page_title', 'My Profile')
@section('page_subtitle', 'Manage provider identity, verification documents, and account security.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/pages/profile.css') }}">
@endpush

@section('content')
@php
    $profileImage = $profile->image ? asset('storage/' . $profile->image) : null;
    $ktpImage = $ktpDocumentUrl ?? null;
    $nibDocument = $nibDocumentUrl ?? null;
    $businessImage = $profile->business_image ? asset('storage/' . $profile->business_image) : null;

    $accountStatus = $profile->status ?? 'active';
    $documentStatus = $profile->document_status ?? 'pending';
    $createdAt = $user->created_at ? $user->created_at->format('d M Y') : '-';
    $createdTime = $user->created_at ? $user->created_at->format('H:i') : '-';
    $updatedAt = $profile->updated_at ? $profile->updated_at->format('d M Y') : '-';
    $emailStatus = $user->email_verified_at ? 'Verified' : 'Not Verified';
    $complete = collect([$profile->ktp_image, $profile->nib_number, $profile->nib_document, $profile->business_image])->filter()->count() . '/4';
@endphp

<section class="admin-profile-page admin-booking-page provider-profile-page">
    <div class="admin-booking-route admin-profile-route provider-profile-route">
        <div class="admin-breadcrumb">
            <a href="{{ provider_route('provider.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>My Profile</strong>
        </div>

    </div>

    @if (session('success'))
        <div class="admin-booking-alert success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="admin-booking-alert danger">{{ session('error') }}</div>
    @endif

    <div class="admin-booking-summary-grid admin-profile-summary-grid">
        <div class="admin-booking-summary-card pink">
            <span>Account</span>
            <strong>{{ ucfirst($accountStatus) }}</strong>
            <small>Provider account status</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Document</span>
            <strong>{{ ucfirst($documentStatus) }}</strong>
            <small>Document verification status</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Joined</span>
            <strong>{{ $createdAt }}</strong>
            <small>{{ $createdTime }} WIB</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Completion</span>
            <strong>{{ $complete }}</strong>
            <small>KTP, nomor NIB, dokumen NIB, dan foto usaha</small>
        </div>
    </div>

    <div class="admin-profile-layout">
        <aside class="admin-profile-side-card provider-profile-side-card">
            <div class="admin-profile-mini">
                <div class="admin-profile-hero-avatar">
                @if ($profileImage)
                    <img src="{{ $profileImage }}" alt="Profile Image">
                @else
                    {{ strtoupper(substr($user->name ?? 'P', 0, 1)) }}
                @endif
                </div>

                <div class="admin-profile-mini-info">
                    <div class="admin-profile-badges">
                        <span class="admin-profile-badge active">{{ ucfirst($accountStatus) }}</span>
                        <span class="admin-profile-badge admin">Provider</span>
                    </div>

                    <h2>{{ $user->name }}</h2>
                    <p>{{ $user->email }}</p>
                </div>
            </div>

            <div class="left-section">
                <h3>Account Access</h3>

                <div class="info-row">
                    <strong>Provider ID</strong>
                    <span>#{{ str_pad((string) $user->id, 4, '0', STR_PAD_LEFT) }}</span>
                </div>

                <div class="info-row">
                    <strong>Username</strong>
                    <span>{{ $user->username ?: '-' }}</span>
                </div>

                <div class="info-row">
                    <strong>Role</strong>
                    <span>{{ ucfirst($user->role ?? 'provider') }}</span>
                </div>
            </div>

            <div class="left-section">
                <h3>Primary Contact</h3>

                <div class="contact-item">
                    <div class="contact-icon">P</div>
                    <div>
                        <strong>Phone Number</strong>
                        <span>{{ $profile->phone_number ?: '-' }}</span>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">E</div>
                    <div>
                        <strong>Email Address</strong>
                        <span>{{ $user->email }}</span>
                    </div>
                </div>
            </div>

            <div class="left-section">
                <h3>Verification Snapshot</h3>

                <div class="admin-profile-status-list">
                    <div>
                        <span>Email Status</span>
                        <strong>{{ $emailStatus }}</strong>
                    </div>

                    <div>
                        <span>Document</span>
                        <strong>{{ ucfirst($documentStatus) }}</strong>
                    </div>

                    <div>
                        <span>Profile Update</span>
                        <strong>{{ $updatedAt }}</strong>
                    </div>
                </div>
            </div>

            @if (!empty($profile->document_note))
                <div class="profile-note">
                    <strong>Admin Note</strong>
                    <p>{{ $profile->document_note }}</p>
                </div>
            @endif
        </aside>

        <main class="admin-profile-main">
            <div class="admin-profile-tab">
                Profile Details
            </div>

            <div class="detail-panel admin-profile-card">
                <div class="detail-panel-header admin-profile-panel-header">
                    <span>Account Information</span>
                    <strong>#{{ str_pad((string) $user->id, 4, '0', STR_PAD_LEFT) }}</strong>
                </div>

                <div class="detail-panel-body">
                    <div class="admin-profile-overview-grid">
                    <div class="admin-profile-overview-item">
                        <span>Name</span>
                        <strong>{{ $user->name ?? '-' }}</strong>
                    </div>

                    <div class="admin-profile-overview-item">
                        <span>Username</span>
                        <strong>{{ $user->username ?? '-' }}</strong>
                    </div>

                    <div class="admin-profile-overview-item">
                        <span>Email</span>
                        <strong>{{ $user->email ?? '-' }}</strong>
                    </div>

                    <div class="admin-profile-overview-item">
                        <span>Phone Number</span>
                        <strong>{{ $profile->phone_number ?? '-' }}</strong>
                    </div>

                    <div class="admin-profile-overview-item">
                        <span>Email Verification</span>
                        <strong>{{ $emailStatus }}</strong>
                    </div>

                    <div class="admin-profile-overview-item">
                        <span>Profile Update</span>
                        <strong>{{ $updatedAt }}</strong>
                    </div>
                    </div>
                </div>
            </div>

            @if ($canAccessSensitiveDocuments)
            <div class="detail-panel admin-profile-card">
                <div class="detail-panel-header admin-profile-panel-header">
                    <span>Verification Documents</span>
                    <strong>{{ ucfirst($documentStatus) }}</strong>
                </div>

                <div class="detail-panel-body">
                <div class="profile-document-grid provider-document-grid">
                    <div class="profile-document-item">
                        <div class="document-preview">
                            @if ($nibDocument)
                                <a href="{{ $nibDocument }}" target="_blank" rel="noopener">Buka dokumen NIB</a>
                            @else
                                <span>No NIB Document</span>
                            @endif
                        </div>

                        <h3>NIB: {{ $profile->nib_number ?: '-' }}</h3>
                    </div>

                    <div class="profile-document-item">
                        <div class="document-preview">
                            @if ($ktpImage)
                                <img src="{{ $ktpImage }}" alt="KTP Image">
                            @else
                                <span>No KTP Image</span>
                            @endif
                        </div>

                        <h3>ID Card Photo</h3>
                    </div>

                    <div class="profile-document-item">
                        <div class="document-preview">
                            @if ($businessImage)
                                <img src="{{ $businessImage }}" alt="Business Image">
                            @else
                                <span>No Business Image</span>
                            @endif
                        </div>

                        <h3>Business Photo</h3>
                    </div>
                </div>

                @if ($documentStatus !== 'verified')
                    <div class="admin-profile-note-box provider-document-note">
                        <strong>Verification Note</strong>
                        <p>Complete the documents on the edit profile page so admin can verify your account.</p>
                    </div>
                @else
                    <div class="admin-profile-note-box provider-document-note">
                        <strong>Verification Note</strong>
                        <p>The provider documents have been verified by admin.</p>
                    </div>
                @endif
                </div>
            </div>
            @endif

            <div class="detail-panel admin-profile-card">
                <div class="detail-panel-header admin-profile-panel-header">
                    <span>Security</span>
                    <strong>Password</strong>
                </div>

                <div class="detail-panel-body">
                    <div class="admin-profile-security-grid">
                        <div class="admin-profile-security-copy">
                    <div class="contact-icon">S</div>

                            <div>
                                <strong>Login Password</strong>
                                <p>Use the edit profile page to update the account password.</p>
                            </div>
                        </div>

                        <div class="admin-profile-overview-grid">
                            <div class="admin-profile-overview-item">
                                <span>Account Status</span>
                                <strong>{{ ucfirst($accountStatus) }}</strong>
                            </div>

                            <div class="admin-profile-overview-item">
                                <span>Last Profile Update</span>
                                <strong>{{ $updatedAt }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="admin-profile-actions">
                        <a href="{{ provider_route('provider.profile.edit') }}" class="admin-profile-primary">
                            Edit Profile & Password
                        </a>
                    </div>
                </div>
            </div>

            <div class="detail-panel admin-profile-card">
                <div class="detail-panel-header admin-profile-panel-header">
                    <span>Profile Summary</span>
                    <strong>Overview</strong>
                </div>

                <div class="detail-panel-body">
                    <div class="admin-profile-overview-grid">
                        <div class="admin-profile-overview-item">
                            <span>Full Name</span>
                            <strong>{{ $user->name ?? '-' }}</strong>
                        </div>

                        <div class="admin-profile-overview-item">
                            <span>Role</span>
                            <strong>{{ ucfirst($user->role ?? 'provider') }}</strong>
                        </div>

                        <div class="admin-profile-overview-item">
                            <span>Email Verification</span>
                            <strong>{{ $emailStatus }}</strong>
                        </div>

                        <div class="admin-profile-overview-item">
                            <span>Created At</span>
                            <strong>{{ $createdAt }}</strong>
                        </div>
                    </div>

                    <div class="admin-profile-note-box">
                        <strong>Admin Note</strong>
                        <p>{{ $profile->document_note ?: 'No admin note yet.' }}</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</section>
@endsection
