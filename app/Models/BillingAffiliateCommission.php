<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAffiliateCommission extends Model
{
    protected $fillable = [
        'paying_team_id',
        'referrer_team_id',
        'paying_enterprise_id',
        'referrer_enterprise_id',
        'stripe_invoice_id',
        'amount_paid_cents',
        'currency',
        'commission_percent',
        'commission_amount_cents',
    ];

    protected $casts = [
        'amount_paid_cents' => 'integer',
        'commission_percent' => 'decimal:4',
        'commission_amount_cents' => 'integer',
    ];

    public function payingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'paying_team_id');
    }

    public function referrerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'referrer_team_id');
    }

    public function payingEnterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class, 'paying_enterprise_id');
    }

    public function referrerEnterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class, 'referrer_enterprise_id');
    }
}
