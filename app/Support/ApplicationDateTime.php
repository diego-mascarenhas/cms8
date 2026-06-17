<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

final class ApplicationDateTime
{
    public static function carbon(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '')
        {
            return null;
        }

        try
        {
            if ($value instanceof CarbonInterface)
            {
                return $value->copy()
                    ->timezone(config('app.timezone'))
                    ->locale(self::carbonLocale());
            }

            if ($value instanceof DateTimeInterface)
            {
                return Carbon::instance($value)
                    ->timezone(config('app.timezone'))
                    ->locale(self::carbonLocale());
            }

            return Carbon::parse($value)
                ->timezone(config('app.timezone'))
                ->locale(self::carbonLocale());
        } catch (\Throwable)
        {
            return null;
        }
    }

    public static function carbonLocale(): string
    {
        return ApplicationLocales::javascriptLocale(app()->getLocale());
    }

    public static function formatDateTime(mixed $value, string $fallback = '—'): string
    {
        $date = self::carbon($value);

        if ($date !== null)
        {
            return $date->isoFormat('D MMM YYYY, HH:mm');
        }

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    public static function formatListDateTime(mixed $value, string $fallback = '—'): string
    {
        $date = self::carbon($value);

        if ($date === null)
        {
            return is_string($value) && $value !== '' ? $value : $fallback;
        }

        $now = now()->timezone(config('app.timezone'));

        if ($date->isSameDay($now))
        {
            return $date->isoFormat('HH:mm');
        }

        if ($date->isSameYear($now))
        {
            return $date->isoFormat('D MMM');
        }

        return $date->isoFormat('D MMM YYYY');
    }

    public static function formatShortDateTime(mixed $value, string $fallback = '—'): string
    {
        $date = self::carbon($value);

        if ($date !== null)
        {
            return $date->isoFormat('D MMM, HH:mm');
        }

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
