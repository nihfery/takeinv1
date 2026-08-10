<?php

namespace App\Modules\Booking\Presentation\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Infrastructure\Persistence\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $view = $request->get('view', 'month');
        $view = in_array($view, ['month', 'week', 'day'], true) ? $view : 'month';
        $dateParam = $request->get('date');
        $dayLabels = [
            'Sun' => 'Sun',
            'Mon' => 'Sen',
            'Tue' => 'Tue',
            'Wed' => 'Wed',
            'Thu' => 'Thu',
            'Fri' => 'Fri',
            'Sat' => 'Sat',
        ];
        $fullDayLabels = [
            'Sun' => 'Sunday',
            'Mon' => 'Monday',
            'Tue' => 'Tuesday',
            'Wed' => 'Wednesday',
            'Thu' => 'Thursday',
            'Fri' => 'Friday',
            'Sat' => 'Saturday',
        ];
        $monthNames = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
        $shortMonthNames = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
        ];

        try {
            $baseDate = $dateParam
                ? Carbon::parse($dateParam)->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable) {
            $baseDate = now()->startOfDay();
        }

        // Navigation date
        $prevDate = $baseDate->copy();
        $nextDate = $baseDate->copy();

        if ($view === 'month') {
            $prevDate->subMonth();
            $nextDate->addMonth();
        } elseif ($view === 'week') {
            $prevDate->subWeek();
            $nextDate->addWeek();
        } else {
            $prevDate->subDay();
            $nextDate->addDay();
        }

        // Booking query range
        $rangeStart = $baseDate->copy();
        $rangeEnd = $baseDate->copy();

        if ($view === 'month') {
            $rangeStart = $baseDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
            $rangeEnd = $baseDate->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        } elseif ($view === 'week') {
            $rangeStart = $baseDate->copy()->startOfWeek(Carbon::SUNDAY);
            $rangeEnd = $baseDate->copy()->endOfWeek(Carbon::SATURDAY);
        }

        $bookings = Booking::with(['provider', 'customer', 'services', 'branch', 'staff', 'payment'])
            ->whereBetween('booking_date', [
                $rangeStart->format('Y-m-d'),
                $rangeEnd->format('Y-m-d'),
            ])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $eventsByDate = $bookings->groupBy(function ($booking) {
            return Carbon::parse($booking->booking_date)->format('Y-m-d');
        });

        // Month data
        $monthWeeks = [];
        if ($view === 'month') {
            $monthStart = $baseDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
            $monthEnd = $baseDate->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

            $cursor = $monthStart->copy();
            $week = [];

            while ($cursor->lte($monthEnd)) {
                $week[] = [
                    'date' => $cursor->copy(),
                    'day' => $cursor->day,
                    'is_current_month' => $cursor->month === $baseDate->month,
                    'is_today' => $cursor->isToday(),
                ];

                if (count($week) === 7) {
                    $monthWeeks[] = $week;
                    $week = [];
                }

                $cursor->addDay();
            }
        }

        // Week data
        $weekDays = [];
        if ($view === 'week') {
            $weekStart = $baseDate->copy()->startOfWeek(Carbon::SUNDAY);
            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->copy()->addDays($i);
                $dayKey = $day->format('D');
                $weekDays[] = [
                    'date' => $day,
                    'label' => $dayLabels[$dayKey] ?? $dayKey,
                    'label_full' => ($dayLabels[$dayKey] ?? $dayKey) . ' ' . $day->format('j') . ' ' . $shortMonthNames[$day->month],
                    'is_today' => $day->isToday(),
                ];
            }
        }

        // Day data
        $dayData = null;
        if ($view === 'day') {
            $dayKey = $baseDate->format('D');
            $dayData = [
                'date' => $baseDate->copy(),
                'label' => $fullDayLabels[$dayKey] ?? $baseDate->format('l'),
                'is_today' => $baseDate->isToday(),
            ];
        }

        // Time labels, 00:00 - 23:00.
        $timeSlots = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $timeSlots[] = [
                'hour' => $hour,
                'label' => Carbon::createFromTime($hour, 0)->format('H:i'),
            ];
        }

        // Title
        if ($view === 'month') {
            $calendarTitle = strtoupper($monthNames[$baseDate->month] . ' ' . $baseDate->year);
        } elseif ($view === 'week') {
            $weekStart = $baseDate->copy()->startOfWeek(Carbon::SUNDAY);
            $weekEnd = $baseDate->copy()->endOfWeek(Carbon::SATURDAY);

            if ($weekStart->month === $weekEnd->month && $weekStart->year === $weekEnd->year) {
                $calendarTitle = strtoupper(
                    $weekStart->format('j') . ' - ' . $weekEnd->format('j') . ' ' . $monthNames[$weekStart->month] . ' ' . $weekStart->year
                );
            } else {
                $calendarTitle = strtoupper(
                    $weekStart->format('j') . ' ' . $shortMonthNames[$weekStart->month] . ' - ' . $weekEnd->format('j') . ' ' . $shortMonthNames[$weekEnd->month] . ' ' . $weekEnd->year
                );
            }
        } else {
            $calendarTitle = strtoupper($dayData['label'] . ', ' . $baseDate->format('j') . ' ' . $monthNames[$baseDate->month] . ' ' . $baseDate->year);
        }

        $calendarDays = [];

        if ($view === 'month') {
            $cursor = $baseDate->copy()->startOfMonth();
            $monthEnd = $baseDate->copy()->endOfMonth();

            while ($cursor->lte($monthEnd)) {
                $dateKey = $cursor->format('Y-m-d');

                $calendarDays[] = [
                    'date' => $cursor->copy(),
                    'label' => $dayLabels[$cursor->format('D')] ?? $cursor->format('D'),
                    'day' => $cursor->format('j'),
                    'count' => ($eventsByDate[$dateKey] ?? collect())->count(),
                    'is_today' => $cursor->isToday(),
                ];

                $cursor->addDay();
            }
        } elseif ($view === 'week') {
            foreach ($weekDays as $day) {
                $dateKey = $day['date']->format('Y-m-d');

                $calendarDays[] = [
                    'date' => $day['date']->copy(),
                    'label' => $day['label'],
                    'day' => $day['date']->format('j'),
                    'count' => ($eventsByDate[$dateKey] ?? collect())->count(),
                    'is_today' => $day['is_today'],
                ];
            }
        } else {
            $dateKey = $dayData['date']->format('Y-m-d');

                $calendarDays[] = [
                    'date' => $dayData['date']->copy(),
                    'label' => $dayLabels[$dayData['date']->format('D')] ?? $dayData['date']->format('D'),
                    'day' => $dayData['date']->format('j'),
                    'count' => ($eventsByDate[$dateKey] ?? collect())->count(),
                    'is_today' => $dayData['is_today'],
            ];
        }

        $calendarAgenda = $bookings
            ->sortBy(function ($booking) {
                $date = $booking->booking_date ? Carbon::parse($booking->booking_date)->format('Y-m-d') : '9999-12-31';
                $time = $booking->start_time ?? '23:59:59';

                return $date . ' ' . $time . ' ' . str_pad((string) $booking->id, 10, '0', STR_PAD_LEFT);
            })
            ->values();

        return view('admin.calendar.index', compact(
            'view',
            'baseDate',
            'prevDate',
            'nextDate',
            'calendarTitle',
            'calendarAgenda',
            'calendarDays',
            'eventsByDate',
            'monthWeeks',
            'weekDays',
            'dayData',
            'timeSlots'
        ));
    }
}
