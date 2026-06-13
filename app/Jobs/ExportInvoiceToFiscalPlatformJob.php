<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Fiscal\Exceptions\FiscalExportException;
use App\Services\Fiscal\FiscalExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExportInvoiceToFiscalPlatformJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public int $invoiceId,
        public bool $force = false,
    ) {}

    public function tries(): int
    {
        return (int) config('fiscal.retry.max_attempts', 5);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return (array) config('fiscal.retry.backoff_seconds', [60, 120, 300, 600]);
    }

    public function handle(FiscalExportService $service): void
    {
        $invoice = Invoice::withoutGlobalScopes()->find($this->invoiceId);

        if (! $invoice instanceof Invoice)
        {
            Log::info('Fiscal export job skipped: invoice not found', ['invoice_id' => $this->invoiceId]);

            return;
        }

        try
        {
            $service->export($invoice, $this->force);
        } catch (FiscalExportException $exception)
        {
            if ($exception->retryAfterSeconds !== null)
            {
                $this->release($exception->retryAfterSeconds);

                return;
            }

            throw $exception;
        }
    }
}
