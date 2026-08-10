@extends('admin.layouts.app')

@section('title', 'My Profile - JasaKu')
@section('page_title', 'My Profile')

@section('content')
@php
    $admin = $user ?? Auth::user();
    $profile = $profile ?? $admin->adminProfile;

    $parts = collect(explode(' ', trim($admin->name ?? 'Admin')))->filter()->values();
    $initials = $parts->count() >= 2
        ? strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1))
        : strtoupper(substr($admin->name ?? 'A', 0, 1));

    $avatar = $profile->avatar ?? null;
    $avatarUrl = null;

    if (! empty($avatar)) {
        $avatar = ltrim($avatar, '/');

        if (\Illuminate\Support\Str::startsWith($avatar, ['http://', 'https://'])) {
            $avatarUrl = $avatar;
        } elseif (\Illuminate\Support\Str::startsWith($avatar, 'storage/')) {
            $avatarUrl = asset($avatar);
        } else {
            $avatarUrl = asset('storage/' . $avatar);
        }
    }

    $roleLabel = \Illuminate\Support\Str::headline($admin->role ?? 'admin');
    $position = $profile->position ?: 'Administrator';
    $phoneNumber = $profile->phone_number ?: '-';
    $bio = $profile->bio ?: 'No profile notes yet.';
    $createdAt = $admin->created_at ? $admin->created_at->format('d M Y') : '-';
    $createdTime = $admin->created_at ? $admin->created_at->format('H:i') : '-';
    $updatedAt = $admin->updated_at ? $admin->updated_at->format('d M Y') : '-';
    $profileUpdatedAt = $profile->updated_at ? $profile->updated_at->format('d M Y') : '-';
    $emailStatus = $admin->email_verified_at ? 'Verified' : 'Not Verified';
    $sessionLifetime = (int) config('session.lifetime', 120);
@endphp

<section class="admin-profile-page admin-booking-page">
    <div class="admin-booking-route admin-profile-route">
        <div class="admin-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span>&rsaquo;</span>
            <strong>My Profile</strong>
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
            {{ $errors->first() }}
        </div>
    @endif

    <div class="admin-booking-summary-grid admin-profile-summary-grid">
        <div class="admin-booking-summary-card pink">
            <span>Role</span>
            <strong>{{ $roleLabel }}</strong>
            <small>Main dashboard access</small>
        </div>

        <div class="admin-booking-summary-card yellow">
            <span>Email</span>
            <strong>{{ $emailStatus }}</strong>
            <small>{{ $admin->email }}</small>
        </div>

        <div class="admin-booking-summary-card blue">
            <span>Joined</span>
            <strong>{{ $createdAt }}</strong>
            <small>{{ $createdTime }} WIB</small>
        </div>

        <div class="admin-booking-summary-card orange">
            <span>Profile Update</span>
            <strong>{{ $profileUpdatedAt }}</strong>
            <small>Latest account data</small>
        </div>
    </div>

    <div class="admin-profile-layout">
        <aside class="admin-profile-side-card">
            <div class="admin-profile-mini">
                <div class="admin-profile-hero-avatar">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $admin->name }}">
                    @else
                        {{ $initials }}
                    @endif
                </div>

                <div class="admin-profile-mini-info">
                    <div class="admin-profile-badges">
                        <span class="admin-profile-badge active">Active</span>
                        <span class="admin-profile-badge admin">{{ $roleLabel }}</span>
                    </div>

                    <h2>{{ $admin->name }}</h2>
                    <p>{{ $admin->email }}</p>
                </div>
            </div>

            <div class="left-section">
                <h3>Account Access</h3>

                <div class="info-row">
                    <strong>Admin ID</strong>
                    <span>#{{ str_pad((string) $admin->id, 4, '0', STR_PAD_LEFT) }}</span>
                </div>

                <div class="info-row">
                    <strong>Username</strong>
                    <span>{{ $admin->username ?: '-' }}</span>
                </div>

                <div class="info-row">
                    <strong>Position</strong>
                    <span>{{ $position }}</span>
                </div>
            </div>

            <div class="left-section">
                <h3>Primary Contact</h3>

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
                        <span>{{ $admin->email }}</span>
                    </div>
                </div>
            </div>

            <div class="left-section">
                <h3>Security Snapshot</h3>

                <div class="admin-profile-status-list">
                    <div>
                        <span>Email Status</span>
                        <strong>{{ $emailStatus }}</strong>
                    </div>

                    <div>
                        <span>Session</span>
                        <strong>{{ $sessionLifetime }} min</strong>
                    </div>
                </div>
            </div>
        </aside>

        <main class="admin-profile-main">
            <div class="admin-profile-tab">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 12C14.2 12 16 10.2 16 8C16 5.8 14.2 4 12 4C9.8 4 8 5.8 8 8C8 10.2 9.8 12 12 12Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M4.5 20C5.4 16.6 8.3 14.5 12 14.5C15.7 14.5 18.6 16.6 19.5 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Profile Details
            </div>

            <form action="{{ route('admin.profile.update') }}" method="POST" enctype="multipart/form-data" class="detail-panel admin-profile-card">
                @csrf
                @method('PATCH')

                <div class="detail-panel-header admin-profile-panel-header">
                    <span>Account Information</span>
                    <strong>#{{ str_pad((string) $admin->id, 4, '0', STR_PAD_LEFT) }}</strong>
                </div>

                <div class="detail-panel-body">
                    <div class="admin-profile-upload-row">
                        <label for="adminAvatarInput" class="admin-profile-upload-avatar">
                            @if ($avatarUrl)
                                <img src="{{ $avatarUrl }}" id="adminAvatarPreview" alt="{{ $admin->name }}">
                                <span id="adminAvatarPlaceholder" class="hidden">Upload</span>
                            @else
                                <img src="" id="adminAvatarPreview" class="hidden" alt="{{ $admin->name }}">
                                <span id="adminAvatarPlaceholder">{{ $initials }}</span>
                            @endif
                        </label>

                        <input type="file" name="avatar" id="adminAvatarInput" accept="image/*" hidden>

                        <div>
                            <strong>Profile Photo</strong>
                            <p>JPG, PNG, or WEBP. Maximum 2MB.</p>
                            @error('avatar') <small>{{ $message }}</small> @enderror
                        </div>
                    </div>

                    <div class="admin-profile-form-grid">
                        <div class="admin-profile-form-group">
                            <label for="adminName">Name <span>*</span></label>
                            <input type="text" name="name" id="adminName" value="{{ old('name', $admin->name) }}" placeholder="Admin name">
                            @error('name') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="admin-profile-form-group">
                            <label for="adminUsername">Username</label>
                            <input type="text" name="username" id="adminUsername" value="{{ old('username', $admin->username) }}" placeholder="Admin username">
                            @error('username') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="admin-profile-form-group">
                            <label for="adminEmail">Email <span>*</span></label>
                            <input type="email" name="email" id="adminEmail" value="{{ old('email', $admin->email) }}" placeholder="Admin email">
                            @error('email') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="admin-profile-form-group">
                            <label for="adminPhone">Phone Number</label>
                            <input type="text" name="phone_number" id="adminPhone" value="{{ old('phone_number', $profile->phone_number) }}" placeholder="Phone number">
                            @error('phone_number') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="admin-profile-form-group full">
                            <label for="adminPosition">Position</label>
                            <input type="text" name="position" id="adminPosition" value="{{ old('position', $profile->position) }}" placeholder="Admin position">
                            @error('position') <small>{{ $message }}</small> @enderror
                        </div>

                        <div class="admin-profile-form-group full">
                            <label for="adminBio">Profile Note</label>
                            <textarea name="bio" id="adminBio" rows="4" placeholder="Short admin profile note">{{ old('bio', $profile->bio) }}</textarea>
                            @error('bio') <small>{{ $message }}</small> @enderror
                        </div>
                    </div>

                    <div class="admin-profile-actions">
                        <button type="reset" class="admin-profile-secondary">
                            Reset
                        </button>

                        <button type="submit" class="admin-profile-primary">
                            Save Profile
                        </button>
                    </div>
                </div>
            </form>

            <form action="{{ route('admin.profile.password.update') }}" method="POST" class="detail-panel admin-profile-card">
                @csrf
                @method('PUT')

                <div class="detail-panel-header admin-profile-panel-header">
                    <span>Security Settings</span>
                    <strong>Password</strong>
                </div>

                <div class="detail-panel-body">
                    <div class="admin-profile-security-grid">
                        <div class="admin-profile-security-copy">
                            <div class="contact-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="4" y="11" width="16" height="9" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M8 11V8C8 5.8 9.8 4 12 4C14.2 4 16 5.8 16 8V11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </div>

                            <div>
                                <strong>Login Password</strong>
                                <p>Last account update: {{ $updatedAt }}</p>
                            </div>
                        </div>

                        <div class="admin-profile-form-grid">
                            <div class="admin-profile-form-group full">
                                <label for="currentPassword">Current Password <span>*</span></label>
                                <input type="password" name="current_password" id="currentPassword" placeholder="Password lama">
                                @error('current_password') <small>{{ $message }}</small> @enderror
                            </div>

                            <div class="admin-profile-form-group">
                                <label for="newPassword">New Password <span>*</span></label>
                                <input type="password" name="password" id="newPassword" placeholder="Password baru">
                                @error('password') <small>{{ $message }}</small> @enderror
                            </div>

                            <div class="admin-profile-form-group">
                                <label for="passwordConfirmation">Confirm Password <span>*</span></label>
                                <input type="password" name="password_confirmation" id="passwordConfirmation" placeholder="Confirm password">
                            </div>
                        </div>
                    </div>

                    <div class="admin-profile-actions">
                        <button type="submit" class="admin-profile-primary">
                            Update Password
                        </button>
                    </div>
                </div>
            </form>

            <div class="detail-panel admin-profile-card">
                <div class="detail-panel-header admin-profile-panel-header">
                    <span>Profile Summary</span>
                    <strong>Overview</strong>
                </div>

                <div class="detail-panel-body">
                    <div class="admin-profile-overview-grid">
                        <div class="admin-profile-overview-item">
                            <span>Full Name</span>
                            <strong>{{ $admin->name }}</strong>
                        </div>

                        <div class="admin-profile-overview-item">
                            <span>Role</span>
                            <strong>{{ $roleLabel }}</strong>
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
                        <strong>Profile Note</strong>
                        <p>{{ $bio }}</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('adminAvatarInput');
            const preview = document.getElementById('adminAvatarPreview');
            const placeholder = document.getElementById('adminAvatarPlaceholder');

            if (!input || !preview || !placeholder) {
                return;
            }

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];

                if (!file) {
                    return;
                }

                preview.src = URL.createObjectURL(file);
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            });
        });
    </script>
@endpush
