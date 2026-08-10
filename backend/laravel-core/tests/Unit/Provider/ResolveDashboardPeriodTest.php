<?php

namespace Tests\Unit\Provider;

use App\Modules\Provider\Application\Queries\ResolveDashboardPeriod;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ResolveDashboardPeriodTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_preserves_dashboard_period_ranges_and_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));

        $period = (new ResolveDashboardPeriod())->handle('not-supported');

        $this->assertSame('6m', $period->selected);
        $this->assertSame('Last 6 months', $period->label);
        $this->assertSame('2026-03-01 00:00:00', $period->start->toDateTimeString());
        $this->assertSame('2026-08-10 23:59:59', $period->end->toDateTimeString());
        $this->assertSame('2025-09-19 00:00:00', $period->previousStart->toDateTimeString());
        $this->assertSame('2026-02-28 23:59:59', $period->previousEnd->toDateTimeString());
        $this->assertSame(['7d', '30d', '6m', 'year'], array_keys($period->options));
    }
}
