<?php

namespace App\Services;

use App\Models\ExchangeRate;
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
        return strtoupper((string) config('humano_pricing.token_billing.currency', 'EUR'));
    }

    public static function clientTokenMultiplier(): float
    {
        return max(1, (float) config('humano_pricing.token_billing.client_token_multiplier', 8));
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
