@extends('provider.layouts.dashboard')

@section('title', 'Customer Directory - YouYaku')
@section('page_title', 'Customer Directory')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/provider-customers.css') }}?v=1">
@endpush

@section('content')
@php
    $summary = $summary ?? [];
    $customers = $customers ?? collect();
    $rupiah = fn ($amount) => 'Rp' . number_format((float) ($amount ?? 0), 0, ',', '.');
    $number = fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.');
@endphp

<section class="provider-crm-page">
    <header class="provider-crm-hero">
        <div>
            <span>Customers</span>
            <h1>Customer directory</h1>
            <p>Understand who books with your business and identify returning customers from one place.</p>
        </div>

        <form method="GET" action="{{ provider_route('provider.customers.index') }}" class="provider-crm-search">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-4-4"></path>
            </svg>
            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Search name, email, phone, or customer ID"
                aria-label="Search customers"
            >
            @if ($search !== '')
                <a href="{{ provider_route('provider.customers.index') }}">Clear</a>
            @endif
        </form>
    </header>

    <div class="provider-crm-summary">
        <article>
            <span>Total customers</span>
            <strong>{{ $number($summary['total_customers'] ?? 0) }}</strong>
            <small>Unique registered bookers</small>
        </article>
        <article>
            <span>Returning customers</span>
            <strong>{{ $number($summary['returning_customers'] ?? 0) }}</strong>
            <small>Customers with multiple bookings</small>
        </article>
        <article>
            <span>Booked this month</span>
            <strong>{{ $number($summary['new_this_month'] ?? 0) }}</strong>
            <small>Active customers this month</small>
        </article>
        <article>
            <span>Total bookings</span>
            <strong>{{ $number($summary['total_bookings'] ?? 0) }}</strong>
            <small>Bookings across this business</small>
        </article>
    </div>

    <article class="provider-crm-panel">
        <header class="provider-crm-panel-head">
            <div>
                <h2>All customers</h2>
                <p>{{ $customers->total() }} customer records{{ $search !== '' ? ' matching your search' : '' }}.</p>
            </div>
            <span>{{ $customers->firstItem() ?? 0 }}–{{ $customers->lastItem() ?? 0 }} of {{ $customers->total() }}</span>
        </header>

        <div class="provider-crm-table-wrap">
            <table class="provider-crm-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Customer ID</th>
                        <th>Contact</th>
                        <th>Bookings</th>
                        <th>Total value</th>
                        <th>Last booking</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php
                            $profile = $customer->customerProfile;
                            $nameParts = collect(explode(' ', trim((string) $customer->name)))->filter()->take(2);
                            $initials = $nameParts
                                ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                ->implode('');
                            $lastBooking = $customer->provider_last_booking_date
                                ? \Carbon\Carbon::parse($customer->provider_last_booking_date)
                                : null;
                        @endphp
                        <tr>
                            <td>
                                <div class="provider-crm-customer">
                                    <span>{{ $initials ?: 'CU' }}</span>
                                    <div>
                                        <strong>{{ $customer->name }}</strong>
                                        <small>{{ $profile?->city ?: 'Location not provided' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td><code>{{ $profile?->customer_id ?: '—' }}</code></td>
                            <td>
                                <div class="provider-crm-contact">
                                    <strong>{{ $customer->email }}</strong>
                                    <small>{{ $profile?->phone_number ?: 'Phone not provided' }}</small>
                                </div>
                            </td>
                            <td><strong>{{ $number($customer->provider_bookings_count) }}</strong></td>
                            <td><strong>{{ $rupiah($customer->provider_total_spent) }}</strong></td>
                            <td>
                                @if ($lastBooking)
                                    <strong>{{ $lastBooking->translatedFormat('d M Y') }}</strong>
                                    <small>{{ $lastBooking->diffForHumans() }}</small>
                                @else
                                    <span>—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="provider-crm-empty">
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle cx="10" cy="8" r="4"></circle>
                                            <path d="M3 21v-2a7 7 0 0 1 14 0v2M18 8h4M20 6v4"></path>
                                        </svg>
                                    </span>
                                    <strong>No customers found</strong>
                                    <p>Customer records will appear after a registered customer completes the booking flow.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="provider-crm-pagination">
                {{ $customers->links() }}
            </div>
        @endif
    </article>
</section>
@endsection
