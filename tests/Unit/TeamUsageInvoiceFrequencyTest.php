<?php

namespace Tests\Unit;

use App\Support\TeamUsageInvoiceFrequency;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TeamUsageInvoiceFrequencyTest extends TestCase
{
    public function test_next_monthly_anniversary_clamps_days_missing_in_shorter_months(): void
    {
        $this->assertSame(
            '2026-02-28',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2026-01-31'), 31)->toDateString(),
        );
        $this->assertSame(
            '2026-03-31',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2026-02-28'), 31)->toDateString(),
        );
        $this->assertSame(
            '2026-04-30',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2026-03-31'), 31)->toDateString(),
        );
        $this->assertSame(
            '2026-05-31',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2026-04-30'), 31)->toDateString(),
        );
        $this->assertSame(
            '2026-02-28',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2026-01-30'), 30)->toDateString(),
        );
        $this->assertSame(
            '2026-03-30',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2026-02-28'), 30)->toDateString(),
        );
        $this->assertSame(
            '2026-02-28',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2026-01-29'), 29)->toDateString(),
        );
        $this->assertSame(
            '2028-02-29',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2028-01-31'), 31)->toDateString(),
        );
        $this->assertSame(
            '2028-03-31',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2028-02-29'), 31)->toDateString(),
        );
        $this->assertSame(
            '2028-02-29',
            TeamUsageInvoiceFrequency::nextMonthlyAnniversary(Carbon::parse('2028-01-29'), 29)->toDateString(),
        );
    }

    public function test_weekly_window_repeats_from_the_change_weekday(): void
    {
        $start = Carbon::parse('2026-09-23 00:00:00');

        [$from, $closesOn] = TeamUsageInvoiceFrequency::weeklyWindow($start, Carbon::parse('2026-09-25 18:00:00'));
        $this->assertSame('2026-09-23', $from->toDateString());
        $this->assertSame('2026-09-30', $closesOn->toDateString());

        [$from, $closesOn] = TeamUsageInvoiceFrequency::weeklyWindow($start, Carbon::parse('2026-10-01 09:00:00'));
        $this->assertSame('2026-09-30', $from->toDateString());
        $this->assertSame('2026-10-07', $closesOn->toDateString());
    }

    public function test_monthly_window_uses_the_change_day_as_anniversary(): void
    {
        $start = Carbon::parse('2026-09-15 00:00:00');

        [$from, $closesOn] = TeamUsageInvoiceFrequency::monthlyWindow($start, 15, Carbon::parse('2026-09-20 12:00:00'));
        $this->assertSame('2026-09-15', $from->toDateString());
        $this->assertSame('2026-10-15', $closesOn->toDateString());

        [$from, $closesOn] = TeamUsageInvoiceFrequency::monthlyWindow($start, 15, Carbon::parse('2026-10-15 00:00:00'));
        $this->assertSame('2026-10-15', $from->toDateString());
        $this->assertSame('2026-11-15', $closesOn->toDateString());
    }
}
