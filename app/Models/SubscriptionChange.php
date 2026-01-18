<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionChange extends Model
{
    protected $fillable = [
        'subscription_id',
        'source',
        'changed_fields',
        'previous_values',
        'current_values',
        'detected_at',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'previous_values' => 'array',
        'current_values' => 'array',
        'detected_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(StripeSubscription::class, 'subscription_id');
    }
}
