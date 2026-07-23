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

    /**
     * CVU / account funding transfers often expose the collector as "payer".
     */
    public function lacksIdentifiablePayer(): bool
    {
        $operationType = strtolower(trim((string) data_get($this->raw_payload, 'operation_type', '')));
        if ($operationType === 'account_fund')
        {
            return true;
        }

        $payerId = trim((string) data_get($this->raw_payload, 'payer.id', ''));
        $collectorId = trim((string) data_get($this->raw_payload, 'collector_id', ''));
        if ($payerId !== '' && $collectorId !== '' && $payerId === $collectorId)
        {
            return true;
        }

        return blank($this->customer_id) && blank($this->customer_email);
    }

    /**
     * Mercado Pago "Código de identificación" (e2e / Coelsa id for bank transfers).
     */
    public function identificationCode(): ?string
    {
        $candidates = [
            data_get($this->raw_payload, 'transaction_details.transaction_id'),
            data_get($this->raw_payload, 'point_of_interaction.transaction_data.e2e_id'),
            data_get($this->raw_payload, 'point_of_interaction.transaction_data.transaction_id'),
        ];

        foreach ($candidates as $candidate)
        {
            $value = trim((string) $candidate);
            if ($value !== '')
            {
                return $value;
            }
        }

        return null;
    }
}
