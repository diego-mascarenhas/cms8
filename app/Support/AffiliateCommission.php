<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\Team;
use App\Services\HumanoPricingPlanResolver;
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

    /**
     * Product referral % for a catalog / subscription type, falling back to the global percent.
     */
    public static function percentForCatalog(?string $catalog): float
    {
        $catalog = strtolower(trim((string) $catalog));
        if ($catalog === '')
        {
            return self::percent();
        }

        $byCatalog = config('humano_pricing.affiliate_commission_percent_by_catalog', []);
        if (! is_array($byCatalog) || ! array_key_exists($catalog, $byCatalog))
        {
            return self::percent();
        }

        $raw = $byCatalog[$catalog];
        if ($raw === null || $raw === '')
        {
            return self::percent();
        }

        return self::clampPercent((float) $raw);
    }

    /**
     * Product referral % for a pricing plan array.
     * Plan key wins, then catalog map, then global.
     *
     * @param  array<string, mixed>|null  $plan
     */
    public static function percentForPlan(?array $plan): float
    {
        if ($plan === null)
        {
            return self::percent();
        }

        if (array_key_exists('affiliate_commission_percent', $plan)
            && $plan['affiliate_commission_percent'] !== null
            && $plan['affiliate_commission_percent'] !== '')
        {
            return self::clampPercent((float) $plan['affiliate_commission_percent']);
        }

        $catalog = trim((string) ($plan['catalog'] ?? ''));

        return self::percentForCatalog($catalog !== '' ? $catalog : null);
    }

    /**
     * Resolve the product referral % for a Cashier subscription (plan → catalog/type → global).
     */
    public static function percentForSubscription(?Subscription $subscription): float
    {
        if ($subscription === null)
        {
            return self::percent();
        }

        $plan = app(HumanoPricingPlanResolver::class)->planForSubscription($subscription);
        if ($plan !== null)
        {
            return self::percentForPlan($plan);
        }

        return self::percentForCatalog((string) ($subscription->type ?? ''));
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
