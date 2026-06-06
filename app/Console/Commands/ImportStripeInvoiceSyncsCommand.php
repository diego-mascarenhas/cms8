<?php

namespace App\Console\Commands;

use App\Models\InvoiceSync;
use App\Services\Billing\StripeInvoiceCoreImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportStripeInvoiceSyncsCommand extends Command
{
    protected $signature = 'invoice-syncs:import-stripe
                            {--team_id= : Import only one team}
                            {--external_id= : Import only this Stripe invoice id}
                            {--limit=500 : Max sync rows to process}
                            {--reconcile : Also refresh rows that already have a core invoice (same team + Stripe source id)}
                            {--fallback-email : Resolve enterprise by email when customer_id/code does not match}
                            {--link-code-on-email-match : When fallback by email succeeds uniquely, write Stripe customer_id into enterprises.code}
                            {--dry-run : Preview without writing}';

    protected $description = 'Map Stripe invoice_syncs rows into core invoices table (idempotent by source reference)';

    public function handle(StripeInvoiceCoreImportService $importService): int
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
        $fallbackEmail = (bool) $this->option('fallback-email');
        $linkCodeOnEmailMatch = (bool) $this->option('link-code-on-email-match');

        $query = InvoiceSync::query()->where('provider', 'stripe');

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
                    ->where('invoices.source_provider', 'stripe');
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
                ->where('source_provider', 'stripe')
                ->where('source_reference_id', $row->external_id)
                ->first();

            $invoice = $importService->importFromSyncRow(
                $row,
                $fallbackEmail,
                $linkCodeOnEmailMatch,
                $dryRun,
            );

            if (! $invoice && ! $dryRun)
            {
                $skipped++;
                $reason = $fallbackEmail ? 'customer_id/code or unique email' : 'customer_id/code';
                $this->warn("Skip {$row->external_id}: enterprise not found by {$reason} for team {$row->team_id}");

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
