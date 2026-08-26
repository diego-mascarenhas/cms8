<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\TokenBillingHistory;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * USD token sell rate (provider cost + markup) with a monthly ledger,
 * converted to the display currency via {@see ExchangeRate}.
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

    public static function amountPerMillion(): float
    {
        return max(0, (float) config('humano_pricing.token_billing.amount_per_million', 10));
    }

    public static function markupPercent(): float
    {
        return max(0, (float) config('humano_pricing.token_billing.markup_percent', 50));
    }

    public static function usdSellRateFromConfig(): float
    {
        return self::usdSellRateFrom(self::amountPerMillion(), self::markupPercent());
    }

    public static function usdSellRateFrom(float $amountPerMillion, float $markupPercent): float
    {
        return round($amountPerMillion * (1 + ($markupPercent / 100)), 4);
    }

    /**
     * Persist this month's USD cost and markup when they are missing or changed.
     *
     * @return 'created'|'updated'|'skipped'
     */
    public static function syncCurrentMonth(): string
    {
        $base = self::baseCurrency();
        $amount = self::amountPerMillion();
        $markup = self::markupPercent();
        $sell = self::usdSellRateFrom($amount, $markup);
        $month = now()->copy()->startOfMonth()->toDateString();

        $existing = TokenBillingHistory::query()
            ->where('base_currency', $base)
            ->whereDate('rate_month', $month)
            ->first();

        if ($existing !== null
            && abs((float) $existing->amount_per_million - $amount) < 0.00005
            && abs((float) $existing->markup_percent - $markup) < 0.005
            && abs((float) $existing->sell_rate - $sell) < 0.00005)
        {
            return 'skipped';
        }

        $payload = [
            'amount_per_million' => $amount,
            'markup_percent' => $markup,
            'sell_rate' => $sell,
            'recorded_at' => now(),
        ];

        if ($existing !== null)
        {
            $existing->update($payload);

            return 'updated';
        }

        TokenBillingHistory::query()->create([
            'base_currency' => $base,
            'rate_month' => $month,
            ...$payload,
        ]);

        return 'created';
    }

    public static function usdSellRateOnOrBefore(DateTimeInterface|string|null $on = null): float
    {
        self::syncCurrentMonth();

        $at = self::resolveDate($on);
        $history = TokenBillingHistory::latestOnOrBefore(self::baseCurrency(), $at);

        return $history !== null
            ? (float) $history->sell_rate
            : self::usdSellRateFromConfig();
    }

    /**
     * Customer rate in the display currency for that date.
     */
    public static function displaySellRate(DateTimeInterface|string|null $on = null): float
    {
        $usd = self::usdSellRateOnOrBefore($on);
        $base = self::baseCurrency();
        $display = self::displayCurrency();

        if ($base === $display)
        {
            return round($usd, 4);
        }

        $fx = ExchangeRate::rateOnOrBeforeDate($base, $display, self::resolveDate($on));

        return $fx === null ? round($usd, 4) : round($usd * $fx, 4);
    }

    public static function cents(int $tokens, DateTimeInterface|string|null $on = null, ?float $rate = null): int
    {
        $rate ??= self::displaySellRate($on);

        return (int) round(($tokens / 1_000_000) * $rate * 100);
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
