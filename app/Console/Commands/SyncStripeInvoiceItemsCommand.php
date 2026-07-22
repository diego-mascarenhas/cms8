<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Services\Billing\StripeInvoiceItemImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncStripeInvoiceItemsCommand extends Command
{
    protected $signature = 'stripe:sync-invoice-items
                            {--team_id= : Limit to one team}
                            {--limit=500 : Max invoices to process}
                            {--dry-run : Preview without writing}';

    protected $description = 'Backfill invoice_items for Stripe core invoices from invoice_syncs.raw_payload';

    public function handle(StripeInvoiceItemImporter $itemImporter): int
    {
        if (! Schema::hasTable('invoice_syncs') || ! Schema::hasTable('invoice_items'))
        {
            $this->error('Required tables missing. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;

        $query = Invoice::query()
            ->withoutGlobalScopes()
            ->where('source_provider', 'stripe')
            ->whereNotNull('source_reference_id')
            ->orderBy('id');

        if ($teamId)
        {
            $query->where('team_id', $teamId);
        }

        $invoices = $query->limit($limit)->get();
        $synced = 0;
        $skipped = 0;

        foreach ($invoices as $invoice)
        {
            $sync = InvoiceSync::query()
                ->where('provider', 'stripe')
                ->where('team_id', $invoice->team_id)
                ->where('external_id', $invoice->source_reference_id)
                ->first();

            if (! $sync instanceof InvoiceSync)
            {
                $skipped++;

                continue;
            }

            $lines = data_get($sync->raw_payload, 'lines.data', []);
            if (! is_array($lines) || $lines === [])
            {
                $skipped++;

                continue;
            }

            if ($dryRun)
            {
                $this->line("Would sync items for invoice #{$invoice->id} ({$invoice->source_reference_id}) — ".count($lines).' lines');
                $synced++;

                continue;
            }

            $itemImporter->syncForInvoice($invoice, $sync);
            $synced++;
        }

        $this->info(($dryRun ? 'Would sync' : 'Synced').": {$synced}; skipped: {$skipped}");

        return self::SUCCESS;
    }
}
