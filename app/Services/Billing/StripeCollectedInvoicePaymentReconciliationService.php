<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\PaymentSync;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StripeCollectedInvoicePaymentReconciliationService
{
    public function __construct(
        private readonly StripePaymentImportService $paymentImportService,
        private readonly StripeInvoiceCoreImportService $coreImportService,
    ) {}

    /**
     * @return array{core_refreshed: int, matched: int, from_payment_sync: int, from_invoice_sync: int, skipped: int, dry_run: bool}
     */
    public function reconcile(
        ?int $teamId = null,
        int $limit = 100,
        bool $dryRun = false,
    ): array {
        $limit = max(1, $limit);

        $coreRefreshed = $this->refreshStaleCoreFromPaidInvoiceSyncs($teamId, $limit, $dryRun);

        $query = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'stripe')
            ->where('balance', '<=', 0)
            ->whereNotIn('status', [3, 5, 6, 7, 9])
            ->whereNotNull('source_reference_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        }

        $invoices = $query->limit($limit * 3)->get();

        $matched = 0;
        $fromPaymentSync = 0;
        $fromInvoiceSync = 0;
        $skipped = 0;

        foreach ($invoices as $invoice)
        {
            if (! $invoice instanceof Invoice)
            {
                continue;
            }

            if ($matched >= $limit)
            {
                break;
            }

            if ($this->paymentImportService->invoiceHasApprovedPayments($invoice))
            {
                continue;
            }

            $matched++;

            if ($this->importLinkedPaymentSyncs($invoice, $dryRun))
            {
                $fromPaymentSync++;

                continue;
            }

            if ($this->createPaymentFromInvoiceSync($invoice, $dryRun))
            {
                $fromInvoiceSync++;

                continue;
            }

            $skipped++;
        }

        return [
            'core_refreshed' => $coreRefreshed,
            'matched' => $matched,
            'from_payment_sync' => $fromPaymentSync,
            'from_invoice_sync' => $fromInvoiceSync,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ];
    }

    public function reconcileInvoice(Invoice $invoice, bool $dryRun = false): bool
    {
        if ($invoice->source_provider !== 'stripe')
        {
            return false;
        }

        $this->refreshCoreFromPaidInvoiceSync($invoice, $dryRun);
        $invoice->refresh();

        if ((float) $invoice->balance > 0)
        {
            return false;
        }

        if ($this->paymentImportService->invoiceHasApprovedPayments($invoice))
        {
            return false;
        }

        if ($this->importLinkedPaymentSyncs($invoice, $dryRun))
        {
            return true;
        }

        return $this->createPaymentFromInvoiceSync($invoice, $dryRun);
    }

    private function refreshStaleCoreFromPaidInvoiceSyncs(
        ?int $teamId,
        int $limit,
        bool $dryRun,
    ): int {
        $query = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'stripe')
            ->where('balance', '>', 0)
            ->whereNotIn('status', [3, 5, 6, 7, 9])
            ->whereNotNull('source_reference_id')
            ->whereExists(function ($subQuery): void
            {
                $subQuery->from('invoice_syncs')
                    ->whereColumn('invoice_syncs.external_id', 'invoices.source_reference_id')
                    ->whereColumn('invoice_syncs.team_id', 'invoices.team_id')
                    ->where('invoice_syncs.provider', 'stripe')
                    ->where(function ($paidQuery): void
                    {
                        $paidQuery->where('invoice_syncs.paid', true)
                            ->orWhere('invoice_syncs.status', 'paid');
                    });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        }

        $refreshed = 0;

        foreach ($query->limit($limit)->get() as $invoice)
        {
            if (! $invoice instanceof Invoice)
            {
                continue;
            }

            if ($this->refreshCoreFromPaidInvoiceSync($invoice, $dryRun))
            {
                $refreshed++;
            }
        }

        return $refreshed;
    }

    private function refreshCoreFromPaidInvoiceSync(Invoice $invoice, bool $dryRun): bool
    {
        if (! filled($invoice->source_reference_id) || (float) $invoice->balance <= 0)
        {
            return false;
        }

        $invoiceSync = InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $invoice->source_reference_id)
            ->first();

        if (! $invoiceSync instanceof InvoiceSync || ! $this->invoiceSyncIsPaid($invoiceSync))
        {
            return false;
        }

        if ($dryRun)
        {
            return true;
        }

        $updated = $this->coreImportService->importFromSyncRow(
            $invoiceSync,
            fallbackEmail: true,
            linkCodeOnEmailMatch: true,
        );

        return $updated instanceof Invoice && (float) $updated->balance <= 0;
    }

    private function invoiceSyncIsPaid(InvoiceSync $invoiceSync): bool
    {
        if ($invoiceSync->paid)
        {
            return true;
        }

        return strtolower((string) $invoiceSync->status) === 'paid';
    }

    private function importLinkedPaymentSyncs(Invoice $invoice, bool $dryRun): bool
    {
        if (! filled($invoice->source_reference_id))
        {
            return false;
        }

        $syncs = PaymentSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('invoice_external_id', $invoice->source_reference_id)
            ->where('status', 'succeeded')
            ->orderBy('charge_created_at')
            ->get();

        $created = false;

        foreach ($syncs as $sync)
        {
            if (! $sync instanceof PaymentSync)
            {
                continue;
            }

            $payment = $this->paymentImportService->importFromPaymentSync(
                $sync,
                fallbackEmail: true,
                linkCodeOnEmailMatch: true,
                dryRun: $dryRun,
            );

            if ($payment !== null)
            {
                $created = true;
            }
        }

        return $created;
    }

    private function createPaymentFromInvoiceSync(Invoice $invoice, bool $dryRun): bool
    {
        if (! filled($invoice->source_reference_id))
        {
            return false;
        }

        $invoiceSync = InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $invoice->source_reference_id)
            ->first();

        if (! $invoiceSync instanceof InvoiceSync || ! $this->invoiceSyncIsPaid($invoiceSync))
        {
            return false;
        }

        $amount = (float) ($invoiceSync->amount_paid ?? 0);
        if ($amount <= 0)
        {
            $amount = (float) ($invoiceSync->total ?? $invoice->total_amount ?? 0);
        }

        if ($amount <= 0)
        {
            return false;
        }

        $paidAt = data_get($invoiceSync->raw_payload, 'status_transitions.paid_at');
        $date = is_numeric($paidAt)
            ? Carbon::createFromTimestamp((int) $paidAt)->toDateString()
            : ($invoiceSync->last_synced_at?->toDateString() ?? now()->toDateString());

        $payment = $this->paymentImportService->createPaymentFromPaidInvoice(
            invoice: $invoice,
            amount: $amount,
            date: $date,
            remarks: 'Stripe invoice payment (reconciled)',
            dryRun: $dryRun,
        );

        if ($payment === null && ! $dryRun)
        {
            Log::warning('Stripe collected invoice payment reconcile failed', [
                'invoice_id' => $invoice->id,
                'external_id' => $invoice->source_reference_id,
            ]);
        }

        return $payment !== null || $dryRun;
    }
}
