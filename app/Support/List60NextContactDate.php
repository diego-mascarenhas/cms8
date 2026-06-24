<?php

namespace App\Support;

use Carbon\Carbon;

class List60NextContactDate
{
    public const DEFAULT_BUSINESS_DAYS = 7;

    public static function afterOutreach(?Carbon $from = null): Carbon
    {
        return self::addBusinessDays($from ?? now(), self::DEFAULT_BUSINESS_DAYS);
    }

    public static function addBusinessDays(Carbon $from, int $businessDays): Carbon
    {
        $nextDate = $from->copy();
        $count = 0;

        while ($count < $businessDays)
        {
            $nextDate = $nextDate->addDay();

            if (! $nextDate->isWeekend())
            {
                $count++;
            }
        }

        return $nextDate;
    }
}
