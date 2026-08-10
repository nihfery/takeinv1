@extends('admin.layouts.app')

@section('title', 'User Details - JasaKu')
@section('page_title', 'User Details')

@section('content')
@php
    $profile = $customer->customerProfile;
    $status = $profile->status ?? 'active';

    $avatarUrl = $profile && $profile->avatar
        ? (filter_var($profile->avatar, FILTER_VALIDATE_URL) ? $profile->avatar : asset('storage/' . $profile->avatar))
        : null;

    $initial = strtoupper(substr($customer->name ?? 'U', 0, 1));

    $dateOfBirth = $profile && $profile->date_of_birth
        ? \Carbon\Carbon::parse($profile->date_of_birth)->format('d/m/Y')
        : '-';

    $fullAddressTop = $profile->address_line_1 ?? '-';

    $fullAddressBottom = collect([
        $profile->address_line_2 ?? null,
        $profile->city ?? null,
        $profile->state ?? null,
        $profile->country ?? null,
    ])->filter()->implode(', ');

    if (!$fullAddressBottom) {
        $fullAddressBottom = '-';
    }
@endphp

<section class="user-detail-page admin-booking-page admin-people-detail-page">
    <div class="admin-booking-route admin-people-detail-route">
        <div>
            <h1>User Details</h1>

            <div class="user-breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                <span>›</span>
                <a href="{{ route('admin.users.index') }}">Users</a>
                <span>›</span>
                <strong>User Details</strong>
            </div>
        </div>

        <a href="{{ route('admin.users.index') }}" class="detail-back-btn">
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

    <div class="user-detail-layout">
        <aside class="user-left-card">
            <div class="user-mini-profile">
                <div class="user-avatar-image">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $customer->name }}">
                    @else
                        {{ $initial }}
                    @endif
                </div>

                <div class="user-mini-info">
                    <div class="user-badges">
                        <span class="detail-badge badge-{{ $status }}">
                            {{ ucfirst($status) }}
                        </span>
                    </div>

                    <h2>{{ $customer->name }}</h2>
                    <p>{{ $customer->email }}</p>
                </div>
            </div>

            <div class="left-section">
                <h3>Basic Information</h3>

                <div class="info-row">
                    <strong>Name</strong>
                    <span>{{ $customer->name }}</span>
                </div>

                <div class="info-row">
                    <strong>Gender</strong>
                    <span>{{ $profile->gender ?? '-' }}</span>
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
                        <span>{{ $profile->phone_number ?? '-' }}</span>
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
                        <span>{{ $customer->email }}</span>
                    </div>
                </div>
            </div>
        </aside>

        <main class="user-detail-main">
            <div class="user-tab">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 12C14.2 12 16 10.2 16 8C16 5.8 14.2 4 12 4C9.8 4 8 5.8 8 8C8 10.2 9.8 12 12 12Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M4.5 20C5.4 16.6 8.3 14.5 12 14.5C15.7 14.5 18.6 16.6 19.5 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                User Details
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
                                {{ $fullAddressTop }}<br>
                                {{ $fullAddressBottom }}
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
                    <div class="no-document">
                        No documents available.
                    </div>
                </div>
            </div>
        </main>
    </div>
</section>
@endsection
