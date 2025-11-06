<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
     *
     * @param string $base
     * @param string $target
     * @return float|null
     */
    public static function getLatestRate(string $base, string $target): ?float
    {
        // Si son la misma moneda, la tasa es 1
        if ($base === $target) {
            return 1.0;
        }

        // Intentar obtener conversión directa
        $rate = static::where('base_currency', $base)
            ->where('target_currency', $target)
            ->latest('date')
            ->first();

        if ($rate) {
            return (float) $rate->rate;
        }

        // Si no existe conversión directa, calcular usando USD como intermediario
        // Fórmula: BASE → TARGET = (1 / USD_BASE) × USD_TARGET
        if ($base !== 'USD' && $target !== 'USD') {
            $usdToBase = static::where('base_currency', 'USD')
                ->where('target_currency', $base)
                ->latest('date')
                ->first();

            $usdToTarget = static::where('base_currency', 'USD')
                ->where('target_currency', $target)
                ->latest('date')
                ->first();

            if ($usdToBase && $usdToTarget) {
                // 1 BASE = (1 / USD_BASE) USD
                // 1 BASE = (1 / USD_BASE) × USD_TARGET TARGET
                return (1 / (float) $usdToBase->rate) * (float) $usdToTarget->rate;
            }
        }

        // Si base es USD, intentar la conversión inversa
        if ($base === 'USD') {
            $targetToUsd = static::where('base_currency', $target)
                ->where('target_currency', 'USD')
                ->latest('date')
                ->first();

            if ($targetToUsd) {
                return 1 / (float) $targetToUsd->rate;
            }
        }

        // Si target es USD, intentar la conversión inversa
        if ($target === 'USD') {
            $baseToUsd = static::where('base_currency', $base)
                ->where('target_currency', 'USD')
                ->latest('date')
                ->first();

            if ($baseToUsd) {
                return 1 / (float) $baseToUsd->rate;
            }
        }

        return null;
    }

    /**
     * Get exchange rate for a specific date.
     *
     * @param string $base
     * @param string $target
     * @param string|Carbon $date
     * @return float|null
     */
    public static function getRateForDate(string $base, string $target, $date): ?float
    {
        $dateString = $date instanceof Carbon ? $date->format('Y-m-d') : $date;

        $rate = static::where('base_currency', $base)
            ->where('target_currency', $target)
            ->where('date', $dateString)
            ->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Convert an amount from one currency to another.
     *
     * @param float $amount
     * @param string $from
     * @param string $to
     * @return float|null
     */
    public static function convert(float $amount, string $from, string $to): ?float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = static::getLatestRate($from, $to);

        return $rate ? $amount * $rate : null;
    }

    /**
     * Scope to filter by base currency.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $currency
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBaseCurrency($query, string $currency)
    {
        return $query->where('base_currency', $currency);
    }

    /**
     * Scope to filter by target currency.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $currency
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTargetCurrency($query, string $currency)
    {
        return $query->where('target_currency', $currency);
    }

    /**
     * Scope to filter by date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|Carbon $startDate
     * @param string|Carbon $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}

