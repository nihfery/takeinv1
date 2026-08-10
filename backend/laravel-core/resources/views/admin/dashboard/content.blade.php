@php
    $stats = $stats ?? [];
    $activeTab = $activeTab ?? 'overview';
    $monthlyBuckets = $monthlyBuckets ?? [];
    $monthlyBars = $monthlyBars ?? ['providers' => [], 'services' => [], 'bookings' => []];
    $revenueChart = $revenueChart ?? [];
    $recentPlatformRows = $recentPlatformRows ?? [];
    $salesSummary = $salesSummary ?? [];
    $orderSummary = $orderSummary ?? [];
    $reportSummary = $reportSummary ?? [];

    $tabs = [
        'overview' => 'Overview',
        'sales' => 'Sales',
        'order' => 'Order',
        'report' => 'Report',
    ];

    $rupiah = fn ($value) => 'Rp' . number_format((float) $value, 0, ',', '.');
    $statusLabel = fn ($value) => ucwords(str_replace('_', ' ', $value ?: '-'));

    $totalProviders = $stats['total_providers'] ?? 0;
    $activeProviders = $stats['active_providers'] ?? 0;
    $totalServices = $stats['total_services'] ?? 0;
    $activeServices = $stats['active_services'] ?? 0;
    $totalBookings = $stats['total_bookings'] ?? 0;
    $completedBookings = $stats['completed_bookings'] ?? 0;
    $pendingBookings = $stats['pending_bookings'] ?? 0;
    $totalAmount = $stats['total_amount'] ?? 0;
    $completedAmount = $stats['completed_amount'] ?? 0;
    $pendingAmount = $stats['pending_amount'] ?? 0;
    $paidAmount = $stats['paid_amount'] ?? 0;
    $latestChart = $revenueChart['latest'] ?? [];
@endphp

<section class="admin-dashboard-page">
    <div class="admin-dashboard-tabs-row">
        <div class="admin-dashboard-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.dashboard', ['tab' => $key]) }}"
                   class="admin-dashboard-tab {{ $activeTab === $key ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="admin-dashboard-actions">
            <details class="admin-export-menu">
                <summary class="admin-dashboard-action-btn dark">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 3v12"></path>
                        <path d="m7 10 5 5 5-5"></path>
                        <path d="M5 21h14"></path>
                    </svg>
                    Export all
                </summary>

                <div class="admin-export-options">
                    <a href="{{ route('admin.dashboard.export', 'pdf') }}">PDF</a>
                    <a href="{{ route('admin.dashboard.export', 'csv') }}">CSV</a>
                    <a href="{{ route('admin.dashboard.export', 'excel') }}">Excel</a>
                </div>
            </details>
        </div>
    </div>

    <div class="admin-dashboard-metric-grid">
        <div class="admin-metric-card">
            <div class="admin-metric-head">
                <h3>Total Providers</h3>
            </div>

            <div class="admin-metric-value">
                <strong>{{ number_format((int) $totalProviders) }}</strong>
                <span class="positive">&bull; {{ number_format((int) $activeProviders) }} active</span>
            </div>

            <div class="admin-mini-chart">
                @foreach ($monthlyBars['providers'] ?? [] as $bar)
                    <span class="{{ $loop->last ? 'active' : '' }}"
                          style="height: {{ $bar['height'] }}px"
                          title="{{ $bar['label'] }}: {{ number_format((int) $bar['value']) }}"></span>
                @endforeach
            </div>

            <div class="admin-mini-months">
                @foreach ($monthlyBuckets as $bucket)
                    <span>{{ $bucket['label'] }}</span>
                @endforeach
            </div>
        </div>

        <div class="admin-metric-card">
            <div class="admin-metric-head">
                <h3>Total Services</h3>
            </div>

            <div class="admin-metric-value">
                <strong>{{ number_format((int) $totalServices) }}</strong>
                <span class="positive">&bull; {{ number_format((int) $activeServices) }} active</span>
            </div>

            <div class="admin-mini-chart">
                @foreach ($monthlyBars['services'] ?? [] as $bar)
                    <span class="{{ $loop->last ? 'active' : '' }}"
                          style="height: {{ $bar['height'] }}px"
                          title="{{ $bar['label'] }}: {{ number_format((int) $bar['value']) }}"></span>
                @endforeach
            </div>

            <div class="admin-mini-months">
                @foreach ($monthlyBuckets as $bucket)
                    <span>{{ $bucket['label'] }}</span>
                @endforeach
            </div>
        </div>

        <div class="admin-metric-card">
            <div class="admin-metric-head">
                <h3>Total Amount</h3>
            </div>

            <div class="admin-metric-value">
                <strong>{{ $rupiah($totalAmount) }}</strong>
                <span class="positive">&bull; revenue</span>
            </div>

            <div class="admin-mini-chart">
                @foreach ($monthlyBars['bookings'] ?? [] as $bar)
                    <span class="{{ $loop->last ? 'active' : '' }}"
                          style="height: {{ $bar['height'] }}px"
                          title="{{ $bar['label'] }}: {{ number_format((int) $bar['value']) }} bookings"></span>
                @endforeach
            </div>

            <div class="admin-mini-months">
                @foreach ($monthlyBuckets as $bucket)
                    <span>{{ $bucket['label'] }}</span>
                @endforeach
            </div>
        </div>
    </div>

    @if ($activeTab === 'overview')
        <div class="admin-dashboard-chart-grid">
            <div class="admin-chart-card">
                <div class="admin-chart-head">
                    <h3>Revenue forecast</h3>

                    <div class="admin-chart-actions">
                        <button type="button">
                            <svg viewBox="0 0 24 24">
                                <path d="M8 2v4"></path>
                                <path d="M16 2v4"></path>
                                <path d="M5 5h14v16H5z"></path>
                                <path d="M3 10h18"></path>
                            </svg>
                            Monthly
                        </button>
                    </div>
                </div>

                <div class="admin-revenue-chart-wrap">
                    <svg class="admin-revenue-chart" viewBox="0 0 760 300" preserveAspectRatio="none" role="img" aria-label="Revenue trend from database">
                        <defs>
                            <linearGradient id="adminPurpleArea" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#a855f7" stop-opacity=".24"/>
                                <stop offset="100%" stop-color="#a855f7" stop-opacity="0"/>
                            </linearGradient>

                            <linearGradient id="adminOrangeArea" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#f97316" stop-opacity=".18"/>
                                <stop offset="100%" stop-color="#f97316" stop-opacity="0"/>
                            </linearGradient>
                        </defs>

                        <g class="chart-grid-lines">
                            <line x1="0" y1="52" x2="760" y2="52"></line>
                            <line x1="0" y1="108" x2="760" y2="108"></line>
                            <line x1="0" y1="164" x2="760" y2="164"></line>
                            <line x1="0" y1="220" x2="760" y2="220"></line>
                        </g>

                        <path class="area purple-area" d="{{ $revenueChart['paid_area'] ?? '' }}"></path>
                        <path class="area orange-area" d="{{ $revenueChart['booked_area'] ?? '' }}"></path>
                        <path class="line purple-line" d="{{ $revenueChart['paid_path'] ?? '' }}"></path>
                        <path class="line orange-line" d="{{ $revenueChart['booked_path'] ?? '' }}"></path>
                        <path class="line blue-line" d="{{ $revenueChart['booking_path'] ?? '' }}"></path>

                        <line class="chart-marker"
                              x1="{{ $revenueChart['marker_x'] ?? 0 }}"
                              y1="52"
                              x2="{{ $revenueChart['marker_x'] ?? 0 }}"
                              y2="260"></line>

                        <circle class="chart-point purple"
                                cx="{{ $revenueChart['points']['paid']['x'] ?? 0 }}"
                                cy="{{ $revenueChart['points']['paid']['y'] ?? 245 }}"
                                r="5"></circle>
                        <circle class="chart-point orange"
                                cx="{{ $revenueChart['points']['booked']['x'] ?? 0 }}"
                                cy="{{ $revenueChart['points']['booked']['y'] ?? 245 }}"
                                r="5"></circle>
                        <circle class="chart-point blue"
                                cx="{{ $revenueChart['points']['bookings']['x'] ?? 0 }}"
                                cy="{{ $revenueChart['points']['bookings']['y'] ?? 245 }}"
                                r="5"></circle>
                    </svg>

                    <div class="admin-chart-tooltip"
                         style="left: {{ $revenueChart['tooltip_left'] ?? 12 }}px; top: {{ $revenueChart['tooltip_top'] ?? 62 }}px;">
                        <strong>{{ $latestChart['full_label'] ?? now()->format('F Y') }}</strong>

                        <p>
                            <span class="dot purple"></span>
                            {{ $rupiah($latestChart['paid_revenue'] ?? 0) }}
                            <b>paid</b>
                        </p>

                        <p>
                            <span class="dot orange"></span>
                            {{ $rupiah($latestChart['booked_revenue'] ?? 0) }}
                            <b>booked</b>
                        </p>

                        <p>
                            <span class="dot blue"></span>
                            {{ number_format((int) ($latestChart['bookings'] ?? 0)) }} bookings
                            <b>orders</b>
                        </p>
                    </div>
                </div>

                <div class="admin-chart-legend">
                    <span><i class="purple"></i> Paid Revenue</span>
                    <span><i class="orange"></i> Booked Revenue</span>
                    <span><i class="blue"></i> Bookings</span>
                </div>

                <div class="admin-mobile-summary-list">
                    <div>
                        <span>Paid</span>
                        <strong>{{ $rupiah($latestChart['paid_revenue'] ?? 0) }}</strong>
                    </div>
                    <div>
                        <span>Booked</span>
                        <strong>{{ $rupiah($latestChart['booked_revenue'] ?? 0) }}</strong>
                    </div>
                    <div>
                        <span>Bookings</span>
                        <strong>{{ number_format((int) ($latestChart['bookings'] ?? 0)) }}</strong>
                    </div>
                </div>
            </div>

            <div class="admin-chart-card admin-status-card">
                <div class="admin-chart-head">
                    <h3>Platform Status</h3>

                    <div class="admin-chart-actions">
                        <a href="{{ route('admin.providers.index') }}">Documents</a>
                    </div>
                </div>

                <div class="admin-gauge-wrap">
                    <div class="admin-gauge">
                        <span class="gauge-orange"></span>
                        <span class="gauge-purple"></span>
                        <span class="gauge-blue"></span>
                        <span class="gauge-green"></span>
                    </div>

                    <div class="admin-gauge-center">
                        <span>Total</span>
                        <strong>{{ number_format((int) ($totalProviders + $totalServices + $totalBookings)) }}</strong>
                    </div>
                </div>

                <div class="admin-status-list">
                    <div>
                        <span><i class="orange"></i> Providers</span>
                        <strong>{{ number_format((int) $totalProviders) }}</strong>
                        <em>{{ number_format((int) $activeProviders) }} active</em>
                    </div>

                    <div>
                        <span><i class="purple"></i> Services</span>
                        <strong>{{ number_format((int) $totalServices) }}</strong>
                        <em>{{ number_format((int) $activeServices) }} active</em>
                    </div>

                    <div>
                        <span><i class="blue"></i> Bookings</span>
                        <strong>{{ number_format((int) $totalBookings) }}</strong>
                        <em>{{ number_format((int) $pendingBookings) }} running</em>
                    </div>

                    <div>
                        <span><i class="green"></i> Paid</span>
                        <strong>{{ $rupiah($paidAmount) }}</strong>
                        <em>revenue</em>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-dashboard-table-card">
            <div class="admin-table-card-head">
                <h3>Recent platform data</h3>

                <div class="admin-table-tools">
                    <a href="{{ route('admin.providers.index') }}">Providers</a>
                    <a href="{{ route('admin.services.index') }}">Services</a>
                    <a href="{{ route('admin.bookings.index') }}">Bookings</a>
                </div>
            </div>

            <div class="admin-mobile-summary-list">
                @foreach ($recentPlatformRows as $row)
                    <a href="{{ match ($row['name']) {
                        'Providers' => route('admin.providers.index'),
                        'Services' => route('admin.services.index'),
                        default => route('admin.bookings.index'),
                    } }}">
                        <span>{{ $row['name'] }}</span>
                        <strong>{{ number_format((int) $row['total']) }}</strong>
                        <small>{{ $row['status'] }}</small>
                    </a>
                @endforeach
            </div>

            <div class="admin-table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Data name</th>
                            <th>Type</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($recentPlatformRows as $row)
                            <tr>
                                <td>
                                    <div class="admin-table-name">
                                        <span>{{ $row['initial'] }}</span>
                                        <div>
                                            <strong>{{ $row['name'] }}</strong>
                                            <small>{{ $row['description'] }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $row['type'] }}</td>
                                <td>{{ number_format((int) $row['total']) }}</td>
                                <td><span class="table-status {{ $row['status_class'] }}">{{ $row['status'] }}</span></td>
                                <td>{{ $row['updated'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($activeTab === 'sales')
        <div class="admin-dashboard-panel">
            <div class="admin-insight-grid">
                <article class="admin-insight-card">
                    <span>Paid Revenue</span>
                    <strong>{{ $rupiah($salesSummary['paid_revenue'] ?? 0) }}</strong>
                    <small>Payment status paid</small>
                </article>
                <article class="admin-insight-card">
                    <span>Booked Revenue</span>
                    <strong>{{ $rupiah($salesSummary['booked_revenue'] ?? 0) }}</strong>
                    <small>All booking value</small>
                </article>
                <article class="admin-insight-card">
                    <span>Pending Revenue</span>
                    <strong>{{ $rupiah($salesSummary['pending_revenue'] ?? 0) }}</strong>
                    <small>Open and running bookings</small>
                </article>
                <article class="admin-insight-card">
                    <span>Average Order</span>
                    <strong>{{ $rupiah($salesSummary['average_order'] ?? 0) }}</strong>
                    <small>Booked revenue / bookings</small>
                </article>
            </div>

            <div class="admin-dashboard-split-grid">
                <div class="admin-dashboard-table-card">
                    <div class="admin-table-card-head">
                        <h3>Monthly sales</h3>
                    </div>

                    <div class="admin-mobile-summary-list">
                        @foreach ($salesSummary['monthly'] ?? [] as $bucket)
                            <div>
                                <span>{{ $bucket['label'] }}</span>
                                <strong>{{ $rupiah($bucket['booked_revenue']) }}</strong>
                                <small>{{ number_format((int) $bucket['bookings']) }} bookings</small>
                            </div>
                        @endforeach
                    </div>

                    <div class="admin-table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Booked</th>
                                    <th>Paid</th>
                                    <th>Pending</th>
                                    <th>Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($salesSummary['monthly'] ?? [] as $bucket)
                                    <tr>
                                        <td>{{ $bucket['full_label'] }}</td>
                                        <td>{{ $rupiah($bucket['booked_revenue']) }}</td>
                                        <td>{{ $rupiah($bucket['paid_revenue']) }}</td>
                                        <td>{{ $rupiah($bucket['pending_revenue']) }}</td>
                                        <td>{{ number_format((int) $bucket['bookings']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-chart-card admin-panel-card">
                    <div class="admin-chart-head">
                        <h3>Payment status</h3>
                    </div>

                    <div class="admin-compact-list">
                        @foreach ($salesSummary['payment_statuses'] ?? [] as $paymentStatus)
                            <div>
                                <span>{{ $paymentStatus['label'] }}</span>
                                <strong>{{ number_format((int) $paymentStatus['count']) }}</strong>
                                <em>{{ $rupiah($paymentStatus['amount']) }}</em>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @elseif ($activeTab === 'order')
        <div class="admin-dashboard-panel">
            <div class="admin-insight-grid">
                @foreach ($orderSummary['statuses'] ?? [] as $orderStatus)
                    <article class="admin-insight-card">
                        <span>{{ $orderStatus['label'] }}</span>
                        <strong>{{ number_format((int) $orderStatus['count']) }}</strong>
                        <small>Booking status</small>
                    </article>
                @endforeach
            </div>

            <div class="admin-dashboard-split-grid">
                <div class="admin-dashboard-table-card">
                    <div class="admin-table-card-head">
                        <h3>Recent orders</h3>
                        <div class="admin-table-tools">
                            <a href="{{ route('admin.bookings.index') }}">View all</a>
                        </div>
                    </div>

                    <div class="admin-mobile-summary-list">
                        @forelse ($orderSummary['recent'] ?? [] as $booking)
                            <a href="{{ route('admin.bookings.index', ['search' => $booking->booking_code]) }}">
                                <span>{{ $booking->booking_code }}</span>
                                <strong>{{ $rupiah($booking->total_price) }}</strong>
                                <small>{{ $statusLabel($booking->status) }}</small>
                            </a>
                        @empty
                            <div>
                                <span>No booking data yet.</span>
                                <strong>0</strong>
                            </div>
                        @endforelse
                    </div>

                    <div class="admin-table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking</th>
                                    <th>Customer</th>
                                    <th>Provider</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($orderSummary['recent'] ?? [] as $booking)
                                    <tr>
                                        <td>
                                            <div class="admin-table-name">
                                                <span>B</span>
                                                <div>
                                                    <strong>{{ $booking->booking_code }}</strong>
                                                    <small>{{ $booking->created_at?->format('d M Y') ?? '-' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $booking->customer_name ?: ($booking->customer?->name ?? '-') }}</td>
                                        <td>{{ $booking->provider?->name ?? '-' }}</td>
                                        <td><span class="table-status pending">{{ $statusLabel($booking->status) }}</span></td>
                                        <td>{{ $rupiah($booking->total_price) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">No booking data yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-chart-card admin-panel-card">
                    <div class="admin-chart-head">
                        <h3>Booking modes</h3>
                    </div>

                    <div class="admin-compact-list">
                        @forelse ($orderSummary['modes'] ?? [] as $mode)
                            <div>
                                <span>{{ $mode['label'] }}</span>
                                <strong>{{ number_format((int) $mode['count']) }}</strong>
                                <em>orders</em>
                            </div>
                        @empty
                            <p class="admin-empty-inline">No booking modes yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="admin-dashboard-panel">
            <div class="admin-dashboard-split-grid">
                <div class="admin-chart-card admin-panel-card">
                    <div class="admin-chart-head">
                        <h3>Provider documents</h3>
                        <div class="admin-chart-actions">
                            <a href="{{ route('admin.providers.index') }}">Review</a>
                        </div>
                    </div>

                    <div class="admin-compact-list">
                        @forelse ($reportSummary['documents'] ?? [] as $document)
                            <div>
                                <span>{{ $document['label'] }}</span>
                                <strong>{{ number_format((int) $document['count']) }}</strong>
                                <em>providers</em>
                            </div>
                        @empty
                            <p class="admin-empty-inline">No provider documents yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="admin-chart-card admin-panel-card">
                    <div class="admin-chart-head">
                        <h3>Service report</h3>
                        <div class="admin-chart-actions">
                            <a href="{{ route('admin.services.index') }}">Services</a>
                        </div>
                    </div>

                    <div class="admin-compact-list">
                        @foreach ($reportSummary['services'] ?? [] as $serviceStatus)
                            <div>
                                <span>{{ $serviceStatus['label'] }}</span>
                                <strong>{{ number_format((int) $serviceStatus['count']) }}</strong>
                                <em>services</em>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="admin-dashboard-split-grid">
                <div class="admin-dashboard-table-card">
                    <div class="admin-table-card-head">
                        <h3>Top services</h3>
                    </div>

                    <div class="admin-mobile-summary-list">
                        @forelse ($reportSummary['top_services'] ?? [] as $service)
                            <div>
                                <span>{{ $service->title }}</span>
                                <strong>{{ number_format((int) $service->bookings_count) }}</strong>
                                <small>{{ $service->provider?->name ?? '-' }}</small>
                            </div>
                        @empty
                            <div>
                                <span>No service data yet.</span>
                                <strong>0</strong>
                            </div>
                        @endforelse
                    </div>

                    <div class="admin-table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Provider</th>
                                    <th>Status</th>
                                    <th>Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reportSummary['top_services'] ?? [] as $service)
                                    <tr>
                                        <td>
                                            <div class="admin-table-name">
                                                <span>S</span>
                                                <div>
                                                    <strong>{{ $service->title }}</strong>
                                                    <small>{{ $rupiah($service->price) }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $service->provider?->name ?? '-' }}</td>
                                        <td><span class="table-status {{ $service->status === 'active' ? 'active' : 'inactive' }}">{{ $statusLabel($service->status) }}</span></td>
                                        <td>{{ number_format((int) $service->bookings_count) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">No service data yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-dashboard-table-card">
                    <div class="admin-table-card-head">
                        <h3>User roles</h3>
                    </div>

                    <div class="admin-mobile-summary-list">
                        @foreach ($reportSummary['roles'] ?? [] as $role)
                            <div>
                                <span>{{ $role['label'] }}</span>
                                <strong>{{ number_format((int) $role['count']) }}</strong>
                            </div>
                        @endforeach
                    </div>

                    <div class="admin-table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportSummary['roles'] ?? [] as $role)
                                    <tr>
                                        <td>{{ $role['label'] }}</td>
                                        <td>{{ number_format((int) $role['count']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
