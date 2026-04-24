<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSync extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'provider',
        'external_id',
        'customer_id',
        'customer_email',
        'status',
        'currency',
        'amount_cents',
        'amount_refunded_cents',
        'amount_net_cents',
        'invoice_external_id',
        'description',
        'charge_created_at',
        'last_synced_at',
        'raw_payload',
    ];

    protected $casts = [
        'charge_created_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', strtolower($provider));
    }
}
