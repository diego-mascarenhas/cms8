<?php

namespace App\Services\Fiscal;

use App\Jobs\ExportInvoiceToFiscalPlatformJob;
use App\Models\FiscalExport;
use App\Models\Invoice;
use App\Services\Fiscal\Exceptions\FiscalExportException;
use Illuminate\Support\Facades\Log;

class FiscalExportService
{
    public function __construct(
        private readonly FiscalExportRouter $router,
    ) {}

    /**
     * Queue an export for an invoice when it is eligible. Safe to call often:
     * the job and DB unique constraint keep it idempotent.
     */
    public function dispatchFor(Invoice $invoice): void
    {
        if (! (bool) config('fiscal.enabled', false))
        {
            return;
        }

        if (! $this->isEligible($invoice))
        {
            return;
        }

        ExportInvoiceToFiscalPlatformJob::dispatch($invoice->id);
    }

    /**
     * Run the export synchronously and persist its outcome.
     */
    public function export(Invoice $invoice, bool $force = false): ?FiscalExport
    {
        if (! (bool) config('fiscal.enabled', false))
        {
            return null;
        }

        $invoice->loadMissing(['team', 'enterprise', 'items', 'payments', 'currency']);

        $adapter = $this->router->resolve($invoice);
        $platform = $adapter->platform();

        $export = FiscalExport::query()->firstOrNew([
            'invoice_id' => $invoice->id,
            'platform' => $platform,
        ]);
        $export->team_id = $invoice->team_id;

        if ($platform === NullFiscalExportAdapter::PLATFORM)
        {
            return $this->persistResult($export, FiscalExportResult::skipped('No fiscal platform for invoice.'));
        }

        $status = (int) $invoice->status;
        $isExportable = in_array($status, (array) config('fiscal.export_on_status', []), true);
        $isRectifiable = in_array($status, (array) config('fiscal.rectify_on_status', []), true);

        if ($export->isExported() && ! $force && ! $isRectifiable)
        {
            return $export;
        }

        if (! $isExportable && ! $isRectifiable)
        {
            return $this->persistResult(
                $export,
                FiscalExportResult::skipped('Invoice status '.$status.' is not exportable.'),
            );
        }

        $export->attempts = (int) $export->attempts + 1;
        $export->last_attempted_at = now();
        $export->save();

        try
        {
            $result = ($isRectifiable && $export->isExported())
                ? $adapter->voidOrRectify($invoice, $export)
                : $adapter->export($invoice);

            return $this->persistResult($export, $result);
        } catch (FiscalExportException $exception)
        {
            $export->status = FiscalExport::STATUS_FAILED;
            $export->error_message = $exception->getMessage();
            $export->save();

            Log::warning('Fiscal export failed', [
                'invoice_id' => $invoice->id,
                'platform' => $platform,
                'retryable' => $exception->retryable,
                'message' => $exception->getMessage(),
            ]);

            if ($exception->retryable)
            {
                throw $exception;
            }

            return $export;
        }
    }

    public function isEligible(Invoice $invoice): bool
    {
        $status = (int) $invoice->status;
        $exportable = in_array($status, (array) config('fiscal.export_on_status', []), true);
        $rectifiable = in_array($status, (array) config('fiscal.rectify_on_status', []), true);

        if (! $exportable && ! $rectifiable)
        {
            return false;
        }

        $invoice->loadMissing('team');

        // The adapter only supports the invoice when the team has its own
        // fiscal credentials configured. Teams without configuration export
        // nothing (no fallback to Humano's own account).
        return $this->router->resolve($invoice)->platform() !== NullFiscalExportAdapter::PLATFORM;
    }

    private function persistResult(FiscalExport $export, FiscalExportResult $result): FiscalExport
    {
        $export->status = $result->status;
        $export->error_message = $result->errorMessage;

        if ($result->isExported() || $result->isRectified())
        {
            $export->external_id = $result->externalId ?? $export->external_id;
            $export->external_number = $result->externalNumber ?? $export->external_number;
            $export->external_customer_id = $result->externalCustomerId ?? $export->external_customer_id;
            $export->payload_snapshot = $result->payload ?: $export->payload_snapshot;
            $export->response_snapshot = $result->response ?: $export->response_snapshot;
            $export->payload_hash = $result->payload !== []
                ? hash('sha256', json_encode($result->payload) ?: '')
                : $export->payload_hash;
            $export->exported_at = now();
        }

        $export->save();

        return $export;
    }
}
