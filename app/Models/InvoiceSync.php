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

    public function consumptionSummary(): string
    {
        $parts = array_map(
            fn (array $line): string => implode(' · ', array_filter([$line['name'], $line['period'], $line['detail']])),
            $this->consumptionLines(),
        );

        return $parts !== [] ? implode(' · ', $parts) : 'Consumo';
    }

    /**
     * Each Stripe usage line is stored as "Name · period" or "Name · period · detail".
     *
     * @return list<array{name: string, period: string, detail: string}>
     */
    public function consumptionLines(): array
    {
        $lines = data_get($this->raw_payload, 'lines.data', []);
        $items = [];

        if (is_array($lines))
        {
            foreach ($lines as $line)
            {
                if (! is_array($line))
                {
                    continue;
                }

                $description = trim((string) ($line['description'] ?? ''));
                if ($description === '')
                {
                    continue;
                }

                $items[] = $this->splitConsumptionDescription($description);
            }
        }

        if ($items !== [])
        {
            return $items;
        }

        $number = trim((string) $this->number);

        return [[
            'name' => $number !== '' ? $number : 'Consumo',
            'period' => '',
            'detail' => '',
        ]];
    }

    /**
     * @return array{name: string, period: string, detail: string}
     */
    private function splitConsumptionDescription(string $description): array
    {
        $segments = array_values(array_filter(
            array_map(trim(...), explode(' · ', $description)),
            fn (string $segment): bool => $segment !== '',
        ));

        if (count($segments) >= 3)
        {
            $name = (string) array_shift($segments);
            $detail = (string) array_pop($segments);

            return [
                'name' => $name,
                'period' => implode(' · ', $segments),
                'detail' => $detail,
            ];
        }

        if (count($segments) === 2)
        {
            return [
                'name' => $segments[0],
                'period' => $segments[1],
                'detail' => '',
            ];
        }

        return [
            'name' => $description,
            'period' => '',
            'detail' => '',
        ];
    }

    public function getStatusBadgeAttribute(): string
    {
        $status = strtolower(trim((string) $this->status));
        [$label, $color] = match ($status)
        {
            'paid' => ['Pagada', 'success'],
            'open' => ['Abierta', 'warning'],
            'draft' => ['Borrador', 'secondary'],
            'void' => ['Anulada', 'danger'],
            'uncollectible' => ['Incobrable', 'warning'],
            default => [$status !== '' ? ucfirst($status) : '', 'secondary'],
        };

        if ($label === '')
        {
            return '';
        }

        return '<span class="badge rounded-pill bg-label-'.$color.'">'.e($label).'</span>';
    }
}
