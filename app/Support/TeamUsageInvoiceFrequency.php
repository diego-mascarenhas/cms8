<?php

namespace App\Support;

use App\Enums\TeamBillingFrequency;
use App\Models\Team;
use App\Models\TeamUsageInvoiceAdjustment;
use Carbon\Carbon;

class TeamUsageInvoiceFrequency
{
    public const SETTING_KEY = 'usage_invoice_frequency';

    public const PERIOD_STARTS_AT_KEY = 'usage_invoice_period_starts_at';

    public const ANCHOR_DAY_KEY = 'usage_invoice_anchor_day';

    public static function for(Team $team): TeamBillingFrequency
    {
        return TeamBillingFrequency::tryFrom((string) $team->getSetting(self::SETTING_KEY, TeamBillingFrequency::Monthly->value))
            ?? TeamBillingFrequency::Monthly;
    }

    public static function periodStartsAt(Team $team): ?Carbon
    {
        $value = $team->getSetting(self::PERIOD_STARTS_AT_KEY);

        if (! is_string($value) || trim($value) === '')
        {
            return null;
        }

        return Carbon::parse($value);
    }

    public static function anchorDay(Team $team): ?int
    {
        $value = $team->getSetting(self::ANCHOR_DAY_KEY);

        if ($value === null || $value === '')
        {
            return null;
        }

        $day = (int) $value;

        return $day >= 1 && $day <= 31 ? $day : null;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function window(Team $team, ?TeamBillingFrequency $frequency = null, ?Carbon $now = null): array
    {
        $frequency ??= self::for($team);
        $now = ($now ?? now())->copy();
        $cycleStart = self::periodStartsAt($team);

        if ($cycleStart === null)
        {
            return self::calendarWindow($frequency, $now);
        }

        if ($frequency === TeamBillingFrequency::Weekly)
        {
            return self::weeklyWindow($cycleStart, $now);
        }

        return self::monthlyWindow($cycleStart, self::anchorDay($team) ?? $cycleStart->day, $now);
    }

    public static function nextMonthlyAnniversary(Carbon $from, int $anchorDay): Carbon
    {
        $anchorDay = min(31, max(1, $anchorDay));
        $month = $from->month + 1;
        $year = $from->year;
        if ($month > 12)
        {
            $month = 1;
            $year++;
        }

        $day = min($anchorDay, Carbon::create($year, $month, 1)->daysInMonth);

        return Carbon::create($year, $month, $day, 0, 0, 0, $from->timezone);
    }

    public static function set(Team $team, TeamBillingFrequency $frequency): void
    {
        $previous = self::for($team);
        $now = now()->copy();

        if ($previous !== $frequency)
        {
            self::closeOpenPeriod($team, $previous, $frequency, $now);
        }

        $team->setSetting(self::SETTING_KEY, $frequency->value, [
            'type' => 'string',
            'group' => 'billing',
        ]);
        $team->unsetRelation('settings');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function calendarWindow(TeamBillingFrequency $frequency, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();

        if ($frequency === TeamBillingFrequency::Weekly)
        {
            $from = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

            return [$from, $from->copy()->addWeek()];
        }

        $from = $now->copy()->startOfMonth();

        return [$from, $from->copy()->addMonth()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function weeklyWindow(Carbon $cycleStart, Carbon $now): array
    {
        $from = $cycleStart->copy()->startOfDay();
        $closesOn = $from->copy()->addWeek();
        $guard = 0;

        while ($closesOn->lte($now) && $guard < 260)
        {
            $from = $closesOn->copy();
            $closesOn = $from->copy()->addWeek();
            $guard++;
        }

        return [$from, $closesOn];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function monthlyWindow(Carbon $cycleStart, int $anchorDay, Carbon $now): array
    {
        $from = $cycleStart->copy()->startOfDay();
        $closesOn = self::nextMonthlyAnniversary($from, $anchorDay);
        $guard = 0;

        while ($closesOn->lte($now) && $guard < 36)
        {
            $from = $closesOn->copy();
            $closesOn = self::nextMonthlyAnniversary($from, $anchorDay);
            $guard++;
        }

        return [$from, $closesOn];
    }

    private static function closeOpenPeriod(
        Team $team,
        TeamBillingFrequency $previous,
        TeamBillingFrequency $next,
        Carbon $now,
    ): void {
        [$currentFrom] = self::window($team, $previous, $now);
        $nextStart = $now->copy()->startOfDay();

        if ($currentFrom->lt($nextStart))
        {
            TeamUsageInvoiceAdjustment::query()->create([
                'team_id' => $team->id,
                'frequency' => $previous,
                'period_from' => $currentFrom,
                'period_to' => $nextStart,
            ]);
        }

        self::setPeriodStartsAt($team, $nextStart);

        if ($next === TeamBillingFrequency::Monthly)
        {
            self::setAnchorDay($team, $nextStart->day);
        }
    }

    private static function setPeriodStartsAt(Team $team, Carbon $from): void
    {
        $team->setSetting(self::PERIOD_STARTS_AT_KEY, $from->copy()->startOfDay()->toIso8601String(), [
            'type' => 'string',
            'group' => 'billing',
        ]);
        $team->unsetRelation('settings');
    }

    private static function setAnchorDay(Team $team, int $day): void
    {
        $team->setSetting(self::ANCHOR_DAY_KEY, (string) min(31, max(1, $day)), [
            'type' => 'string',
            'group' => 'billing',
        ]);
        $team->unsetRelation('settings');
    }
}
