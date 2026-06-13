<?php

namespace App\Services\Fiscal\Cuentica;

use App\Enums\CuenticaInboundDocumentKind;
use App\Models\InvoiceSync;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class CuenticaInvoiceSyncUpserter
{
    public function __construct(
        private readonly CuenticaInboundPayloadNormalizer $normalizer,
    ) {}

    public function upsertSale(int $teamId, array $payload): ?InvoiceSync
    {
        return $this->upsert($teamId, CuenticaInboundDocumentKind::Sale, $payload);
    }

    public function upsertPurchase(int $teamId, array $payload): ?InvoiceSync
    {
        return $this->upsert($teamId, CuenticaInboundDocumentKind::Purchase, $payload);
    }

    public function upsert(int $teamId, CuenticaInboundDocumentKind $kind, array $payload): ?InvoiceSync
    {
        $cuenticaId = Arr::get($payload, 'id');
        if ($cuenticaId === null || $cuenticaId === '')
        {
            return null;
        }

        $externalId = $kind->externalId($cuenticaId);
        $currency = strtolower((string) config('fiscal.platforms.cuentica.inbound_sync.default_currency', 'EUR'));

        if ($kind === CuenticaInboundDocumentKind::Sale)
        {
            $payload = $this->normalizer->normalizeSale($payload);

            return $this->upsertSalePayload($teamId, $externalId, $kind, $currency, $payload);
        }

        $payload = $this->normalizer->normalizePurchase($payload);

        return $this->upsertPurchasePayload($teamId, $externalId, $kind, $currency, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertSalePayload(
        int $teamId,
        string $externalId,
        CuenticaInboundDocumentKind $kind,
        string $currency,
        array $payload,
    ): ?InvoiceSync {
        $counterpartyId = $this->counterpartyId($payload, 'customer');
        $subtotal = $this->amount(Arr::get($payload, 'total_base'));
        $total = $this->amount(Arr::get($payload, 'total_invoice'), $subtotal);
        $tax = max(0.0, round($total - $subtotal, 2));
        $paid = $this->isPaidFromCharges(Arr::get($payload, 'charges', []), $total);
        $issued = (bool) Arr::get($payload, 'issued', false);
        $status = $this->saleStatus($issued, $paid, (bool) Arr::get($payload, 'voided', false));

        return InvoiceSync::updateOrCreate(
            [
                'team_id' => $teamId,
                'provider' => 'cuentica',
                'external_id' => $externalId,
            ],
            [
                'customer_id' => $counterpartyId,
                'customer_email' => Arr::get($payload, 'customer_email'),
                'customer_name' => Arr::get($payload, 'customer_name'),
                'customer_tax_id' => Arr::get($payload, 'customer_tax_id'),
                'customer_address_country' => strtoupper((string) Arr::get($payload, 'customer_country', 'ES')) ?: 'ES',
                'number' => $this->formatNumber(Arr::get($payload, 'number')),
                'status' => $status,
                'billing_reason' => $kind->billingReason(),
                'closed' => $issued,
                'currency' => $currency,
                'amount_due' => $paid ? 0 : $total,
                'amount_paid' => $paid ? $total : 0,
                'amount_remaining' => $paid ? 0 : $total,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'invoice_created_at' => $this->date(Arr::get($payload, 'date')),
                'invoice_due_date' => null,
                'paid' => $paid,
                'last_synced_at' => now(),
                'raw_payload' => $payload,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertPurchasePayload(
        int $teamId,
        string $externalId,
        CuenticaInboundDocumentKind $kind,
        string $currency,
        array $payload,
    ): ?InvoiceSync {
        $counterpartyId = $this->counterpartyId($payload, 'provider');
        $subtotal = $this->amount(Arr::get($payload, 'total_base'));
        $total = $this->amount(Arr::get($payload, 'total_expense'), $subtotal);
        $tax = max(0.0, round($total - $subtotal, 2));
        $paid = $this->isPaidFromCharges(Arr::get($payload, 'payments', []), $total);
        $draft = (bool) Arr::get($payload, 'draft', false);
        $status = $this->purchaseStatus($draft, $paid);

        return InvoiceSync::updateOrCreate(
            [
                'team_id' => $teamId,
                'provider' => 'cuentica',
                'external_id' => $externalId,
            ],
            [
                'customer_id' => $counterpartyId,
                'customer_email' => Arr::get($payload, 'provider_email'),
                'customer_name' => Arr::get($payload, 'provider_name'),
                'customer_tax_id' => Arr::get($payload, 'provider_tax_id'),
                'customer_address_country' => strtoupper((string) Arr::get($payload, 'provider_country', 'ES')) ?: 'ES',
                'number' => $this->formatNumber(Arr::get($payload, 'document_number')),
                'status' => $status,
                'billing_reason' => $kind->billingReason(),
                'closed' => ! $draft,
                'currency' => $currency,
                'amount_due' => $paid ? 0 : $total,
                'amount_paid' => $paid ? $total : 0,
                'amount_remaining' => $paid ? 0 : $total,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'invoice_created_at' => $this->date(Arr::get($payload, 'date')),
                'invoice_due_date' => null,
                'paid' => $paid,
                'last_synced_at' => now(),
                'raw_payload' => $payload,
            ],
        );
    }

    private function counterpartyId(array $payload, string $key): ?string
    {
        $value = Arr::get($payload, $key);

        if (is_array($value))
        {
            $id = Arr::get($value, 'id');

            return $id !== null && $id !== '' ? (string) $id : null;
        }

        if ($value === null || $value === '')
        {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<int, mixed>  $charges
     */
    private function isPaidFromCharges(array $charges, float $total): bool
    {
        if ($charges === [] || $total <= 0)
        {
            return false;
        }

        $paidAmount = 0.0;
        $allPaid = true;

        foreach ($charges as $charge)
        {
            if (! is_array($charge))
            {
                continue;
            }

            $amount = $this->amount(Arr::get($charge, 'amount'));
            if ((bool) Arr::get($charge, 'paid', false))
            {
                $paidAmount += $amount;
            } else
            {
                $allPaid = false;
            }
        }

        if ($allPaid && $paidAmount >= $total)
        {
            return true;
        }

        return $paidAmount >= $total && $total > 0;
    }

    private function saleStatus(bool $issued, bool $paid, bool $voided): string
    {
        if ($voided)
        {
            return 'void';
        }

        if (! $issued)
        {
            return 'draft';
        }

        return $paid ? 'paid' : 'open';
    }

    private function purchaseStatus(bool $draft, bool $paid): string
    {
        if ($draft)
        {
            return 'draft';
        }

        return $paid ? 'paid' : 'open';
    }

    private function amount(mixed $value, ?float $fallback = null): float
    {
        if ($value === null || $value === '')
        {
            return $fallback ?? 0.0;
        }

        $normalized = round((float) $value, 2);

        return max(0.0, $normalized);
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '')
        {
            return null;
        }

        try
        {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable)
        {
            return null;
        }
    }

    private function formatNumber(mixed $number): ?string
    {
        if ($number === null || $number === '')
        {
            return null;
        }

        return (string) $number;
    }
}
