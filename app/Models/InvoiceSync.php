<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSync extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'provider',
        'external_id',
        'stripe_subscription_id',
        'customer_id',
        'customer_email',
        'customer_name',
        'customer_description',
        'customer_tax_id',
        'customer_address_country',
        'number',
        'status',
        'billing_reason',
        'closed',
        'currency',
        'amount_due',
        'amount_paid',
        'amount_remaining',
        'subtotal',
        'tax',
        'total',
        'total_discount_amount',
        'applied_coupons',
        'invoice_created_at',
        'invoice_due_date',
        'paid',
        'hosted_invoice_url',
        'invoice_pdf',
        'last_synced_at',
        'raw_payload',
    ];

    protected $casts = [
        'closed' => 'boolean',
        'paid' => 'boolean',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_remaining' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'total_discount_amount' => 'decimal:2',
        'invoice_created_at' => 'datetime',
        'invoice_due_date' => 'datetime',
        'last_synced_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', strtolower($provider));
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeCreatedBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('invoice_created_at', [$from, $to]);
    }

    /**
     * Paid Stripe invoices that are not yet linked to a Mercado Pago payment via metadata.
     */
    public function scopePaidWithoutMercadoPagoMetadata(Builder $query): Builder
    {
        return $query
            ->where('provider', 'stripe')
            ->where(function (Builder $paid): void
            {
                $paid->where('paid', true)
                    ->orWhere('status', 'paid');
            })
            ->whereRaw("(
                NULLIF(TRIM(COALESCE(
                    raw_payload->'metadata'->>'mercadopago_id',
                    raw_payload->'metadata'->>'mercadopago_payment_id',
                    ''
                )), '') IS NULL
            )");
    }

    public function lacksMercadoPagoLinkMetadata(): bool
    {
        $metadata = data_get($this->raw_payload, 'metadata', []);
        if (! is_array($metadata))
        {
            return true;
        }

        $mercadoPagoId = trim((string) ($metadata['mercadopago_id'] ?? $metadata['mercadopago_payment_id'] ?? ''));

        return $mercadoPagoId === '';
    }
}
