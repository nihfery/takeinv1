<?php

namespace App\Modules\Provider\Application\Queries;

use App\Modules\Provider\Application\DTOs\DashboardPeriod;
use Carbon\Carbon;

final class ResolveDashboardPeriod
{
    public function handle(?string $requestedPeriod): DashboardPeriod
    {
        $options = [
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '6m' => 'Last 6 months',
            'year' => 'This year',
        ];
        $selected = array_key_exists((string) $requestedPeriod, $options)
            ? (string) $requestedPeriod
            : '6m';
        $end = now()->endOfDay();
        $start = match ($selected) {
            '7d' => $end->copy()->subDays(6)->startOfDay(),
            '30d' => $end->copy()->subDays(29)->startOfDay(),
            'year' => $end->copy()->startOfYear(),
            default => $end->copy()->subMonths(5)->startOfMonth(),
        };
        [$previousStart, $previousEnd] = $this->previousRange($start, $end);

        return new DashboardPeriod(
            selected: $selected,
            label: $options[$selected],
            options: $options,
            start: $start,
            end: $end,
            previousStart: $previousStart,
            previousEnd: $previousEnd,
        );
    }

    private function previousRange(Carbon $start, Carbon $end): array
    {
        $days = max(1, (int) $start->diffInDays($end) + 1);
        $previousEnd = $start->copy()->subSecond()->endOfDay();

        return [
            $previousEnd->copy()->subDays($days - 1)->startOfDay(),
            $previousEnd,
        ];
    }
}
