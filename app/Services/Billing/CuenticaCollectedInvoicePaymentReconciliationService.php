<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceSync;

class CuenticaCollectedInvoicePaymentReconciliationService
{
    public function __construct(
        private readonly CuenticaPaymentImportService $paymentImportService,
        private readonly CuenticaInvoiceCoreImportService $coreImportService,
    ) {}

    /**
     * @return array{matched: int, created: int, skipped: int, dry_run: bool}
     */
    public function reconcile(
        ?int $teamId = null,
        int $limit = 100,
        bool $dryRun = false,
    ): array {
        $limit = max(1, $limit);

        $query = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'cuentica')
            ->whereNotNull('source_reference_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        }

        $invoices = $query->limit($limit * 3)->get();

        $matched = 0;
        $created = 0;
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

            $invoiceSync = InvoiceSync::query()
                ->where('team_id', $invoice->team_id)
                ->where('provider', 'cuentica')
                ->where('external_id', $invoice->source_reference_id)
                ->first();

            if (! $invoiceSync instanceof InvoiceSync)
            {
                continue;
            }

            if ($this->paymentImportService->invoiceHasApprovedPayment($invoice, $invoiceSync))
            {
                continue;
            }

            if ($this->paymentImportService->resolvePaymentAmount($invoiceSync) <= 0)
            {
                continue;
            }

            $matched++;

            if (! $dryRun)
            {
                $this->coreImportService->importFromSyncRow(
                    $invoiceSync,
                    fallbackTaxId: true,
                    fallbackEmail: true,
                    linkCodeOnMatch: true,
                    autoCreateCounterparty: true,
                );

                $invoice->refresh();
            }

            $payment = $this->paymentImportService->syncPaymentForInvoice(
                $invoice,
                $invoiceSync,
                $dryRun,
            );

            if ($payment !== null || $dryRun)
            {
                $created++;
            } else
            {
                $skipped++;
            }
        }

        return [
            'matched' => $matched,
            'created' => $created,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ];
    }
}
