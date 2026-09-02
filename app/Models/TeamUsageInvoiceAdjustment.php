<?php

namespace App\Models;

use App\Enums\TeamBillingFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamUsageInvoiceAdjustment extends Model
{
    /** @use HasFactory<\Database\Factories\TeamUsageInvoiceAdjustmentFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'frequency',
        'period_from',
        'period_to',
        'invoiced_at',
    ];

    protected $casts = [
        'frequency' => TeamBillingFrequency::class,
        'period_from' => 'datetime',
        'period_to' => 'datetime',
        'invoiced_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('invoiced_at');
    }
}
