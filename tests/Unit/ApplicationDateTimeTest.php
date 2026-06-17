<?php

namespace Tests\Unit;

use App\Support\ApplicationDateTime;
use Carbon\Carbon;
use Tests\TestCase;

class ApplicationDateTimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_format_datetime_uses_system_locale_and_twenty_four_hour_clock(): void
    {
        app()->setLocale('es_ES');
        Carbon::setTestNow(Carbon::parse('2026-06-16 10:00:00', config('app.timezone')));

        $formatted = ApplicationDateTime::formatDateTime('2026-06-16 14:30:00');

        $this->assertSame('16 jun. 2026, 14:30', $formatted);
    }

    public function test_format_list_datetime_shows_time_for_today_and_short_date_otherwise(): void
    {
        app()->setLocale('es_ES');
        Carbon::setTestNow(Carbon::parse('2026-06-16 10:00:00', config('app.timezone')));

        $this->assertSame('14:30', ApplicationDateTime::formatListDateTime('2026-06-16 14:30:00'));
        $this->assertSame('15 may.', ApplicationDateTime::formatListDateTime('2026-05-15 09:00:00'));
        $this->assertSame('3 mar. 2025', ApplicationDateTime::formatListDateTime('2025-03-03 09:00:00'));
    }

    public function test_format_short_datetime_includes_date_and_time(): void
    {
        app()->setLocale('es_ES');

        $formatted = ApplicationDateTime::formatShortDateTime('2026-06-16 14:30:00');

        $this->assertSame('16 jun., 14:30', $formatted);
    }
}
