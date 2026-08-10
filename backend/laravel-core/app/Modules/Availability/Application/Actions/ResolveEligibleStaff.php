<?php

namespace App\Modules\Availability\Application\Actions;

use App\Modules\Branch\Infrastructure\Persistence\Models\ProviderBranch;
use App\Modules\Staff\Infrastructure\Persistence\Models\ProviderStaff;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResolveEligibleStaff
{
    public function execute(
        ProviderBranch $branch,
        Collection $services,
        ?string $date = null,
        ?int $staffId = null
    ): Collection {
        $serviceIds = $services->pluck('id')->map(fn ($id) => (int) $id)->all();

        return ProviderStaff::query()
            ->with(['branch', 'skills:id,title', 'schedules'])
            ->where('provider_id', $branch->provider_id)
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->where('current_status', '!=', 'offline')
            ->when($staffId, fn ($query) => $query->whereKey($staffId))
            ->orderBy('first_name')
            ->get()
            ->filter(function (ProviderStaff $staff) use ($branch, $serviceIds, $date) {
                $skillIds = $staff->skills->pluck('id')->map(fn ($id) => (int) $id)->all();

                if (count(array_intersect($serviceIds, $skillIds)) !== count($serviceIds)) {
                    return false;
                }

                return ! $date || $this->isStaffWorking($branch, $staff, $date);
            })
            ->values();
    }

    private function isStaffWorking(ProviderBranch $branch, ProviderStaff $staff, string $date): bool
    {
        return count($this->workingWindows($branch, $staff, $date)) > 0;
    }

    private function workingWindows(ProviderBranch $branch, ProviderStaff $staff, string $date): array
    {
        if (! $this->branchWorksOnDate($branch, $date)) {
            return [];
        }

        $dayAliases = $this->dayAliases(Carbon::parse($date));
        $schedules = $staff->schedules
            ->filter(function ($schedule) use ($dayAliases) {
                return $schedule->is_available
                    && in_array(Str::lower((string) $schedule->day_of_week), $dayAliases, true);
            })
            ->values();

        if ($schedules->isEmpty()) {
            return [[
                'start' => $this->shortTime($branch->working_start_hour ?: '09:00'),
                'end' => $this->shortTime($branch->working_end_hour ?: '18:00'),
            ]];
        }

        $branchStart = $this->shortTime($branch->working_start_hour ?: '09:00');
        $branchEnd = $this->shortTime($branch->working_end_hour ?: '18:00');

        return $schedules
            ->map(function ($schedule) use ($branchStart, $branchEnd) {
                $start = max($this->shortTime($schedule->start_time), $branchStart);
                $end = min($this->shortTime($schedule->end_time), $branchEnd);

                return compact('start', 'end');
            })
            ->filter(fn (array $window) => $window['start'] < $window['end'])
            ->values()
            ->all();
    }

    private function branchWorksOnDate(ProviderBranch $branch, string $date): bool
    {
        $workingDays = collect((array) $branch->working_days)->map(fn ($day) => Str::lower((string) $day))->all();

        if (empty($workingDays)) {
            return true;
        }

        return count(array_intersect($workingDays, $this->dayAliases(Carbon::parse($date)))) > 0;
    }

    private function dayAliases(Carbon $date): array
    {
        $aliases = [
            0 => ['0', 'sunday', 'sun', 'minggu', 'ahad'],
            1 => ['1', 'monday', 'mon', 'senin'],
            2 => ['2', 'tuesday', 'tue', 'selasa'],
            3 => ['3', 'wednesday', 'wed', 'rabu'],
            4 => ['4', 'thursday', 'thu', 'kamis'],
            5 => ['5', 'friday', 'fri', 'jumat', "jum'at"],
            6 => ['6', 'saturday', 'sat', 'sabtu'],
        ];

        return $aliases[$date->dayOfWeek] ?? [];
    }

    private function shortTime(mixed $value): string
    {
        return substr((string) $value, 0, 5);
    }
}
