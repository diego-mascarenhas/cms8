<?php

namespace App\Console\Commands;

use App\Services\Finance\InvoiceItemLegacySyncService;
use Illuminate\Console\Command;

class ResyncInvoiceItemsCommand extends Command
{
    protected $signature = 'invoices:resync-items
                            {--team_id= : Limit to invoices of one team}
                            {--category_team_id=2 : Team id used when importing legacy categories}
                            {--invoice_id= : Resync items for a single invoice}
                            {--item_id= : Resync a single invoice item}
                            {--skip-categories : Skip importing categories from legacy categorias_generales}
                            {--sync-currency : Resync invoice currency from legacy facturas.id_moneda}
                            {--currency-only-null : With --sync-currency, only fill invoices without currency_id}
                            {--only-missing-categories : Only update rows missing category_id (default)}
                            {--all-fields : Update description, amounts and category for every matched item}
                            {--limit= : Maximum legacy items to process}
                            {--chunk=500 : Legacy rows per batch}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import legacy categories and resync invoice items from mysql_legacy.';

    public function handle(InvoiceItemLegacySyncService $service): int
    {
        if (! $service->legacyConnectionAvailable())
        {
            $this->error('Legacy database is not available or facturas_items table is missing.');

            return self::FAILURE;
        }

        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $invoiceId = $this->option('invoice_id') !== null ? (int) $this->option('invoice_id') : null;
        $itemId = $this->option('item_id') !== null ? (int) $this->option('item_id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $skipCategories = (bool) $this->option('skip-categories');
        $syncCurrency = (bool) $this->option('sync-currency');
        $onlyMissingCategories = ! (bool) $this->option('all-fields');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $chunkSize = max(50, (int) $this->option('chunk'));
        $categoryTeamId = (int) $this->option('category_team_id');

        $this->info('Resyncing invoice items from legacy CMS (group '.$service->cmsGroup().')'.($dryRun ? ' [dry-run]' : ''));

        if (! $skipCategories)
        {
            $this->line('Importing categories from legacy categorias_generales (parent_id tree)...');
            $allCategoryStats = $service->importAllCategoriesFromLegacy($categoryTeamId, $dryRun);
            $this->table(['Legacy categories', 'Count'], [
                ['Legacy rows', $allCategoryStats['total_legacy']],
                ['Parents imported', $allCategoryStats['imported_parents']],
                ['Parents updated', $allCategoryStats['updated_parents']],
                ['Child categories imported', $allCategoryStats['imported_children']],
                ['Child categories updated', $allCategoryStats['updated_children']],
                ['Children skipped (missing parent)', $allCategoryStats['skipped_children_missing_parent']],
            ]);
            $this->newLine();
        }

        $this->line('Resyncing invoice items...');

        $stats = $service->resyncItems(
            teamId: $teamId,
            invoiceId: $invoiceId,
            itemId: $itemId,
            importCategories: false,
            onlyMissingCategories: $onlyMissingCategories,
            dryRun: $dryRun,
            limit: $limit,
            chunkSize: $chunkSize,
            categoryTeamId: $categoryTeamId,
        );

        $this->table(['Metric', 'Count'], [
            ['Processed', $stats['processed']],
            ['Created', $stats['created']],
            ['Updated', $stats['updated']],
            ['Skipped (invoice missing)', $stats['skipped_no_invoice']],
            ['Rows with category resolved', $stats['category_assigned']],
            ['Rows still uncategorized', $stats['still_uncategorized']],
        ]);

        if ($syncCurrency)
        {
            $currencyStats = $service->resyncInvoiceCurrencies(
                teamId: $teamId,
                onlyNull: (bool) $this->option('currency-only-null'),
                dryRun: $dryRun,
            );

            $this->info(
                'Invoice currency: updated '.$currencyStats['updated']
                .' | legacy: '.$currencyStats['legacy']
                .' | stripe: '.$currencyStats['stripe']
                .' | manual ARS: '.$currencyStats['manual_default'],
            );
        }

        if ($stats['still_uncategorized'] > 0)
        {
            $this->warn('Some items remain without category. Re-run without --skip-categories, or use --all-fields to reassign existing rows.');
        }

        return self::SUCCESS;
    }
}
