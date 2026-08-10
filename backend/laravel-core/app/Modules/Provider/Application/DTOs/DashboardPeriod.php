<?php

namespace App\Modules\Provider\Application\DTOs;

use Carbon\Carbon;

final readonly class DashboardPeriod
{
    public function __construct(
        public string $selected,
        public string $label,
        public array $options,
        public Carbon $start,
        public Carbon $end,
        public Carbon $previousStart,
        public Carbon $previousEnd,
    ) {
    }
}
