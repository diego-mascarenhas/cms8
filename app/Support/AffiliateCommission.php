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
        $team = self::platformTeam();

        if ($team !== null)
        {
            $stored = $team->getSetting('affiliate_commission_percent');

            if ($stored !== null && $stored !== '')
            {
                return self::clampPercent((float) $stored);
            }
        }

        return self::clampPercent((float) config('humano_pricing.affiliate_commission_percent', 30));
    }

    public static function displayPercent(): string
    {
        $percent = self::percent();

        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
    }

    private static function clampPercent(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
