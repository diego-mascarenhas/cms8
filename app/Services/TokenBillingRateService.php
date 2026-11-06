<?php

namespace App\Services;

use App\Enums\TeamBillingProduct;
use App\Models\ExchangeRate;
use App\Models\Team;
use App\Models\TeamBillingRate;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Token display currency and the client token multiplier.
 * USD is converted with {@see ExchangeRate} when the charge label is not USD.
 */
class TokenBillingRateService
{
    public static function baseCurrency(): string
    {
        return strtoupper((string) config('humano_pricing.token_billing.base_currency', 'USD'));
    }

    public static function displayCurrency(): string
    {
        return strtoupper((string) config('humano_pricing.token_billing.currency', 'USD'));
    }

    public static function toDisplayCents(int $cents, string $fromCurrency, DateTimeInterface|string|null $on = null): int
    {
        $from = strtoupper(trim($fromCurrency));
        $display = self::displayCurrency();
        if ($cents === 0 || $from === '' || $from === $display)
        {
            return $cents;
        }

        $rate = ExchangeRate::rateOnOrBeforeDate($from, $display, self::resolveDate($on));
        if ($rate === null)
        {
            return $cents;
        }

        return (int) round($cents * $rate);
    }

    public static function clientTokenMultiplier(Team|int|null $team = null, DateTimeInterface|string|null $on = null): float
    {
        $teamId = $team instanceof Team ? (int) $team->id : $team;

        return TeamBillingRate::amountOn($teamId, TeamBillingProduct::TokensMultiplier, $on);
    }

    public static function usdToDisplay(DateTimeInterface|string|null $on = null): float
    {
        $base = self::baseCurrency();
        $display = self::displayCurrency();
        if ($base === $display)
        {
            return 1.0;
        }

        $fx = ExchangeRate::rateOnOrBeforeDate($base, $display, self::resolveDate($on));

        return $fx === null ? 1.0 : $fx;
    }

    private static function resolveDate(DateTimeInterface|string|null $on): Carbon
    {
        if ($on instanceof Carbon)
        {
            return $on->copy();
        }

        if ($on instanceof DateTimeInterface)
        {
            return Carbon::instance($on);
        }

        if (is_string($on) && trim($on) !== '')
        {
            return Carbon::parse($on);
        }

        return now();
    }
}
