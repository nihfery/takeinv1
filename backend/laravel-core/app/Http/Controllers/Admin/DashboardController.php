<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Provider\Infrastructure\Persistence\Models\ProviderProfile;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Service;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Provider\Application\Queries\GetAdminDashboardStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const ACTIVE_BOOKING_STATUSES = [
        'open',
        'pending',
        'pending_hold',
        'pending_payment',
        'confirmed',
        'waiting',
        'checked_in',
        'in_progress',
        'inprogress',
        'rescheduled',
    ];

    private const COMPLETED_BOOKING_STATUSES = ['completed', 'order_completed'];

    private const CANCELLED_BOOKING_STATUSES = [
        'provider_cancelled',
        'customer_cancelled',
        'cancelled',
        'expired_hold',
        'payment_expired',
        'no_show',
    ];

    public function __construct(private readonly GetAdminDashboardStats $getAdminDashboardStats)
    {
    }

    public function index(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'You do not have access to the admin page.');
        }

        $allowedTabs = ['overview', 'sales', 'order', 'report'];
        $activeTab = (string) $request->get('tab', 'overview');
        $activeTab = in_array($activeTab, $allowedTabs, true) ? $activeTab : 'overview';

        $dashboardData = Cache::remember('admin_dashboard:data:' . now()->format('Ymd'), 30, function () {
            $stats = $this->stats();
            $monthlyBuckets = $this->monthlyBuckets();

            return [
                'stats' => $stats,
                'monthlyBuckets' => $monthlyBuckets,
                'monthlyBars' => $this->monthlyBars($monthlyBuckets),
                'revenueChart' => $this->revenueChart($monthlyBuckets),
                'recentPlatformRows' => $this->recentPlatformRows($stats),
                'salesSummary' => $this->salesSummary($stats, $monthlyBuckets),
                'orderSummary' => $this->orderSummary(),
                'reportSummary' => $this->reportSummary(),
            ];
        });

        return view('admin.dashboard.index', array_merge($dashboardData, [
            'activeTab' => $activeTab,
        ]));
    }

    public function export(string $format)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'You do not have access to the admin page.');
        }

        $format = strtolower($format);
        $allowedFormats = ['pdf', 'csv', 'excel'];

        abort_unless(in_array($format, $allowedFormats, true), 404);

        $sections = $this->exportSections();
        $filename = 'jasaku-dashboard-' . now()->format('Ymd-His');

        return match ($format) {
            'pdf' => $this->downloadPdf($sections, $filename . '.pdf'),
            'csv' => $this->downloadCsv($sections, $filename . '.csv'),
            'excel' => $this->downloadExcel($sections, $filename . '.xls'),
        };
    }

    private function stats(): array
    {
        return $this->getAdminDashboardStats->handle(
            activeBookingStatuses: self::ACTIVE_BOOKING_STATUSES,
            completedBookingStatuses: self::COMPLETED_BOOKING_STATUSES,
            cancelledBookingStatuses: self::CANCELLED_BOOKING_STATUSES,
        );
    }

    private function monthlyBuckets(): array
    {
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset)->startOfMonth());

        return $months->map(function (Carbon $month) {
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            return [
                'label' => $month->format('M'),
                'full_label' => $month->format('F Y'),
                'providers' => (clone $this->providerOwners())->whereBetween('created_at', [$start, $end])->count(),
                'services' => Service::query()->whereBetween('created_at', [$start, $end])->count(),
                'bookings' => Booking::query()->whereBetween('created_at', [$start, $end])->count(),
                'completed_bookings' => Booking::query()
                    ->whereIn('status', self::COMPLETED_BOOKING_STATUSES)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
                'booked_revenue' => $this->bookingAmount(Booking::query()->whereBetween('created_at', [$start, $end])),
                'paid_revenue' => (float) Payment::query()
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('amount'),
                'pending_revenue' => $this->bookingAmount(
                    Booking::query()
                        ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
                        ->whereBetween('created_at', [$start, $end])
                ),
            ];
        })->all();
    }

    private function monthlyBars(array $monthlyBuckets): array
    {
        return [
            'providers' => $this->barSet($monthlyBuckets, 'providers'),
            'services' => $this->barSet($monthlyBuckets, 'services'),
            'bookings' => $this->barSet($monthlyBuckets, 'bookings'),
        ];
    }

    private function revenueChart(array $monthlyBuckets): array
    {
        $paidValues = array_column($monthlyBuckets, 'paid_revenue');
        $bookedValues = array_column($monthlyBuckets, 'booked_revenue');
        $bookingValues = array_column($monthlyBuckets, 'bookings');
        $moneyMax = max([1, ...$paidValues, ...$bookedValues]);
        $bookingMax = max([1, ...$bookingValues]);

        $paidPoints = $this->chartPoints($paidValues, $moneyMax);
        $bookedPoints = $this->chartPoints($bookedValues, $moneyMax);
        $bookingPoints = $this->chartPoints($bookingValues, $bookingMax);
        $lastIndex = max(0, count($monthlyBuckets) - 1);
        $tooltipPoint = $paidPoints[$lastIndex] ?? ['x' => 0, 'y' => 245];

        return [
            'paid_path' => $this->linePath($paidPoints),
            'paid_area' => $this->areaPath($paidPoints),
            'booked_path' => $this->linePath($bookedPoints),
            'booked_area' => $this->areaPath($bookedPoints),
            'booking_path' => $this->linePath($bookingPoints),
            'marker_x' => $tooltipPoint['x'],
            'tooltip_left' => max(12, min(576, (float) $tooltipPoint['x'] - 86)),
            'tooltip_top' => max(42, min(190, (float) $tooltipPoint['y'] - 52)),
            'points' => [
                'paid' => $paidPoints[$lastIndex] ?? ['x' => 0, 'y' => 245],
                'booked' => $bookedPoints[$lastIndex] ?? ['x' => 0, 'y' => 245],
                'bookings' => $bookingPoints[$lastIndex] ?? ['x' => 0, 'y' => 245],
            ],
            'latest' => $monthlyBuckets[$lastIndex] ?? [],
        ];
    }

    private function recentPlatformRows(array $stats): array
    {
        return [
            [
                'initial' => 'P',
                'name' => 'Providers',
                'description' => 'Registered provider owners',
                'type' => 'People',
                'total' => $stats['total_providers'],
                'status' => $stats['active_providers'] . ' active',
                'status_class' => $stats['active_providers'] > 0 ? 'active' : 'pending',
                'updated' => $this->formatDate($this->providerOwners()->latest('updated_at')->value('updated_at')),
            ],
            [
                'initial' => 'S',
                'name' => 'Services',
                'description' => 'Available service catalog',
                'type' => 'Business',
                'total' => $stats['total_services'],
                'status' => $stats['active_services'] . ' active',
                'status_class' => $stats['active_services'] > 0 ? 'active' : 'pending',
                'updated' => $this->formatDate(Service::query()->latest('updated_at')->value('updated_at')),
            ],
            [
                'initial' => 'B',
                'name' => 'Bookings',
                'description' => 'Customer orders',
                'type' => 'Order',
                'total' => $stats['total_bookings'],
                'status' => $stats['pending_bookings'] . ' in progress',
                'status_class' => $stats['pending_bookings'] > 0 ? 'pending' : 'active',
                'updated' => $this->formatDate(Booking::query()->latest('updated_at')->value('updated_at')),
            ],
        ];
    }

    private function salesSummary(array $stats, array $monthlyBuckets): array
    {
        $paymentBreakdown = Payment::query()
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(amount), 0) as amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = collect(['paid', 'pending', 'unpaid', 'failed', 'refunded'])->map(function (string $status) use ($paymentBreakdown) {
            $row = $paymentBreakdown->get($status);

            return [
                'label' => ucwords(str_replace('_', ' ', $status)),
                'status' => $status,
                'count' => (int) ($row->total ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        })->all();

        return [
            'paid_revenue' => $stats['paid_amount'],
            'booked_revenue' => $stats['total_amount'],
            'pending_revenue' => $stats['pending_amount'],
            'average_order' => $stats['total_bookings'] > 0 ? $stats['total_amount'] / $stats['total_bookings'] : 0,
            'payment_statuses' => $statuses,
            'monthly' => $monthlyBuckets,
        ];
    }

    private function orderSummary(): array
    {
        $recentBookings = Booking::query()
            ->with(['provider:id,name', 'customer:id,name'])
            ->latest('created_at')
            ->limit(6)
            ->get();

        return [
            'statuses' => [
                [
                    'label' => 'Pending Payment',
                    'count' => Booking::query()->where('status', 'pending_payment')->count(),
                    'class' => 'pending',
                ],
                [
                    'label' => 'Waiting',
                    'count' => Booking::query()->where('status', 'waiting')->count(),
                    'class' => 'pending',
                ],
                [
                    'label' => 'In Progress',
                    'count' => Booking::query()->whereIn('status', ['checked_in', 'in_progress', 'inprogress'])->count(),
                    'class' => 'active',
                ],
                [
                    'label' => 'Completed',
                    'count' => Booking::query()->whereIn('status', self::COMPLETED_BOOKING_STATUSES)->count(),
                    'class' => 'active',
                ],
                [
                    'label' => 'Cancelled',
                    'count' => Booking::query()->whereIn('status', self::CANCELLED_BOOKING_STATUSES)->count(),
                    'class' => 'inactive',
                ],
            ],
            'modes' => Booking::query()
                ->select('booking_type', DB::raw('COUNT(*) as total'))
                ->groupBy('booking_type')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'label' => ucwords(str_replace('_', ' ', $row->booking_type ?: 'scheduled')),
                    'count' => (int) $row->total,
                ])
                ->all(),
            'recent' => $recentBookings,
        ];
    }

    private function reportSummary(): array
    {
        return [
            'documents' => ProviderProfile::query()
                ->select('document_status', DB::raw('COUNT(*) as total'))
                ->groupBy('document_status')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'label' => ucwords(str_replace('_', ' ', $row->document_status ?: 'pending')),
                    'count' => (int) $row->total,
                ])
                ->all(),
            'roles' => User::query()
                ->select('role', DB::raw('COUNT(*) as total'))
                ->groupBy('role')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'label' => ucwords(str_replace('_', ' ', $row->role ?: 'user')),
                    'count' => (int) $row->total,
                ])
                ->all(),
            'services' => [
                ['label' => 'Active', 'count' => Service::query()->where('status', 'active')->count()],
                ['label' => 'Inactive', 'count' => Service::query()->where('status', 'inactive')->count()],
                ['label' => 'Verified', 'count' => Service::query()->where('verify_status', 'verified')->count()],
                ['label' => 'Pending Verify', 'count' => Service::query()->where('verify_status', 'pending')->count()],
            ],
            'top_services' => Service::query()
                ->with('provider:id,name')
                ->withCount('bookings')
                ->get()
                ->sortByDesc(fn (Service $service) => (int) $service->bookings_count)
                ->take(5)
                ->values(),
        ];
    }

    private function exportSections(): array
    {
        $stats = $this->stats();
        $monthlyBuckets = $this->monthlyBuckets();
        $salesSummary = $this->salesSummary($stats, $monthlyBuckets);
        $orderSummary = $this->orderSummary();
        $reportSummary = $this->reportSummary();

        return [
            [
                'title' => 'Overview',
                'headers' => ['Metric', 'Value'],
                'rows' => [
                    ['Total Providers', (string) $stats['total_providers']],
                    ['Active Providers', (string) $stats['active_providers']],
                    ['Total Services', (string) $stats['total_services']],
                    ['Active Services', (string) $stats['active_services']],
                    ['Total Bookings', (string) $stats['total_bookings']],
                    ['Completed Bookings', (string) $stats['completed_bookings']],
                    ['Pending Bookings', (string) $stats['pending_bookings']],
                    ['Booked Revenue', $this->formatCurrency($stats['total_amount'])],
                    ['Paid Revenue', $this->formatCurrency($stats['paid_amount'])],
                ],
            ],
            [
                'title' => 'Monthly Sales',
                'headers' => ['Month', 'Booked Revenue', 'Paid Revenue', 'Pending Revenue', 'Bookings'],
                'rows' => array_map(fn (array $bucket) => [
                    $bucket['full_label'],
                    $this->formatCurrency($bucket['booked_revenue']),
                    $this->formatCurrency($bucket['paid_revenue']),
                    $this->formatCurrency($bucket['pending_revenue']),
                    (string) $bucket['bookings'],
                ], $monthlyBuckets),
            ],
            [
                'title' => 'Payment Status',
                'headers' => ['Status', 'Count', 'Amount'],
                'rows' => array_map(fn (array $paymentStatus) => [
                    $paymentStatus['label'],
                    (string) $paymentStatus['count'],
                    $this->formatCurrency($paymentStatus['amount']),
                ], $salesSummary['payment_statuses']),
            ],
            [
                'title' => 'Order Status',
                'headers' => ['Status', 'Count'],
                'rows' => array_map(fn (array $orderStatus) => [
                    $orderStatus['label'],
                    (string) $orderStatus['count'],
                ], $orderSummary['statuses']),
            ],
            [
                'title' => 'Recent Orders',
                'headers' => ['Booking Code', 'Customer', 'Provider', 'Status', 'Total'],
                'rows' => $orderSummary['recent']->map(fn (Booking $booking) => [
                    (string) $booking->booking_code,
                    (string) ($booking->customer_name ?: ($booking->customer?->name ?? '-')),
                    (string) ($booking->provider?->name ?? '-'),
                    ucwords(str_replace('_', ' ', (string) $booking->status)),
                    $this->formatCurrency((float) $booking->total_price),
                ])->all(),
            ],
            [
                'title' => 'Provider Documents',
                'headers' => ['Document Status', 'Providers'],
                'rows' => array_map(fn (array $document) => [
                    $document['label'],
                    (string) $document['count'],
                ], $reportSummary['documents']),
            ],
            [
                'title' => 'Service Report',
                'headers' => ['Service Status', 'Count'],
                'rows' => array_map(fn (array $serviceStatus) => [
                    $serviceStatus['label'],
                    (string) $serviceStatus['count'],
                ], $reportSummary['services']),
            ],
            [
                'title' => 'Top Services',
                'headers' => ['Service', 'Provider', 'Status', 'Bookings'],
                'rows' => $reportSummary['top_services']->map(fn (Service $service) => [
                    (string) $service->title,
                    (string) ($service->provider?->name ?? '-'),
                    ucwords(str_replace('_', ' ', (string) $service->status)),
                    (string) ((int) $service->bookings_count),
                ])->all(),
            ],
            [
                'title' => 'User Roles',
                'headers' => ['Role', 'Total'],
                'rows' => array_map(fn (array $role) => [
                    $role['label'],
                    (string) $role['count'],
                ], $reportSummary['roles']),
            ],
        ];
    }

    private function downloadCsv(array $sections, string $filename)
    {
        return response()->streamDownload(function () use ($sections) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($sections as $section) {
                fputcsv($handle, [$section['title']]);
                fputcsv($handle, $section['headers']);

                foreach ($section['rows'] as $row) {
                    fputcsv($handle, $row);
                }

                fputcsv($handle, []);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadExcel(array $sections, string $filename)
    {
        $html = '<!doctype html><html><head><meta charset="UTF-8"></head><body>';
        $html .= '<h1>JasaKu Dashboard Export</h1>';
        $html .= '<p>Generated ' . e(now()->format('d M Y H:i')) . '</p>';

        foreach ($sections as $section) {
            $html .= '<h2>' . e($section['title']) . '</h2><table border="1" cellspacing="0" cellpadding="6"><thead><tr>';

            foreach ($section['headers'] as $header) {
                $html .= '<th>' . e($header) . '</th>';
            }

            $html .= '</tr></thead><tbody>';

            foreach ($section['rows'] as $row) {
                $html .= '<tr>';

                foreach ($row as $cell) {
                    $html .= '<td>' . e((string) $cell) . '</td>';
                }

                $html .= '</tr>';
            }

            $html .= '</tbody></table><br>';
        }

        $html .= '</body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function downloadPdf(array $sections, string $filename)
    {
        return response($this->buildPdf($sections), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildPdf(array $sections): string
    {
        $pages = [[]];
        $pageIndex = 0;
        $y = 800;

        $addLine = function (string $text, int $fontSize = 10, int $lineHeight = 14) use (&$pages, &$pageIndex, &$y) {
            $lines = explode("\n", wordwrap($text, 94, "\n", true));

            foreach ($lines as $line) {
                if ($y < 48) {
                    $pages[] = [];
                    $pageIndex++;
                    $y = 800;
                }

                $pages[$pageIndex][] = sprintf(
                    "BT /F1 %d Tf 1 0 0 1 42 %.2F Tm (%s) Tj ET\n",
                    $fontSize,
                    $y,
                    $this->escapePdfText($line)
                );

                $y -= $lineHeight;
            }
        };

        $addLine('JasaKu Dashboard Export', 18, 24);
        $addLine('Generated ' . now()->format('d M Y H:i'), 10, 22);

        foreach ($sections as $section) {
            if ($y < 90) {
                $pages[] = [];
                $pageIndex++;
                $y = 800;
            }

            $addLine((string) $section['title'], 14, 18);
            $addLine(implode(' | ', $section['headers']), 9, 13);

            foreach ($section['rows'] as $row) {
                $addLine(implode(' | ', array_map(fn ($cell) => (string) $cell, $row)), 9, 12);
            }

            $y -= 8;
        }

        return $this->pdfFromPageStreams(array_map(fn (array $page) => implode('', $page), $pages));
    }

    private function pdfFromPageStreams(array $pageStreams): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $kids = [];

        foreach ($pageStreams as $index => $stream) {
            $pageObject = 4 + ($index * 2);
            $contentObject = $pageObject + 1;
            $kids[] = $pageObject . ' 0 R';

            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
            $objects[$contentObject] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pageStreams) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= $maxObject; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

            if ($converted !== false) {
                $text = $converted;
            }
        }

        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function providerOwners(): Builder
    {
        return $this->getAdminDashboardStats->providerOwners();
    }

    private function bookingAmount(Builder $query): float
    {
        return $this->getAdminDashboardStats->bookingAmount($query);
    }

    private function barSet(array $monthlyBuckets, string $key): array
    {
        $max = max([1, ...array_map(fn (array $bucket) => (float) ($bucket[$key] ?? 0), $monthlyBuckets)]);

        return array_map(function (array $bucket) use ($key, $max) {
            $value = (float) ($bucket[$key] ?? 0);

            return [
                'label' => $bucket['label'],
                'value' => $value,
                'height' => $this->barHeight($value, $max),
            ];
        }, $monthlyBuckets);
    }

    private function barHeight(float $value, float $max): int
    {
        if ($max <= 0) {
            return 14;
        }

        return (int) round(14 + (($value / $max) * 24));
    }

    private function chartPoints(array $values, float $max): array
    {
        $count = max(1, count($values));
        $width = 760;
        $top = 46;
        $bottom = 245;

        return array_values(array_map(function ($value, int $index) use ($count, $width, $top, $bottom, $max) {
            $x = $count === 1 ? 0 : round(($width / ($count - 1)) * $index, 2);
            $ratio = $max > 0 ? min(1, ((float) $value) / $max) : 0;
            $y = round($bottom - ($ratio * ($bottom - $top)), 2);

            return ['x' => $x, 'y' => $y];
        }, $values, array_keys($values)));
    }

    private function linePath(array $points): string
    {
        if ($points === []) {
            return '';
        }

        return collect($points)
            ->map(fn (array $point, int $index) => ($index === 0 ? 'M' : 'L') . $point['x'] . ' ' . $point['y'])
            ->implode(' ');
    }

    private function areaPath(array $points): string
    {
        $path = $this->linePath($points);

        if ($path === '') {
            return '';
        }

        return $path . ' L 760 260 L 0 260 Z';
    }

    private function formatDate(mixed $value): string
    {
        if (empty($value)) {
            return '-';
        }

        return Carbon::parse($value)->format('d M Y');
    }

    private function formatCurrency(float|int|string|null $value): string
    {
        return 'Rp' . number_format((float) $value, 0, ',', '.');
    }
}
