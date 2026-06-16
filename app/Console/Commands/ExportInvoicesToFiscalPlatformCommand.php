<?php

namespace App\Console\Commands;

use App\Jobs\ExportInvoiceToFiscalPlatformJob;
use App\Models\FiscalExport;
use App\Models\Invoice;
use App\Services\Fiscal\FiscalExportService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ExportInvoicesToFiscalPlatformCommand extends Command
{
    protected $signature = 'fiscal:export-invoices
                            {--team_id= : Limit to a single team}
                            {--invoice_id= : Export a single invoice by id}
                            {--from= : Only invoices with date >= YYYY-MM-DD}
                            {--to= : Only invoices with date <= YYYY-MM-DD}
                            {--limit=100 : Maximum invoices to process}
                            {--retry-failed : Include invoices whose last export failed}
                            {--force : Re-export even if already exported (use with care)}
                            {--sync : Run synchronously instead of queueing}
                            {--dry-run : Show what would be exported without doing it}';

    protected $description = 'Export eligible local invoices to the configured fiscal platform (Cuéntica, ...)';

    public function handle(FiscalExportService $service): int
    {
        if (! Schema::hasTable('fiscal_exports'))
        {
            $this->error('Table fiscal_exports does not exist. Run migrations first.');

            return self::FAILURE;
        }

        if (! (bool) config('fiscal.enabled', false))
        {
            $this->warn('Fiscal export is disabled (config fiscal.enabled).');

            return self::SUCCESS;
        }

        $invoices = $this->buildQuery()->get();

        if ($invoices->isEmpty())
        {
            $this->info('No matching invoices.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sync = (bool) $this->option('sync');
        $force = (bool) $this->option('force');

        $queued = 0;
        $processed = 0;
        $skipped = 0;

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice)
        {
            if (! $force && ! $service->isEligible($invoice))
            {
                $skipped++;

                continue;
            }

            if ($dryRun)
            {
                $this->line("Would export invoice #{$invoice->id} (status {$invoice->status}, number {$invoice->number}).");
                $queued++;

                continue;
            }

            if ($sync)
            {
                $export = $service->export($invoice, $force);
                $status = $export?->status ?? 'null';
                $this->line("Invoice #{$invoice->id}: {$status}".($export?->error_message ? ' - '.$export->error_message : ''));
                $processed++;

                continue;
            }

            ExportInvoiceToFiscalPlatformJob::dispatch($invoice->id, $force);
            $queued++;
        }

        $this->info("Done. Queued: {$queued}, processed: {$processed}, skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function buildQuery(): Builder
    {
        $query = Invoice::query()->withoutGlobalScopes();

        if ($invoiceId = $this->option('invoice_id'))
        {
            return $query->whereKey((int) $invoiceId);
        }

        $statuses = array_unique(array_merge(
            (array) config('fiscal.export_on_status', []),
            (array) config('fiscal.rectify_on_status', []),
        ));
        $query->whereIn('status', $statuses);

        if ($teamId = $this->option('team_id'))
        {
            $query->where('team_id', (int) $teamId);
        }

        if ($from = $this->option('from'))
        {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = $this->option('to'))
        {
            $query->whereDate('date', '<=', $to);
        }

        if (! (bool) $this->option('force'))
        {
            $query->where(function ($q)
            {
                $q->whereDoesntHave('fiscalExports', function ($sub)
                {
                    $sub->whereIn('status', [FiscalExport::STATUS_EXPORTED, FiscalExport::STATUS_RECTIFIED]);
                });

                if ((bool) $this->option('retry-failed'))
                {
                    return;
                }

                $q->whereDoesntHave('fiscalExports', function ($sub)
                {
                    $sub->where('status', FiscalExport::STATUS_FAILED);
                });
            });
        }

        return $query->orderBy('date')->limit((int) $this->option('limit'));
    }
}
