<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenBillingHistory extends Model
{
    /** @use HasFactory<\Database\Factories\TokenBillingHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'base_currency',
        'rate_month',
        'amount_per_million',
        'markup_percent',
        'sell_rate',
        'recorded_at',
    ];

    protected $casts = [
        'rate_month' => 'date',
        'amount_per_million' => 'decimal:4',
        'markup_percent' => 'decimal:2',
        'sell_rate' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    /**
     * @param  string|Carbon  $month
     */
    public function scopeForCalendarMonth(Builder $query, string $base, $month): Builder
    {
        $start = $month instanceof Carbon
            ? $month->copy()->startOfMonth()->toDateString()
            : Carbon::parse($month)->startOfMonth()->toDateString();

        return $query
            ->where('base_currency', strtoupper($base))
            ->whereDate('rate_month', $start);
    }

    /**
     * Latest stored month on or before the given date.
     *
     * @param  string|Carbon  $onOrBefore
     */
    public static function latestOnOrBefore(string $base, $onOrBefore): ?self
    {
        $date = $onOrBefore instanceof Carbon
            ? $onOrBefore->toDateString()
            : Carbon::parse($onOrBefore)->toDateString();

        return static::query()
            ->where('base_currency', strtoupper($base))
            ->whereDate('rate_month', '<=', $date)
            ->orderByDesc('rate_month')
            ->first();
    }
}
