<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SLA extends Model
{
    use HasFactory;

    protected $table = 'slas';

    protected $fillable = [
        'subscription_product_id',
        'title',
        'content',
        'version',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the subscription product that owns the SLA.
     */
    public function subscriptionProduct(): BelongsTo
    {
        return $this->belongsTo(SubscriptionProduct::class);
    }

    /**
     * Get all acceptances for this SLA.
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(SLAAcceptance::class, 'sla_id');
    }

    /**
     * Get the latest acceptance for this SLA.
     */
    public function latestAcceptance()
    {
        return $this->hasOne(SLAAcceptance::class, 'sla_id')->latestOfMany('accepted_at');
    }
}
