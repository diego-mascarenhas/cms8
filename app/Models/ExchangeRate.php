<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'date',
        'fetched_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'rate' => 'decimal:8',
        'date' => 'date',
        'fetched_at' => 'datetime',
    ];

    /**
     * Get the latest exchange rate for a currency pair.
     * If direct conversion doesn't exist, calculates it using USD as intermediary.
     */
    public static function getLatestRate(string $base, string $target): ?float
    {
        // Si son la misma moneda, la tasa es 1
        if ($base === $target)
        {
            return 1.0;
        }

        // Intentar obtener conversión directa
        $rate = static::where('base_currency', $base)
            ->where('target_currency', $target)
            ->latest('date')
            ->first();

        if ($rate)
        {
            return (float) $rate->rate;
        }

        $inverse = static::where('base_currency', $target)
            ->where('target_currency', $base)
            ->latest('date')
            ->first();

        if ($inverse && (float) $inverse->rate > 0)
        {
            return 1 / (float) $inverse->rate;
        }

        // Si no existe conversión directa, calcular usando USD como intermediario
        // Fórmula: BASE → TARGET = (1 / USD_BASE) × USD_TARGET
        if ($base !== 'USD' && $target !== 'USD')
        {
            $usdToBase = static::where('base_currency', 'USD')
                ->where('target_currency', $base)
                ->latest('date')
                ->first();

            $usdToTarget = static::where('base_currency', 'USD')
                ->where('target_currency', $target)
                ->latest('date')
                ->first();

            if ($usdToBase && $usdToTarget)
            {
                // 1 BASE = (1 / USD_BASE) USD
                // 1 BASE = (1 / USD_BASE) × USD_TARGET TARGET
                return (1 / (float) $usdToBase->rate) * (float) $usdToTarget->rate;
            }
        }

        // Si base es USD, intentar la conversión inversa
        if ($base === 'USD')
        {
            $targetToUsd = static::where('base_currency', $target)
                ->where('target_currency', 'USD')
                ->latest('date')
                ->first();

            if ($targetToUsd)
            {
                return 1 / (float) $targetToUsd->rate;
            }
        }

        // Si target es USD, intentar la conversión inversa
        if ($target === 'USD')
        {
            $baseToUsd = static::where('base_currency', $base)
                ->where('target_currency', 'USD')
                ->latest('date')
                ->first();

            if ($baseToUsd)
            {
                return 1 / (float) $baseToUsd->rate;
            }
        }

        return null;
    }

    /**
     * Get exchange rate for a specific date.
     *
     * @param  string|Carbon  $date
     */
    public static function getRateForDate(string $base, string $target, $date): ?float
    {
        $dateString = $date instanceof Carbon ? $date->format('Y-m-d') : $date;

        $rate = static::where('base_currency', $base)
            ->where('target_currency', $target)
            ->whereDate('date', $dateString)
            ->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Persist a daily rate only when missing or changed (avoids duplicate writes on re-run).
     *
     * @return 'created'|'updated'|'skipped'
     */
    public static function storeDailyIfChanged(string $base, string $target, string $date, float $rate): string
    {
        $base = strtoupper(trim($base));
        $target = strtoupper(trim($target));

        $existing = static::query()
            ->where('base_currency', $base)
            ->where('target_currency', $target)
            ->whereDate('date', $date)
            ->first();

        if ($existing !== null && abs((float) $existing->rate - $rate) < 0.000000005)
        {
            return 'skipped';
        }

        if ($existing !== null)
        {
            $existing->update([
                'rate' => $rate,
                'fetched_at' => now(),
            ]);

            return 'updated';
        }

        static::query()->create([
            'base_currency' => $base,
            'target_currency' => $target,
            'date' => $date,
            'rate' => $rate,
            'fetched_at' => now(),
        ]);

        return 'created';
    }

    /**
     * Convert an amount from one currency to another.
     */
    public static function convert(float $amount, string $from, string $to): ?float
    {
        if ($from === $to)
        {
            return $amount;
        }

        $rate = static::getLatestRate($from, $to);

        return $rate ? round($amount * $rate, 2) : null;
    }

    /**
     * Convert using the monthly history rate on or before the date, falling back to latest rates.
     *
     * @param  string|\Carbon\Carbon  $date
     */
    public static function convertOnOrBeforeDate(float $amount, string $from, string $to, $date): ?float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === '' || $to === '')
        {
            return null;
        }

        if ($from === $to)
        {
            return round($amount, 2);
        }

        $history = ExchangeRateHistory::latestRateOnOrBefore($from, $to, $date);
        if ($history)
        {
            return round($amount * (float) $history->rate, 2);
        }

        $inverseHistory = ExchangeRateHistory::latestRateOnOrBefore($to, $from, $date);
        if ($inverseHistory && (float) $inverseHistory->rate > 0)
        {
            return round($amount / (float) $inverseHistory->rate, 2);
        }

        return static::convert($amount, $from, $to);
    }

    /**
     * Scope to filter by base currency.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBaseCurrency($query, string $currency)
    {
        return $query->where('base_currency', $currency);
    }

    /**
     * Scope to filter by target currency.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTargetCurrency($query, string $currency)
    {
        return $query->where('target_currency', $currency);
    }

    /**
     * Scope to filter by date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|Carbon  $startDate
     * @param  string|Carbon  $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
