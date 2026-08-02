<?php

namespace App\Console\Commands;

use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Services\Billing\ServiceSyncImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CategorizeStripeInvoiceItemsCommand extends Command
{
    protected $signature = 'invoices:categorize-stripe-items
                            {--team_id= : Limit to one team}
                            {--limit=2000 : Max invoice items to process}
                            {--dry-run : Preview without writing}';

    protected $description = 'Backfill invoice_items.category_id from linked services for Stripe sell lines';

    public function handle(ServiceSyncImporter $importer): int
    {
        if (! Schema::hasTable('invoice_items') || ! Schema::hasTable('invoice_syncs'))
        {
            $this->error('Required tables are missing. Run migrations first.');

            return self::FAILURE;
        }

        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $query = InvoiceItem::query()
            ->select('invoice_items.*')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNull('invoice_items.category_id')
            ->where('invoices.operation', 'sell')
            ->where('invoices.source_provider', 'stripe')
            ->whereNotNull('invoices.source_reference_id')
            ->orderBy('invoice_items.id');

        if ($teamId)
        {
            $query->where('invoices.team_id', $teamId);
        }

        $items = $query->limit($limit)->get();

        $processed = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item)
        {
            if (! $item instanceof InvoiceItem)
            {
                continue;
            }

            $processed++;

            $invoice = $item->invoice()->withoutGlobalScopes()->first();
            if (! $invoice)
            {
                $skipped++;

                continue;
            }

            $sync = InvoiceSync::query()
                ->where('team_id', $invoice->team_id)
                ->where('provider', 'stripe')
                ->where('external_id', $invoice->source_reference_id)
                ->first();

            if (! $sync || ! filled($sync->stripe_subscription_id))
            {
                $skipped++;

                continue;
            }

            $categoryId = $importer->resolveCategoryIdForInvoiceItem(
                (int) $invoice->team_id,
                (string) $sync->stripe_subscription_id,
            );

            if (! $categoryId)
            {
                $skipped++;

                continue;
            }

            if (! $dryRun)
            {
                $item->forceFill(['category_id' => $categoryId])->save();
            }

            $updated++;
        }

        $this->table(['Metric', 'Count'], [
            ['Processed', $processed],
            ['Updated', $updated],
            ['Skipped', $skipped],
            ['Dry run', $dryRun ? 'yes' : 'no'],
        ]);

        return self::SUCCESS;
    }
}
