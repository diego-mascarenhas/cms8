<?php

namespace Tests\Unit;

use App\Support\List60NextContactDate;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class List60NextContactDateTest extends TestCase
{
    #[DataProvider('businessDaysProvider')]
    public function test_add_business_days_skips_weekends(string $from, int $days, string $expected): void
    {
        $result = List60NextContactDate::addBusinessDays(Carbon::parse($from), $days);

        $this->assertTrue($result->isSameDay(Carbon::parse($expected)));
    }

    public static function businessDaysProvider(): array
    {
        return [
            'monday plus seven business days' => ['2026-06-22', 7, '2026-07-01'],
            'friday plus one business day' => ['2026-06-26', 1, '2026-06-29'],
        ];
    }

    public function test_after_outreach_uses_default_business_days(): void
    {
        Carbon::setTestNow('2026-06-24 10:00:00');

        $result = List60NextContactDate::afterOutreach();

        $this->assertTrue($result->isSameDay(Carbon::parse('2026-07-03')));

        Carbon::setTestNow();
    }
}
