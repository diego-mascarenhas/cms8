<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    public function importedMercadoPagoPayment(): ?Payment
    {
        $externalId = trim((string) $this->external_id);
        if ($externalId === '')
        {
            return null;
        }

        return Payment::withoutGlobalScopes()
            ->with('invoice')
            ->where('team_id', $this->team_id)
            ->where('source_provider', 'mercadopago')
            ->where(function ($query) use ($externalId): void
            {
                $query->where('source_reference_id', $externalId)
                    ->orWhere('source_reference_id', 'like', $externalId.':%');
            })
            ->orderBy('id')
            ->first();
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
     * Mercado Pago Payments API rows are money received by the collector.
     */
    public function transactionType(): TransactionType
    {
        return TransactionType::INCOME;
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

    /**
     * Values that may appear as Stripe invoice metadata.payment_reference
     * when the invoice was marked paid out of band with type + reference.
     *
     * @return list<string>
     */
    public function stripeMatchReferences(): array
    {
        $refs = [];
        $identificationCode = $this->identificationCode();
        if ($identificationCode !== null)
        {
            $refs[] = $identificationCode;
        }

        $externalId = trim((string) $this->external_id);
        if ($externalId !== '')
        {
            $refs[] = $externalId;
        }

        return array_values(array_unique($refs));
    }

    /**
     * Stripe invoices linked via out-of-band metadata:
     * payment_reference (e2e / bank ref) and/or mercadopago_id (MP payment id).
     *
     * @return array<string, array{invoice_id: ?int, number: ?string, stripe_external_id: string}>
     */
    public static function stripeLinkedInvoiceMap(int $teamId): array
    {
        $rows = DB::table('invoice_syncs as s')
            ->leftJoin('invoices as i', function ($join): void
            {
                $join->on('i.team_id', '=', 's.team_id')
                    ->whereColumn('i.source_reference_id', 's.external_id')
                    ->where('i.source_provider', '=', 'stripe');
            })
            ->where('s.team_id', $teamId)
            ->where('s.provider', 'stripe')
            ->whereNotNull('s.raw_payload')
            ->whereRaw("(
                NULLIF(TRIM(s.raw_payload->'metadata'->>'payment_reference'), '') IS NOT NULL
                OR NULLIF(TRIM(COALESCE(
                    s.raw_payload->'metadata'->>'mercadopago_id',
                    s.raw_payload->'metadata'->>'mercadopago_payment_id',
                    ''
                )), '') IS NOT NULL
            )")
            ->selectRaw("TRIM(s.raw_payload->'metadata'->>'payment_reference') as payment_reference")
            ->selectRaw("TRIM(COALESCE(
                s.raw_payload->'metadata'->>'mercadopago_id',
                s.raw_payload->'metadata'->>'mercadopago_payment_id',
                ''
            )) as mercadopago_id")
            ->selectRaw('s.external_id as stripe_external_id')
            ->selectRaw('i.id as invoice_id')
            ->selectRaw('COALESCE(i.number, s.number) as invoice_number')
            ->orderByDesc('i.id')
            ->orderByDesc('s.id')
            ->get();

        $map = [];
        foreach ($rows as $row)
        {
            $invoiceId = $row->invoice_id !== null ? (int) $row->invoice_id : null;
            $number = trim((string) ($row->invoice_number ?? ''));
            $stripeExternalId = trim((string) ($row->stripe_external_id ?? ''));
            $entry = [
                'invoice_id' => $invoiceId,
                'number' => $number !== '' ? $number : null,
                'stripe_external_id' => $stripeExternalId,
            ];

            foreach ([
                trim((string) ($row->payment_reference ?? '')),
                trim((string) ($row->mercadopago_id ?? '')),
            ] as $key)
            {
                if ($key === '' || isset($map[$key]))
                {
                    continue;
                }

                $map[$key] = $entry;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array{invoice_id: ?int, number: ?string, stripe_external_id?: string}>  $stripeLinkedInvoices
     * @return array{invoice_id: ?int, number: ?string, stripe_external_id?: string}|null
     */
    public function linkedStripeInvoice(array $stripeLinkedInvoices): ?array
    {
        foreach ($this->stripeMatchReferences() as $reference)
        {
            if (isset($stripeLinkedInvoices[$reference]))
            {
                return $stripeLinkedInvoices[$reference];
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{invoice_id: ?int, number: ?string, stripe_external_id?: string}>  $stripeLinkedInvoices
     */
    public function isLinkedViaStripe(array $stripeLinkedInvoices): bool
    {
        return $this->linkedStripeInvoice($stripeLinkedInvoices) !== null;
    }
}
