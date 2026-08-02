<?php

namespace App\Console\Commands;

use App\Services\Finance\InvoiceItemCategoryBackfillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CategorizeStripeInvoiceItemsCommand extends Command
{
    protected $signature = 'invoices:categorize-stripe-items
                            {--team_id= : Limit to one team}
                            {--limit=2000 : Max invoice items to process}
                            {--from-prior-invoices : Also match prior sell lines (same client, description and amount)}
                            {--replace-generic-parents : Also reclassify lines currently on coarse parent categories (e.g. Servicios)}
                            {--dry-run : Preview without writing}';

    protected $description = 'Backfill invoice_items.category_id from linked services and optionally prior invoices';

    public function handle(InvoiceItemCategoryBackfillService $backfill): int
    {
        if (! Schema::hasTable('invoice_items') || ! Schema::hasTable('invoice_syncs'))
        {
            $this->error('Required tables are missing. Run migrations first.');

            return self::FAILURE;
        }

        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $fromPrior = (bool) $this->option('from-prior-invoices');
        $replaceGeneric = (bool) $this->option('replace-generic-parents');

        $this->info('Backfilling Stripe sell invoice item categories'
            .($fromPrior ? ' (with prior-invoice matching)' : '')
            .($replaceGeneric ? ' (including generic parents)' : '')
            .($dryRun ? ' [dry-run]' : ''));

        $stats = $backfill->backfill(
            teamId: $teamId,
            limit: $limit,
            dryRun: $dryRun,
            fromPriorInvoices: $fromPrior,
            replaceGenericParents: $replaceGeneric,
        );

        $this->table(['Metric', 'Count'], [
            ['Processed', $stats['processed']],
            ['Updated', $stats['updated']],
            ['From linked service', $stats['from_service']],
            ['From prior invoices', $stats['from_prior']],
            ['Services category filled', $stats['services_updated']],
            ['Skipped', $stats['skipped']],
            ['Dry run', $dryRun ? 'yes' : 'no'],
        ]);

        return self::SUCCESS;
    }
}
