<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExchangeRateHistory extends Model
{
    protected $table = 'exchange_rate_histories';

    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate_month',
        'rate',
        'fetched_at',
        'provider',
        'payload',
    ];

    protected $casts = [
        'rate_month' => 'date',
        'rate' => 'decimal:8',
        'fetched_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * @param  string|Carbon  $month  Any day in the month; normalized to first day UTC date.
     */
    public function scopeForCalendarMonth(Builder $query, string $base, string $target, $month): Builder
    {
        $base = strtoupper($base);
        $target = strtoupper($target);
        $start = $month instanceof Carbon
            ? $month->copy()->startOfMonth()->toDateString()
            : Carbon::parse($month)->startOfMonth()->toDateString();

        return $query
            ->where('base_currency', $base)
            ->where('target_currency', $target)
            ->whereDate('rate_month', $start);
    }

    /**
     * Latest stored month on or before the given date (for reporting).
     *
     * @param  string|Carbon  $onOrBefore
     */
    public static function latestRateOnOrBefore(string $base, string $target, $onOrBefore): ?self
    {
        $base = strtoupper($base);
        $target = strtoupper($target);
        $date = $onOrBefore instanceof Carbon
            ? $onOrBefore->toDateString()
            : Carbon::parse($onOrBefore)->toDateString();

        return static::query()
            ->where('base_currency', $base)
            ->where('target_currency', $target)
            ->whereDate('rate_month', '<=', $date)
            ->orderByDesc('rate_month')
            ->first();
    }
}
