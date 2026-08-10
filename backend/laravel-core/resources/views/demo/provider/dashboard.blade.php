@extends('provider.layouts.dashboard')

@section('title', 'Provider Dashboard - JasaKu')
@section('page_title', 'Dashboard')

@section('content')
@php
    $rupiah = fn ($amount) => 'Rp' . number_format((float) $amount);
    $number = fn ($value) => number_format((float) $value);

    $summaryCards = collect([
        ['icon' => 'revenue', 'title' => 'Total Revenue', 'value' => 'Rp 24.500.000', 'change' => ['label' => '12%']],
        ['icon' => 'booking', 'title' => 'Total Bookings', 'value' => '142', 'change' => ['label' => '5%']],
        ['icon' => 'completed', 'title' => 'Completed', 'value' => '128', 'change' => ['label' => '8%']],
        ['icon' => 'pending', 'title' => 'Pending', 'value' => '14', 'change' => ['label' => '-2%']],
    ]);
    
    $revenueCard = $summaryCards->firstWhere('icon', 'revenue') ?? ['title' => 'Total Revenue', 'value' => 'Rp0', 'change' => ['label' => '0%']];
    $bookingCard = $summaryCards->firstWhere('icon', 'booking') ?? ['title' => 'Total Bookings', 'value' => '0', 'change' => ['label' => '0%']];
    $completedCard = $summaryCards->firstWhere('icon', 'completed') ?? ['title' => 'Completed', 'value' => '0', 'change' => ['label' => '0%']];
    $pendingCard = $summaryCards->firstWhere('icon', 'pending') ?? ['title' => 'Pending', 'value' => '0', 'change' => ['label' => '0%']];

    $staffItems = collect([
        ['name' => 'Dewi Anggraini', 'rating_label' => '4.9', 'total_booking' => 45, 'revenue_label' => 'Rp 8.500.000'],
        ['name' => 'Budi Kurniawan', 'rating_label' => '4.8', 'total_booking' => 38, 'revenue_label' => 'Rp 6.200.000'],
    ])->values();

    $serviceItems = collect([
        ['name' => 'Premium Haircut', 'booking_count' => 56, 'revenue_label' => 'Rp 5.600.000'],
        ['name' => 'Hair Coloring', 'booking_count' => 42, 'revenue_label' => 'Rp 10.500.000'],
        ['name' => 'Hair Spa', 'booking_count' => 28, 'revenue_label' => 'Rp 4.200.000'],
    ])->values();

    $stats = [
        'branches_count' => 2,
        'services_count' => 15,
    ];

    $providerName = 'Aura Studio';
    $firstName = 'Aura';
    
    $periodOptions = ['month' => 'This Month'];
    $selectedPeriod = 'month';
    $periodLabel = 'This Month';
@endphp

<div class="floating-dashboard-header">
    <div>
        <h1>Good morning, {{ $firstName }}</h1>
        <p>Monitor your business health, staff schedules, orders, and today’s priorities from one command centre.</p>
    </div>
    
    <div>
        <select class="floating-period-select" onchange="window.location.href='?period='+this.value">
            @foreach ($periodOptions as $key => $label)
                <option value="{{ $key }}" {{ ($selectedPeriod ?? '') === $key ? 'selected' : '' }}>
                    Active period: {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="floating-dashboard-grid">
    <!-- Left Column -->
    <div class="dashboard-col">
        <!-- Main Revenue Card -->
        <div class="floating-card" style="margin-bottom: 20px;">
            <h3 class="floating-card-title">Portfolio Revenue</h3>
            <div class="floating-card-value">{{ $revenueCard['value'] }}</div>
            <span class="floating-badge">&uarr; {{ $revenueCard['change']['label'] }}</span>

            <div class="action-btn-group">
                <a href="{{ provider_route('provider.payments.index') }}" class="floating-action-btn dark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    Manage
                </a>
                <a href="#" class="floating-action-btn light">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M4 7h16v10H4z"/><path d="M8 7V5h8v2"/><path d="M12 10v4"/><path d="M9 13h6"/></svg>
                    Payout
                </a>
            </div>

            <div style="margin-top: 24px; border-top: 1px solid #eee; padding-top: 16px;">
                <h3 class="floating-card-title" style="margin-bottom: 0;">Top Staff | {{ $staffItems->count() }} active</h3>
                <div class="mini-client-list">
                    @forelse ($staffItems as $staff)
                        <div class="mini-client-card">
                            <strong>{{ $staff['name'] }}</strong>
                            <small>★ {{ $staff['rating_label'] ?? '0' }} | {{ $number($staff['total_booking'] ?? 0) }}x</small>
                            <span>{{ $staff['revenue_label'] ?? 'Rp0' }}</span>
                        </div>
                    @empty
                        <div class="mini-client-card">
                            <strong>No staff data</strong>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- System Insights -->
        <div class="floating-card">
            <h3 class="floating-card-title">System Status</h3>
            <div style="margin-top: 12px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                    <span style="color: var(--text-main)">Operational Capacity</span>
                    <span style="color: #f97316">Good</span>
                </div>
                <div style="background: var(--border-color); height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="background: #f97316; width: 85%; height: 100%; border-radius: 4px;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted); margin-top: 8px;">
                    <span>{{ $stats['branches_count'] ?? 0 }} Branches Active</span>
                    <span>{{ $stats['services_count'] ?? 0 }} Services Available</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Center Column -->
    <div class="dashboard-col">
        <div class="floating-grid-2">
            <!-- Accent Box -->
            <div class="floating-card accent-card" style="padding: 20px;">
                <h3 class="floating-card-title">{{ $bookingCard['title'] }}</h3>
                <div class="floating-card-value">{{ $bookingCard['value'] }}</div>
                <span class="floating-badge">&uarr; {{ $bookingCard['change']['label'] }}</span>
                <div style="font-size: 12px; opacity: 0.8; margin-top: 12px;">All booking stages</div>
            </div>

            <!-- Normal Box -->
            <div class="floating-card" style="padding: 20px;">
                <h3 class="floating-card-title">{{ $completedCard['title'] }}</h3>
                <div class="floating-card-value">{{ $completedCard['value'] }}</div>
                <span class="floating-badge">&uarr; {{ $completedCard['change']['label'] }}</span>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 12px;">Successfully served</div>
            </div>
        </div>

        <div class="floating-grid-2">
            <div class="floating-card" style="padding: 20px;">
                <h3 class="floating-card-title">{{ $pendingCard['title'] }}</h3>
                <div class="floating-card-value" style="font-size: 28px;">{{ $pendingCard['value'] }}</div>
                <span class="floating-badge" style="color: #f97316; background: #fff1e6;">&bull; {{ $pendingCard['change']['label'] }}</span>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 12px;">Waiting on customer</div>
            </div>

            <div class="floating-card" style="padding: 20px;">
                <h3 class="floating-card-title">Customers</h3>
                <div class="floating-card-value" style="font-size: 28px;">{{ $number($stats['customers_count'] ?? 0) }}</div>
                <span class="floating-badge">&uarr; New Leads</span>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 12px;">Total database</div>
            </div>
        </div>

        <!-- Progress Bars Box -->
        <div class="floating-card">
            <h3 class="floating-card-title" style="margin-bottom: 4px; font-size: 15px; font-weight: 700; color: var(--text-main);">Top Services</h3>
            <p style="font-size: 12px; color: var(--text-muted); margin: 0 0 20px 0;">Ringkasan performa layanan paling laris.</p>
            
            <div style="display: flex; flex-direction: column; gap: 16px;">
                @forelse ($serviceItems->take(3) as $item)
                    @php 
                        $pct = (int) str_replace('%', '', $item['percentage_label'] ?? '0');
                        $width = max(10, $pct);
                        $color = $loop->index === 0 ? '#ff6b4a' : ($loop->index === 1 ? '#a855f7' : '#10b981');
                    @endphp
                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 600;">
                        <span style="width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $item['name'] }}</span>
                        <div style="flex: 1; background: #f1f1f1; height: 10px; border-radius: 5px; margin: 0 16px; overflow: hidden;">
                            <div style="background: {{ $color }}; width: {{ $width }}%; height: 100%; border-radius: 5px;"></div>
                        </div>
                        <span style="color: var(--text-muted); width: 35px; text-align: right;">{{ $item['percentage_label'] ?? '0%' }}</span>
                    </div>
                @empty
                    <div style="font-size: 13px; color: var(--text-muted);">No service data available yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="dashboard-col">
        <div class="floating-card" style="height: 100%;">
            <h3 class="floating-card-title" style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Activity & Forecast</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0 0 24px 0;">Revenue and booking trends for the active period.</p>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px; font-size: 12px; font-weight: 600; margin-bottom: 24px;">
                <span style="display: flex; align-items: center; gap: 6px;"><div style="width:8px; height:8px; border-radius:50%; background: #ff6b4a;"></div> Revenue</span>
                <span style="display: flex; align-items: center; gap: 6px;"><div style="width:8px; height:8px; border-radius:50%; background: var(--text-main);"></div> Bookings</span>
            </div>

            <!-- Simulated Chart (Mapping bookingSummary to visually resemble the bar chart) -->
            <div style="display: flex; align-items: flex-end; justify-content: space-between; height: 260px; padding-bottom: 12px; border-bottom: 1px solid #eee;">
                @php
                    $chartBuckets = collect($revenueChart['buckets'] ?? [])->values();
                    if ($chartBuckets->isEmpty()) {
                        $chartBuckets = collect([
                            ['label' => 'W1', 'paid_revenue' => 0, 'booked_revenue' => 0],
                            ['label' => 'W2', 'paid_revenue' => 0, 'booked_revenue' => 0],
                            ['label' => 'W3', 'paid_revenue' => 0, 'booked_revenue' => 0],
                            ['label' => 'W4', 'paid_revenue' => 0, 'booked_revenue' => 0]
                        ]);
                    }
                    $maxRev = max(1, $chartBuckets->max('paid_revenue') + $chartBuckets->max('booked_revenue'));
                @endphp
                
                @foreach($chartBuckets->take(8) as $bucket)
                    @php
                        $paidPct = max(5, (($bucket['paid_revenue'] ?? 0) / $maxRev) * 100);
                        $bookPct = max(5, (($bucket['booked_revenue'] ?? 0) / $maxRev) * 100);
                    @endphp
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 4px; width: 10%;">
                        <!-- Revenue Bar (Orange) -->
                        <div style="width: 100%; background-image: repeating-linear-gradient(45deg, #ff6b4a, #ff6b4a 2px, #ff8c73 2px, #ff8c73 4px); height: {{ $paidPct }}%; border-radius: 8px 8px 0 0; min-height: 20px; transition: height 0.5s ease;"></div>
                        <!-- Booking Bar (Adapts to theme) -->
                        <div style="width: 100%; background: var(--text-main); height: {{ $bookPct }}%; border-radius: 8px; min-height: 16px; margin-top: -8px; z-index: 10; transition: height 0.5s ease;"></div>
                    </div>
                @endforeach
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted); margin-top: 12px;">
                @foreach($chartBuckets->take(8) as $bucket)
                    <span style="width: 10%; text-align: center;">{{ $bucket['label'] }}</span>
                @endforeach
            </div>
            
            <div class="floating-card" style="margin-top: 32px; padding: 20px;">
                <h3 style="margin: 0 0 8px 0; font-size: 14px; color: var(--text-main);">Business Status</h3>
                <p style="margin: 0; font-size: 13px; color: var(--text-muted); line-height: 1.5;">You have {{ $number($stats['services_count'] ?? 0) }} active services. Ensure your staff schedules are updated for this week's incoming bookings.</p>
            </div>
        </div>
    </div>
</div>
<style>
    /* Prevent manual clicking on the whole page to make it a pure simulation */
    body {
        pointer-events: none;
    }
    
    /* Subtle theme transition effect */
    body, .admin-main-wrapper, .admin-sidebar, .admin-topbar, .floating-card {
        transition: background-color 0.8s ease, color 0.8s ease, border-color 0.8s ease, box-shadow 0.8s ease !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const themeBtn = document.getElementById('themeToggleBtn');
        const wait = ms => new Promise(r => setTimeout(r, ms));
        
        async function runThemeSimulation() {
            await wait(3000);
            
            while (true) {
                const darkBtn = document.querySelector('.toggle-theme[title="Dark Theme"]');
                const lightBtn = document.querySelector('.toggle-theme[title="Light Theme"]');
                
                if (darkBtn) darkBtn.click();
                await wait(4000);
                
                if (lightBtn) lightBtn.click();
                await wait(4000);
            }
        }
        
        runThemeSimulation();
    });
</script>
@endsection
