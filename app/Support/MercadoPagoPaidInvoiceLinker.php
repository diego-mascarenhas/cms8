<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use Illuminate\Support\Collection;

class MercadoPagoPaidInvoiceLinker
{
    /**
     * Local Stripe invoices that are already paid but not yet linked to Mercado Pago metadata.
     *
     * @return Collection<int, Invoice>
     */
    public static function paidUnlinkedForEnterprise(int $teamId, int $enterpriseId, int $limit = 100): Collection
    {
        return Invoice::query()
            ->where('team_id', $teamId)
            ->where('enterprise_id', $enterpriseId)
            ->where('operation', 'sell')
            ->where('source_provider', 'stripe')
            ->where('balance', '<=', 0)
            ->where('source_reference_id', 'like', 'in_%')
            ->whereExists(function ($query): void
            {
                $query->selectRaw('1')
                    ->from('invoice_syncs')
                    ->whereColumn('invoice_syncs.team_id', 'invoices.team_id')
                    ->whereColumn('invoice_syncs.external_id', 'invoices.source_reference_id')
                    ->where('invoice_syncs.provider', 'stripe')
                    ->where(function ($paid): void
                    {
                        $paid->where('invoice_syncs.paid', true)
                            ->orWhere('invoice_syncs.status', 'paid');
                    })
                    ->whereRaw("(
                        NULLIF(TRIM(COALESCE(
                            invoice_syncs.raw_payload->'metadata'->>'mercadopago_id',
                            invoice_syncs.raw_payload->'metadata'->>'mercadopago_payment_id',
                            ''
                        )), '') IS NULL
                    )");
            })
            ->whereNotExists(function ($query): void
            {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.invoice_id', 'invoices.id')
                    ->where('payments.source_provider', 'mercadopago')
                    ->where('payments.status', '!=', 0);
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public static function isPaidUnlinkedCandidate(Invoice $invoice): bool
    {
        if (strtolower((string) $invoice->source_provider) !== 'stripe')
        {
            return false;
        }

        if ((float) $invoice->balance > 0)
        {
            return false;
        }

        $externalId = trim((string) $invoice->source_reference_id);
        if ($externalId === '' || ! str_starts_with($externalId, 'in_'))
        {
            return false;
        }

        $hasMercadoPagoPayment = Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('source_provider', 'mercadopago')
            ->where('status', '!=', 0)
            ->exists();

        if ($hasMercadoPagoPayment)
        {
            return false;
        }

        $sync = InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $externalId)
            ->orderByDesc('id')
            ->first();

        if (! $sync instanceof InvoiceSync)
        {
            return false;
        }

        $alreadyPaid = $sync->paid || strtolower((string) $sync->status) === 'paid';

        return $alreadyPaid && $sync->lacksMercadoPagoLinkMetadata();
    }
}
