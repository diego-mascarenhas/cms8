<?php

namespace Tests\Unit;

use App\Support\CalendarEventDateTimeParser;
use Carbon\Carbon;
use Tests\TestCase;

class CalendarEventDateTimeParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['calendar.wall_clock_timezone' => 'Europe/Madrid']);
    }

    public function test_naive_datetime_is_interpreted_as_wall_clock_timezone(): void
    {
        $stored = CalendarEventDateTimeParser::parseForStorage('2026-06-10 14:00:00');

        $this->assertSame('12:00:00', $stored->utc()->format('H:i:s'));
    }

    public function test_iso_with_timezone_is_preserved(): void
    {
        $stored = CalendarEventDateTimeParser::parseForStorage('2026-06-10T12:00:00.000Z');

        $this->assertTrue($stored->utc()->equalTo(Carbon::parse('2026-06-10T12:00:00.000Z')->utc()));
    }

    public function test_format_wall_clock_displays_in_configured_timezone(): void
    {
        $instant = Carbon::parse('2026-06-10T12:00:00', 'UTC');

        $this->assertSame('2026-06-10 14:00', CalendarEventDateTimeParser::formatWallClock($instant));
    }
}
