@extends('provider.layouts.dashboard')

@section('title', 'Customer Reviews - YouYaku')
@section('page_title', 'Customer Reviews')

@push('styles')
    <link rel="stylesheet" href="{{ asset('provider/css/provider-customers.css') }}?v=1">
@endpush

@section('content')
@php
    $summary = $summary ?? [];
    $branchReviews = $branchReviews ?? collect();
    $staffReviews = $staffReviews ?? collect();
    $number = fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.');
@endphp

<section class="provider-crm-page provider-review-page">
    <header class="provider-crm-hero">
        <div>
            <span>Customer feedback</span>
            <h1>Reviews</h1>
            <p>Track feedback about your locations and professionals to continuously improve service quality.</p>
        </div>

        <form method="GET" action="{{ provider_route('provider.reviews.index') }}" class="provider-review-filter">
            <label for="providerReviewRating">Rating</label>
            <select id="providerReviewRating" name="rating" onchange="this.form.submit()">
                <option value="">All ratings</option>
                @for ($value = 5; $value >= 1; $value--)
                    <option value="{{ $value }}" {{ $rating === $value ? 'selected' : '' }}>
                        {{ $value }} stars
                    </option>
                @endfor
            </select>
        </form>
    </header>

    <div class="provider-crm-summary">
        <article>
            <span>Total reviews</span>
            <strong>{{ $number($summary['total_reviews'] ?? 0) }}</strong>
            <small>Location and professional reviews</small>
        </article>
        <article>
            <span>Location rating</span>
            <strong>{{ number_format((float) ($summary['branch_average'] ?? 0), 1) }}</strong>
            <small>Average customer score</small>
        </article>
        <article>
            <span>Professional rating</span>
            <strong>{{ number_format((float) ($summary['staff_average'] ?? 0), 1) }}</strong>
            <small>Average team score</small>
        </article>
        <article>
            <span>Five-star reviews</span>
            <strong>{{ $number($summary['five_star'] ?? 0) }}</strong>
            <small>Highest customer rating</small>
        </article>
    </div>

    <div class="provider-review-grid">
        <article class="provider-crm-panel">
            <header class="provider-crm-panel-head">
                <div>
                    <h2>Location reviews</h2>
                    <p>Feedback about the overall branch experience.</p>
                </div>
                <span>{{ $branchReviews->total() }} reviews</span>
            </header>

            <div class="provider-review-list">
                @forelse ($branchReviews as $review)
                    @php
                        $customer = $review->booking?->customer;
                        $name = $customer?->name ?: $review->booking?->customer_name ?: 'Customer';
                        $initials = collect(explode(' ', trim($name)))
                            ->filter()
                            ->take(2)
                            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                            ->implode('');
                    @endphp
                    <div class="provider-review-card">
                        <div class="provider-review-avatar">{{ $initials ?: 'CU' }}</div>
                        <div class="provider-review-copy">
                            <div class="provider-review-meta">
                                <div>
                                    <strong>{{ $name }}</strong>
                                    <span>{{ $review->booking?->branch?->branch_name ?: 'Location' }}</span>
                                </div>
                                <time>{{ $review->created_at?->translatedFormat('d M Y') }}</time>
                            </div>
                            <div class="provider-review-stars" aria-label="{{ $review->rating }} out of 5 stars">
                                @for ($star = 1; $star <= 5; $star++)
                                    <span class="{{ $star <= $review->rating ? 'is-filled' : '' }}">&#9733;</span>
                                @endfor
                            </div>
                            <p>{{ $review->comment ?: 'No written feedback was provided.' }}</p>
                            <small>Booking {{ $review->booking?->booking_code ?: '—' }}</small>
                        </div>
                    </div>
                @empty
                    <div class="provider-crm-empty">
                        <strong>No location reviews found</strong>
                        <p>Reviews matching this rating will appear here.</p>
                    </div>
                @endforelse
            </div>

            @if ($branchReviews->hasPages())
                <div class="provider-crm-pagination">{{ $branchReviews->links() }}</div>
            @endif
        </article>

        <article class="provider-crm-panel">
            <header class="provider-crm-panel-head">
                <div>
                    <h2>Professional reviews</h2>
                    <p>Feedback about individual team members.</p>
                </div>
                <span>{{ $staffReviews->total() }} reviews</span>
            </header>

            <div class="provider-review-list">
                @forelse ($staffReviews as $review)
                    @php
                        $customer = $review->booking?->customer;
                        $name = $customer?->name ?: $review->booking?->customer_name ?: 'Customer';
                        $initials = collect(explode(' ', trim($name)))
                            ->filter()
                            ->take(2)
                            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                            ->implode('');
                    @endphp
                    <div class="provider-review-card">
                        <div class="provider-review-avatar">{{ $initials ?: 'CU' }}</div>
                        <div class="provider-review-copy">
                            <div class="provider-review-meta">
                                <div>
                                    <strong>{{ $name }}</strong>
                                    <span>{{ $review->staff?->full_name ?: 'Professional' }}</span>
                                </div>
                                <time>{{ $review->created_at?->translatedFormat('d M Y') }}</time>
                            </div>
                            <div class="provider-review-stars" aria-label="{{ $review->rating }} out of 5 stars">
                                @for ($star = 1; $star <= 5; $star++)
                                    <span class="{{ $star <= $review->rating ? 'is-filled' : '' }}">&#9733;</span>
                                @endfor
                            </div>
                            <p>{{ $review->comment ?: 'No written feedback was provided.' }}</p>
                            <small>Booking {{ $review->booking?->booking_code ?: '—' }}</small>
                        </div>
                    </div>
                @empty
                    <div class="provider-crm-empty">
                        <strong>No professional reviews found</strong>
                        <p>Reviews matching this rating will appear here.</p>
                    </div>
                @endforelse
            </div>

            @if ($staffReviews->hasPages())
                <div class="provider-crm-pagination">{{ $staffReviews->links() }}</div>
            @endif
        </article>
    </div>
</section>
@endsection
