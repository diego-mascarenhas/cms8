<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Support\Facades\Schema;

class AffiliateCommission
{
    public static function platformTeamId(): int
    {
        return (int) config('humano_pricing.platform_team_id', 0);
    }

    public static function isPlatformTeam(Team $team): bool
    {
        $platformTeamId = self::platformTeamId();

        return $platformTeamId > 0 && (int) $team->id === $platformTeamId;
    }

    public static function platformTeam(): ?Team
    {
        $platformTeamId = self::platformTeamId();

        if ($platformTeamId <= 0 || ! Schema::hasTable('teams'))
        {
            return null;
        }

        return Team::withoutGlobalScopes()->find($platformTeamId);
    }

    public static function percent(): float
    {
        return self::resolvePercent('affiliate_commission_percent', 30);
    }

    public static function agencyPercent(): float
    {
        return self::resolvePercent('agency_commission_percent', 10);
    }

    public static function displayPercent(): string
    {
        return self::formatPercent(self::percent());
    }

    public static function displayAgencyPercent(): string
    {
        return self::formatPercent(self::agencyPercent());
    }

    private static function resolvePercent(string $key, float $default): float
    {
        $team = self::platformTeam();

        if ($team !== null)
        {
            $stored = $team->getSetting($key);

            if ($stored !== null && $stored !== '')
            {
                return self::clampPercent((float) $stored);
            }
        }

        return self::clampPercent((float) config('humano_pricing.'.$key, $default));
    }

    private static function formatPercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
    }

    private static function clampPercent(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
