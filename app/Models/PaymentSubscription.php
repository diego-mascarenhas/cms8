<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentSubscription extends Model
{
    protected $table = 'payment_subscriptions';

    protected $fillable = [
        'team_id',
        'provider',
        'external_id',
        'status',
        'name',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Services linked to this payment subscription.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'subscription_id');
    }

    /**
     * Display label for forms/lists (name + provider + status).
     */
    public function getDisplayLabelAttribute(): string
    {
        $parts = array_filter([
            $this->name ?: $this->external_id,
            $this->provider,
            $this->status,
        ]);

        return implode(' — ', $parts) ?: (string) $this->id;
    }
}
