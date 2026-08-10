@extends('admin.layouts.app')

@section('title', 'Add Coupon - JasaKu')
@section('page_title', 'Add Coupon')

@section('content')
<section class="admin-coupon-page admin-coupon-form-page admin-booking-page">
    <form action="{{ route('admin.coupons.store') }}" method="POST" id="couponForm" class="admin-coupon-editor">
        @csrf

        <div class="admin-booking-route admin-coupon-form-route">
            <div class="admin-breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                <span>&rsaquo;</span>
                <a href="{{ route('admin.coupons.index') }}">Coupons</a>
                <span>&rsaquo;</span>
                <strong>Add Coupon</strong>
            </div>

            <div class="admin-coupon-form-actions">
                <a href="{{ route('admin.coupons.index') }}" class="admin-coupon-secondary">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="m15 18-6-6 6-6"></path>
                    </svg>
                    Cancel
                </a>

                <button type="submit" class="admin-coupon-primary">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"></path>
                        <path d="M17 21v-8H7v8"></path>
                        <path d="M7 3v5h8"></path>
                    </svg>
                    Save Coupon
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="admin-booking-alert danger">
                {{ $errors->first() }}
            </div>
        @endif

        @include('admin.coupons.partials.form', [
            'coupon' => $coupon,
            'services' => $services,
            'categories' => $categories,
            'mode' => $mode ?? 'create',
        ])
    </form>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('admin/js/coupons.js') }}"></script>
@endpush
