<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CalendarEventDateTimeParser
{
    public static function wallClockTimezone(): string
    {
        return (string) config('calendar.wall_clock_timezone', 'Europe/Madrid');
    }

    /**
     * Parse a calendar datetime for DB storage (UTC), matching the calendar UI semantics.
     */
    public static function parseForStorage(string $value): Carbon
    {
        $value = trim($value);
        if ($value === '')
        {
            throw new InvalidArgumentException('Empty datetime.');
        }

        if (self::hasExplicitTimezone($value))
        {
            return Carbon::parse($value)->utc();
        }

        return Carbon::parse($value, self::wallClockTimezone())->utc();
    }

    public static function formatWallClock(CarbonInterface $instant): string
    {
        return $instant->copy()->timezone(self::wallClockTimezone())->format('Y-m-d H:i');
    }

    private static function hasExplicitTimezone(string $value): bool
    {
        return (bool) preg_match('/(?:Z|[+-]\d{2}(?::?\d{2})?)$/i', $value);
    }
}
