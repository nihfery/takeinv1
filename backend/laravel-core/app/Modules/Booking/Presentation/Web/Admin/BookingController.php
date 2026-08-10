<?php

namespace App\Modules\Booking\Presentation\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $status = (string) $request->get('status', 'all');
        $search = trim((string) $request->get('search', ''));
        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $allowedStatuses = [
            'all',
            'open',
            'pending',
            'pending_hold',
            'expired_hold',
            'payment_expired',
            'pending_payment',
            'confirmed',
            'waiting',
            'checked_in',
            'in_progress',
            'inprogress',
            'completed',
            'order_completed',
            'refund_completed',
            'provider_cancelled',
            'customer_cancelled',
            'rescheduled',
            'cancelled',
            'no_show',
        ];

        $paymentStatuses = [
            'all' => 'All Payments',
            'unpaid' => 'Unpaid',
            'pending' => 'Pending',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'expired' => 'Expired',
        ];

        $bookingTypes = [
            'all' => 'All Modes',
            'scheduled' => 'Scheduled',
            'queue' => 'Queue',
            'walk_in' => 'Walk In',
        ];

        $sortOptions = [
            'booking_date' => 'Appointment Date',
            'created_at' => 'Created Date',
            'amount' => 'Total Amount',
            'payment_status' => 'Payment Status',
            'status' => 'Booking Status',
            'booking_type' => 'Mode',
            'booking_code' => 'Booking Code',
        ];

        $status = in_array($status, $allowedStatuses, true) ? $status : 'all';

        $paymentStatus = (string) $request->get('payment_status', 'all');
        $paymentStatus = array_key_exists($paymentStatus, $paymentStatuses) ? $paymentStatus : 'all';

        $bookingType = (string) $request->get('booking_type', 'all');
        $bookingType = array_key_exists($bookingType, $bookingTypes) ? $bookingType : 'all';

        $dateFrom = $request->filled('date_from') ? (string) $request->get('date_from') : null;
        $dateTo = $request->filled('date_to') ? (string) $request->get('date_to') : null;

        if ($dateFrom && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = null;
        }

        if ($dateTo && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = null;
        }

        $sortBy = (string) $request->get('sort_by', 'booking_date');
        $sortBy = array_key_exists($sortBy, $sortOptions) ? $sortBy : 'booking_date';

        $sortDirection = strtolower((string) $request->get('sort_direction', 'desc'));
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc';

        $query = Booking::with([
            'provider',
            'customer',
            'services',
            'branch',
            'staff',
            'payment',
        ]);

        $summaryQuery = Booking::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($paymentStatus !== 'all') {
            $query->whereHas('payment', fn (Builder $paymentQuery) => $paymentQuery->where('status', $paymentStatus));
        }

        if ($bookingType !== 'all') {
            $query->where('booking_type', $bookingType);
        }

        if ($dateFrom) {
            $query->whereDate('booking_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('booking_date', '<=', $dateTo);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('customer_phone', 'like', '%' . $search . '%')
                    ->orWhere('booking_type', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhereHas('payment', fn ($paymentQuery) => $paymentQuery->where('status', 'like', '%' . $search . '%'))
                    ->orWhereHas('provider', function ($providerQuery) use ($search) {
                        $providerQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('services', function ($serviceQuery) use ($search) {
                        $serviceQuery->where('title', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('branch', function ($branchQuery) use ($search) {
                        $branchQuery->where('branch_name', 'like', '%' . $search . '%')
                            ->orWhere('city_id', 'like', '%' . $search . '%');
                    });
            });
        }

        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'paid' => (clone $summaryQuery)
                ->whereHas('payment', fn (Builder $paymentQuery) => $paymentQuery->where('status', 'paid'))
                ->count(),
            'pending' => (clone $summaryQuery)->whereIn('status', ['pending', 'pending_hold', 'pending_payment', 'confirmed', 'waiting', 'checked_in', 'in_progress', 'inprogress'])->count(),
            'completed' => (clone $summaryQuery)->whereIn('status', ['completed', 'order_completed'])->count(),
            'amount' => (float) (clone $summaryQuery)->sum('total_price'),
        ];

        $sortExpressions = [
            'booking_date' => 'booking_date',
            'created_at' => 'created_at',
            'amount' => 'total_price',
            'status' => 'status',
            'booking_type' => 'booking_type',
            'booking_code' => 'booking_code',
        ];

        if ($sortBy === 'payment_status') {
            $query->orderBy(
                Payment::query()
                    ->select('status')
                    ->whereColumn('payments.booking_id', 'bookings.id')
                    ->limit(1),
                $sortDirection
            );
        } else {
            $query->orderByRaw($sortExpressions[$sortBy] . ' ' . $sortDirection);
        }

        $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $bookings = $query->paginate($perPage)->withQueryString();

        $tabs = [
            'all' => 'All Bookings',
            'open' => 'Open',
            'pending' => 'Pending',
            'pending_hold' => 'Hold',
            'pending_payment' => 'Pending Payment',
            'confirmed' => 'Confirmed',
            'waiting' => 'Waiting',
            'checked_in' => 'Checked In',
            'in_progress' => 'In Progress',
            'pending' => 'Pending',
            'inprogress' => 'Inprogress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'expired_hold' => 'Hold Expired',
            'payment_expired' => 'Payment Expired',
            'no_show' => 'No-show',
        ];

        $filters = [
            'status' => $status,
            'search' => $search,
            'per_page' => $perPage,
            'payment_status' => $paymentStatus,
            'booking_type' => $bookingType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];

        $hasActiveFilters = $search !== ''
            || $status !== 'all'
            || $paymentStatus !== 'all'
            || $bookingType !== 'all'
            || ! empty($dateFrom)
            || ! empty($dateTo);

        return view('admin.bookings.index', compact(
            'bookings',
            'tabs',
            'status',
            'search',
            'perPage',
            'filters',
            'paymentStatuses',
            'bookingTypes',
            'sortOptions',
            'sortBy',
            'sortDirection',
            'summary',
            'hasActiveFilters'
        ));
    }
}
