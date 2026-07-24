<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MercadoPagoPaidInvoiceLinker
{
    /**
     * Local Stripe invoices that are already paid but not yet linked to Mercado Pago metadata.
     *
     * @return Collection<int, Invoice>
     */
    public static function paidUnlinkedForEnterprise(
        int $teamId,
        int $enterpriseId,
        int $limit = 100,
        ?CarbonInterface $preferPaidNear = null,
    ): Collection {
        $invoices = Invoice::query()
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
            ->with(['enterprise'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($invoices->isEmpty())
        {
            return $invoices;
        }

        $syncsByExternalId = InvoiceSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'stripe')
            ->whereIn('external_id', $invoices->pluck('source_reference_id')->all())
            ->orderByDesc('id')
            ->get()
            ->unique('external_id')
            ->keyBy('external_id');

        $invoices->each(function (Invoice $invoice) use ($syncsByExternalId): void
        {
            $sync = $syncsByExternalId->get((string) $invoice->source_reference_id);
            $invoice->setRelation('stripeInvoiceSync', $sync);
        });

        if ($preferPaidNear === null)
        {
            return $invoices;
        }

        $target = $preferPaidNear->getTimestamp();

        return $invoices
            ->sortBy(function (Invoice $invoice) use ($target): array
            {
                $paidAt = self::stripePaidAtFromSync($invoice->getRelation('stripeInvoiceSync'));
                $distance = $paidAt instanceof CarbonInterface
                    ? abs($paidAt->getTimestamp() - $target)
                    : PHP_INT_MAX;

                $invoiceTimestamp = 0;
                if ($invoice->date instanceof CarbonInterface)
                {
                    $invoiceTimestamp = $invoice->date->getTimestamp();
                } elseif (filled($invoice->date))
                {
                    try
                    {
                        $invoiceTimestamp = Carbon::parse($invoice->date)->getTimestamp();
                    } catch (\Throwable)
                    {
                        $invoiceTimestamp = 0;
                    }
                }

                return [$distance, -1 * $invoiceTimestamp, -1 * $invoice->id];
            })
            ->values();
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

    public static function stripePaidAt(Invoice $invoice): ?CarbonInterface
    {
        if ($invoice->relationLoaded('stripeInvoiceSync'))
        {
            return self::stripePaidAtFromSync($invoice->getRelation('stripeInvoiceSync'));
        }

        $externalId = trim((string) $invoice->source_reference_id);
        if ($externalId === '')
        {
            return null;
        }

        $sync = InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $externalId)
            ->orderByDesc('id')
            ->first();

        return self::stripePaidAtFromSync($sync);
    }

    private static function stripePaidAtFromSync(mixed $sync): ?CarbonInterface
    {
        if (! $sync instanceof InvoiceSync)
        {
            return null;
        }

        $paidAt = data_get($sync->raw_payload, 'status_transitions.paid_at');
        if (! is_numeric($paidAt) || (int) $paidAt <= 0)
        {
            return null;
        }

        return Carbon::createFromTimestamp((int) $paidAt);
    }
}
