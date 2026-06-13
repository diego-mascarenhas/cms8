<?php

namespace App\Console\Commands;

use App\Models\InvoiceSync;
use App\Services\Billing\CuenticaInvoiceCoreImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportCuenticaInvoiceSyncsCommand extends Command
{
    protected $signature = 'invoice-syncs:import-cuentica
                            {--team_id= : Import only one team}
                            {--external_id= : Import only this Cuéntica external id (sale:123 or purchase:456)}
                            {--limit=500 : Max sync rows to process}
                            {--reconcile : Also refresh rows that already have a core invoice}
                            {--fallback-tax-id : Match enterprise by tax id from sync row}
                            {--fallback-email : Match enterprise by email when code/tax id does not match}
                            {--link-code-on-match : Write cuentica counterparty code into enterprises.code}
                            {--auto-create-counterparty : Create client/supplier from Cuéntica data when not found}
                            {--dry-run : Preview without writing}';

    protected $description = 'Map Cuéntica invoice_syncs rows into core invoices (sale and purchase)';

    public function handle(CuenticaInvoiceCoreImportService $importService): int
    {
        if (! Schema::hasTable('invoice_syncs'))
        {
            $this->error('Table invoice_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $reconcile = (bool) $this->option('reconcile');
        $fallbackTaxId = (bool) $this->option('fallback-tax-id');
        $fallbackEmail = (bool) $this->option('fallback-email');
        $linkCodeOnMatch = (bool) $this->option('link-code-on-match');
        $autoCreateCounterparty = (bool) $this->option('auto-create-counterparty');

        $query = InvoiceSync::query()->where('provider', 'cuentica');

        if ($teamId)
        {
            $query->where('team_id', $teamId);
        } else
        {
            $query->orderBy('team_id');
        }

        $externalId = trim((string) ($this->option('external_id') ?? ''));
        if ($externalId !== '')
        {
            $query->where('external_id', $externalId);
        }

        if (! $reconcile)
        {
            $query->whereNotExists(function ($q)
            {
                $q->from('invoices')
                    ->whereColumn('invoices.source_reference_id', 'invoice_syncs.external_id')
                    ->whereColumn('invoices.team_id', 'invoice_syncs.team_id')
                    ->where('invoices.source_provider', 'cuentica');
            });
        }

        $query
            ->orderByRaw('invoice_created_at IS NULL')
            ->orderBy('invoice_created_at')
            ->orderBy('id');

        $rows = $query->limit($limit)->get();

        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row)
        {
            if (! $row instanceof InvoiceSync)
            {
                continue;
            }

            $processed++;

            $existing = \App\Models\Invoice::withoutGlobalScopes()
                ->where('source_provider', 'cuentica')
                ->where('source_reference_id', $row->external_id)
                ->first();

            $invoice = $importService->importFromSyncRow(
                $row,
                $fallbackTaxId,
                $fallbackEmail,
                $linkCodeOnMatch,
                $autoCreateCounterparty,
                $dryRun,
            );

            if (! $invoice && ! $dryRun)
            {
                $skipped++;
                $this->warn("Skip {$row->external_id}: enterprise not found for team {$row->team_id}");

                continue;
            }

            if ($dryRun)
            {
                continue;
            }

            if ($existing)
            {
                $updated++;
            } else
            {
                $created++;
            }
        }

        $this->info(
            "Processed: {$processed} | created: {$created} | updated: {$updated} | skipped: {$skipped}".
            ($reconcile ? ' | reconcile' : ' | pending-only').
            ($dryRun ? ' | dry-run' : ''),
        );

        return self::SUCCESS;
    }
}
