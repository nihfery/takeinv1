@extends('provider.layouts.dashboard')

@section('title', 'Business Dashboard - YouYaku')
@section('page_title', 'Dashboard')

@section('content')
@php
    $summaryCards = collect($summaryCards ?? []);
    $revenueCard = $summaryCards->firstWhere('icon', 'revenue') ?? ['title' => 'Total Revenue', 'value' => 'Rp0', 'raw_value' => 0, 'change' => ['direction' => 'flat', 'label' => '0% vs previous']];
    $bookingCard = $summaryCards->firstWhere('icon', 'booking') ?? ['title' => 'Total Bookings', 'value' => '0', 'raw_value' => 0, 'change' => ['direction' => 'flat', 'label' => '0% vs previous']];
    $completedCard = $summaryCards->firstWhere('icon', 'completed') ?? ['title' => 'Completed Bookings', 'value' => '0', 'raw_value' => 0, 'change' => ['direction' => 'flat', 'label' => '0% vs previous']];
    $pendingCard = $summaryCards->firstWhere('icon', 'pending') ?? ['title' => 'Pending Payment', 'value' => 'Rp0', 'raw_value' => 0, 'change' => ['direction' => 'flat', 'label' => '0% vs previous']];

    $staffItems = collect($topStaffPerformance['items'] ?? [])->values();
    $serviceItems = collect($bestSellingServices['items'] ?? [])->values();
    $paymentItems = collect($paymentStatus['items'] ?? [])->values();
    $chartSeries = collect($revenueChart['series'] ?? [])->where('visible', true)->values();
    $chartBuckets = collect($revenueChart['buckets'] ?? [])->values();
    $chartWidth = (int) ($revenueChart['width'] ?? 760);
    $chartHeight = (int) ($revenueChart['height'] ?? 260);
    $paymentTotal = (int) ($paymentStatus['total'] ?? 0);
    $upcomingAppointments = collect($operations['upcoming'] ?? [])->values();
    $appointmentActivity = collect($operations['activity'] ?? [])->values();
    $nextAppointment = $operations['next_today'] ?? null;

    $rupiah = fn ($amount) => 'Rp' . number_format((float) ($amount ?? 0), 0, ',', '.');
    $number = fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.');
    $changeClass = fn ($card) => match (data_get($card, 'change.direction', 'flat')) {
        'up' => 'is-positive',
        'down' => 'is-negative',
        default => 'is-neutral',
    };
    $changeIcon = fn ($card) => match (data_get($card, 'change.direction', 'flat')) {
        'up' => '+',
        'down' => '-',
        default => '~',
    };

    $authUser = auth()->user();
    $providerName = trim((string) ($authUser->name ?? 'Provider'));
    $firstName = explode(' ', $providerName)[0] ?: 'Provider';
    $greeting = match (true) {
        now()->hour < 12 => 'Good morning',
        now()->hour < 18 => 'Good afternoon',
        default => 'Good evening',
    };
    $isProviderOwner = $authUser && \App\Modules\Provider\Application\Support\ProviderMenuAccess::isProviderOwner($authUser);
    $canAccessMenu = fn (string $key) => \App\Modules\Provider\Application\Support\ProviderMenuAccess::userCanAccess($authUser, $key);
    $setupReady = (bool) data_get($setupChecklist ?? [], 'setup_ready', false);

    $primaryAction = null;

    if ($isProviderOwner && !$setupReady) {
        $primaryAction = match (true) {
            !data_get($setupChecklist, 'has_branch') => ['label' => 'Continue setup', 'url' => provider_route('provider.branch.create')],
            !data_get($setupChecklist, 'has_service') => ['label' => 'Continue setup', 'url' => provider_route('provider.services.create')],
            !data_get($setupChecklist, 'has_staff') => ['label' => 'Continue setup', 'url' => provider_route('provider.staffs.index')],
            !data_get($setupChecklist, 'has_skill') => ['label' => 'Continue setup', 'url' => provider_route('provider.staff.skills')],
            default => ['label' => 'Continue setup', 'url' => provider_route('provider.staff.schedules')],
        };
    }

    $averageBookingValue = (float) ($bookingCard['raw_value'] ?? 0) > 0
        ? (float) ($revenueCard['raw_value'] ?? 0) / (float) $bookingCard['raw_value']
        : 0;
@endphp

@if(!empty($branchEligibility))
    @php $ineligibleCount = collect($branchEligibility)->where('is_eligible', false)->count(); @endphp
    @if($ineligibleCount > 0)
        <div class="provider-home-notice">
            <span>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5M12 17h.01"></path></svg>
            </span>
            <div>
                <strong>{{ $ineligibleCount }} {{ Str::plural('location', $ineligibleCount) }} not visible publicly</strong>
                <p>Activate the location and make sure it has active services and professionals.</p>
            </div>
            @if($isProviderOwner && $canAccessMenu('branch'))
                <a href="{{ provider_route('provider.branch.index') }}">Review locations</a>
            @endif
        </div>
    @endif
@endif

<section class="provider-home provider-home-refined">
    <header class="provider-home-hero provider-home-command-bar">
        <div class="provider-home-heading">
            <span class="provider-home-eyebrow">Business overview</span>
            <h1>{{ $greeting }}, {{ $firstName }}</h1>
            <p>See what needs attention now, then move straight into your next task.</p>
        </div>

        <div class="provider-home-command-actions">
            @if($primaryAction)
                <a href="{{ $primaryAction['url'] }}" class="provider-home-primary-action">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v18M3 12h18"></path></svg>
                    {{ $primaryAction['label'] }}
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                </a>
            @endif

            <form method="GET" action="{{ provider_route('provider.dashboard') }}" class="provider-home-period">
                <label for="providerDashboardPeriod">Reporting period</label>
                <div>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path></svg>
                    <select id="providerDashboardPeriod" name="period" onchange="this.form.submit()">
                        @foreach ($periodOptions as $key => $label)
                            <option value="{{ $key }}" {{ ($selectedPeriod ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </header>

    <div class="provider-home-summary-strip" aria-label="Performance summary">
        @foreach ([$revenueCard, $bookingCard, $completedCard, $pendingCard] as $card)
            <article>
                <span>{{ $card['title'] }}</span>
                <strong>{{ $card['value'] }}</strong>
                <small class="provider-home-change {{ $changeClass($card) }}">
                    {{ $changeIcon($card) }} {{ data_get($card, 'change.label', '0%') }}
                </small>
            </article>
        @endforeach
    </div>

    <div class="provider-home-focus-grid">
        <article class="provider-home-panel provider-home-sales-card">
            <header class="provider-home-panel-head">
                <div>
                    <span class="provider-home-section-label">Latest sales</span>
                    <h2>{{ $revenueCard['value'] }}</h2>
                    <p>{{ $periodLabel ?? 'Selected period' }} · {{ $number($bookingCard['raw_value'] ?? 0) }} appointments · {{ $rupiah($averageBookingValue) }} average value</p>
                </div>
                <div class="provider-home-chart-legend">
                    @foreach ($chartSeries as $series)
                        <span><i style="--series-color: {{ $series['color'] }}"></i>{{ $series['label'] }}</span>
                    @endforeach
                </div>
            </header>

            <div class="provider-home-line-chart {{ empty($revenueChart['has_data']) ? 'is-empty' : '' }}">
                <div class="provider-home-chart-scale">
                    <span>{{ $revenueChart['max_label'] ?? 'Rp0' }}</span>
                    <span>{{ $revenueChart['mid_label'] ?? 'Rp0' }}</span>
                    <span>Rp0</span>
                </div>
                <div class="provider-home-chart-canvas">
                    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" preserveAspectRatio="none" role="img" aria-label="Revenue trend">
                        <g class="provider-home-chart-grid">
                            <line x1="14" y1="30" x2="{{ $chartWidth - 14 }}" y2="30"></line>
                            <line x1="14" y1="124" x2="{{ $chartWidth - 14 }}" y2="124"></line>
                            <line x1="14" y1="218" x2="{{ $chartWidth - 14 }}" y2="218"></line>
                        </g>
                        @foreach ($chartSeries as $series)
                            @if (!empty($series['path']))
                                <path class="provider-home-chart-line" d="{{ $series['path'] }}" style="--series-color: {{ $series['color'] }}"></path>
                                @foreach ($series['points'] ?? [] as $point)
                                    <circle class="provider-home-chart-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" style="--series-color: {{ $series['color'] }}">
                                        <title>{{ $series['label'] }}: {{ $rupiah($point['value']) }}</title>
                                    </circle>
                                @endforeach
                            @endif
                        @endforeach
                    </svg>
                    @if(empty($revenueChart['has_data']))
                        <div class="provider-home-chart-empty"><strong>No sales yet</strong><span>Paid transactions will appear here.</span></div>
                    @endif
                </div>
            </div>
            <div class="provider-home-chart-labels" style="--dashboard-chart-columns: {{ max(1, $chartBuckets->count()) }}">
                @forelse ($chartBuckets as $bucket)<span>{{ $bucket['label'] }}</span>@empty<span>No period data</span>@endforelse
            </div>
        </article>

        <article class="provider-home-panel provider-home-upcoming-card">
            <header class="provider-home-panel-head">
                <div>
                    <span class="provider-home-section-label">Upcoming appointments</span>
                    <h2>Next 7 days</h2>
                    <p>Your nearest confirmed and active bookings.</p>
                </div>
                @if($canAccessMenu('calendar'))
                    <a href="{{ provider_route('provider.calendar.index') }}" class="provider-home-text-link">Open calendar <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg></a>
                @endif
            </header>

            <div class="provider-home-appointment-list">
                @forelse($upcomingAppointments as $appointment)
                    <div class="provider-home-appointment-row">
                        <time><strong>{{ $appointment['date_short'] }}</strong><span>{{ $appointment['time_label'] }}</span></time>
                        <div>
                            <strong>{{ $appointment['service'] }}</strong>
                            <span>{{ $appointment['customer'] }} · {{ $appointment['staff'] }}</span>
                        </div>
                        <small class="is-{{ $appointment['status_tone'] }}">{{ $appointment['status_label'] }}</small>
                    </div>
                @empty
                    <div class="provider-home-empty-state provider-home-schedule-empty">
                        <span><svg viewBox="0 0 24 24" fill="none"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"></path></svg></span>
                        <strong>Your schedule is clear</strong>
                        <p>New appointments will appear here as soon as customers book.</p>
                        @if($canAccessMenu('walk_in'))<a href="{{ provider_route('provider.walk-in.index') }}">Add walk-in appointment</a>@endif
                    </div>
                @endforelse
            </div>
        </article>
    </div>

    <div class="provider-home-operations-grid">
        <article class="provider-home-panel provider-home-activity-card">
            <header class="provider-home-panel-head">
                <div>
                    <span class="provider-home-section-label">Appointment activity</span>
                    <h2>Recent updates</h2>
                    <p>The latest booking activity across your accessible locations.</p>
                </div>
                @if($canAccessMenu('bookings'))
                    <a href="{{ provider_route('provider.bookings.index') }}" class="provider-home-text-link">View bookings <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg></a>
                @endif
            </header>

            <div class="provider-home-activity-list">
                @forelse($appointmentActivity as $activity)
                    <div class="provider-home-activity-row">
                        <time><strong>{{ $activity['date_short'] }}</strong><span>{{ $activity['time_label'] }}</span></time>
                        <div>
                            <strong>{{ $activity['service'] }}</strong>
                            <span>{{ $activity['customer'] }} · {{ $activity['duration_label'] }} with {{ $activity['staff'] }}</span>
                        </div>
                        <div class="provider-home-activity-meta">
                            <small class="is-{{ $activity['status_tone'] }}">{{ $activity['status_label'] }}</small>
                            <strong>{{ $activity['price_label'] }}</strong>
                        </div>
                    </div>
                @empty
                    <div class="provider-home-empty-state is-compact"><strong>No appointment activity yet</strong><p>Booking updates will be collected here.</p></div>
                @endforelse
            </div>
        </article>

        <article class="provider-home-panel provider-home-next-card">
            <header class="provider-home-panel-head">
                <div>
                    <span class="provider-home-section-label">Next appointment today</span>
                    <h2>{{ $nextAppointment ? $nextAppointment['time_label'] : 'No more appointments' }}</h2>
                    <p>{{ now()->format('l, d F Y') }}</p>
                </div>
            </header>

            @if($nextAppointment)
                <div class="provider-home-next-details">
                    <span class="provider-home-next-time">{{ $nextAppointment['time_label'] }}</span>
                    <div>
                        <strong>{{ $nextAppointment['service'] }}</strong>
                        <p>{{ $nextAppointment['customer'] }}</p>
                        <span>{{ $nextAppointment['duration_label'] }} · {{ $nextAppointment['staff'] }}</span>
                    </div>
                    <small class="is-{{ $nextAppointment['status_tone'] }}">{{ $nextAppointment['status_label'] }}</small>
                </div>
            @else
                <div class="provider-home-empty-state provider-home-next-empty">
                    <span><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18M12 14v3l2 1"></path></svg></span>
                    <strong>Your day is clear</strong>
                    <p>There are no remaining appointments scheduled for today.</p>
                </div>
            @endif

            <div class="provider-home-action-grid">
                @if($canAccessMenu('bookings'))<a href="{{ provider_route('provider.bookings.index') }}">Review bookings</a>@endif
                @if($canAccessMenu('calendar'))<a href="{{ provider_route('provider.calendar.index') }}">Open calendar</a>@endif
                @if($canAccessMenu('walk_in'))<a href="{{ provider_route('provider.walk-in.index') }}">Add walk-in</a>@endif
            </div>
        </article>
    </div>

    <div class="provider-home-secondary-heading">
        <div><span class="provider-home-section-label">Business insights</span><h2>Understand what drives performance</h2></div>
        <p>These supporting insights stay available without competing with today's operational tasks.</p>
    </div>

    <div class="provider-home-insight-grid">
        <article class="provider-home-panel provider-home-services-panel">
            <header class="provider-home-panel-head"><div><h2>Top services</h2><p>Most frequently booked services.</p></div>@if($canAccessMenu('services'))<a href="{{ provider_route('provider.services.index') }}" class="provider-home-text-link">Manage <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg></a>@endif</header>
            <div class="provider-home-service-list">
                @forelse ($serviceItems->take(4) as $item)
                    @php $servicePercentage = min(100, max(4, (float) ($item['percentage'] ?? 0))); @endphp
                    <div class="provider-home-service-row">
                        <span class="provider-home-service-rank">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="provider-home-service-copy"><div><strong>{{ $item['name'] }}</strong><span>{{ $number($item['total_booking'] ?? 0) }} bookings</span></div><div class="provider-home-progress"><i style="--progress-width: {{ $servicePercentage }}%"></i></div></div>
                        <div class="provider-home-service-value"><strong>{{ $rupiah($item['revenue'] ?? 0) }}</strong><span>{{ $item['percentage_label'] ?? '0%' }}</span></div>
                    </div>
                @empty
                    <div class="provider-home-empty-state is-compact"><strong>No service performance yet</strong><p>Rankings appear after customers start booking.</p></div>
                @endforelse
            </div>
        </article>

        <article class="provider-home-panel provider-home-team-panel">
            <header class="provider-home-panel-head"><div><h2>Top professionals</h2><p>Team contribution this period.</p></div>@if($canAccessMenu('staffs'))<a href="{{ provider_route('provider.staffs.index') }}" class="provider-home-text-link">View team <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg></a>@endif</header>
            <div class="provider-home-team-list">
                @forelse ($staffItems->take(4) as $staff)
                    @php $initials = collect(explode(' ', trim((string) $staff['name'])))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''); @endphp
                    <div class="provider-home-team-row"><span class="provider-home-team-avatar">{{ $initials ?: 'ST' }}</span><div class="provider-home-team-copy"><strong>{{ $staff['name'] }}</strong><span>{{ $staff['rating_label'] ?? '-' }} rating · {{ $number($staff['total_booking'] ?? 0) }} bookings</span></div><strong class="provider-home-team-revenue">{{ $staff['revenue_label'] ?? 'Rp0' }}</strong></div>
                @empty
                    <div class="provider-home-empty-state is-compact"><strong>No staff performance yet</strong><p>Assign professionals to bookings to see insights.</p></div>
                @endforelse
            </div>
        </article>

        <article class="provider-home-panel provider-home-payment-panel">
            <header class="provider-home-panel-head"><div><h2>Payment overview</h2><p>Transaction status for this period.</p></div>@if($canAccessMenu('payments'))<a href="{{ provider_route('provider.payments.index') }}" class="provider-home-text-link">View all <svg viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6"></path></svg></a>@endif</header>
            <div class="provider-home-payment-body provider-home-payment-body-compact">
                <div class="provider-home-donut" style="--donut-gradient: {{ $paymentStatus['gradient'] ?? 'conic-gradient(#ebe7e3 0 100%)' }}"><div><strong>{{ $number($paymentTotal) }}</strong><span>transactions</span></div></div>
                <div class="provider-home-payment-list">@foreach($paymentItems as $item)<div><span><i style="--series-color: {{ $item['color'] }}"></i>{{ $item['name'] }}</span><strong>{{ $number($item['total_booking'] ?? 0) }}</strong><small>{{ $item['percentage_label'] ?? '0%' }}</small></div>@endforeach</div>
            </div>
        </article>

        <article class="provider-home-panel provider-home-readiness-panel">
            <header class="provider-home-panel-head"><div><h2>Business readiness</h2><p>Core resources available now.</p></div><span class="provider-home-health-badge">{{ $setupReady ? 'Ready' : 'Needs setup' }}</span></header>
            <div class="provider-home-resource-grid">
                <div><span>Locations</span><strong>{{ $number($stats['branches_count'] ?? 0) }}</strong><small>active resources</small></div>
                <div><span>Services</span><strong>{{ $number($stats['services_count'] ?? 0) }}</strong><small>available offerings</small></div>
                <div><span>Professionals</span><strong>{{ $number($stats['staff_count'] ?? 0) }}</strong><small>team members</small></div>
                <div><span>Customers</span><strong>{{ $number($stats['customers_count'] ?? 0) }}</strong><small>unique bookers</small></div>
            </div>
        </article>
    </div>
</section>
@endsection
