<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SLAAcceptance extends Model
{
    use HasFactory;

    protected $table = 'sla_acceptances';

    protected $fillable = [
        'sla_id',
        'subscription_id',
        'token',
        'accepted_by_email',
        'accepted_by_name',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    /**
     * Get the SLA that this acceptance belongs to.
     */
    public function sla(): BelongsTo
    {
        return $this->belongsTo(SLA::class, 'sla_id');
    }

    /**
     * Get the subscription that this acceptance is associated with.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
