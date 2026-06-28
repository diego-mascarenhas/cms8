<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckInvoiceItemsImport extends Command
{
    protected $signature = 'invoice:check-items-import';

    protected $description = 'Check which invoices have items in legacy but not in current system';

    public function handle(): int
    {
        $this->info('🔍 Checking invoice items import status...');
        $this->newLine();

        try
        {
            // Get all invoices that have items in legacy
            $legacyItems = app('db')->connection('mysql_legacy')
                ->table('facturas_items')
                ->where('grupo', env('CMS_GROUP', 502))
                ->select('id_factura', app('db')->raw('COUNT(*) as item_count'))
                ->groupBy('id_factura')
                ->get();

            $this->info("Found {$legacyItems->count()} invoices with items in legacy system");
            $this->newLine();

            $missingItems = [];
            $hasItems = [];
            $noInvoice = [];

            foreach ($legacyItems as $legacy)
            {
                $invoiceId = $legacy->id_factura;
                $legacyItemCount = $legacy->item_count;

                // Check if invoice exists
                $invoiceExists = app('db')->table('invoices')->where('id', $invoiceId)->exists();

                if (! $invoiceExists)
                {
                    $noInvoice[] = [
                        'invoice_id' => $invoiceId,
                        'legacy_items' => $legacyItemCount,
                    ];

                    continue;
                }

                // Check how many items exist in current system
                $currentItemCount = app('db')->table('invoice_items')
                    ->where('invoice_id', $invoiceId)
                    ->count();

                if ($currentItemCount == 0)
                {
                    $missingItems[] = [
                        'invoice_id' => $invoiceId,
                        'legacy_items' => $legacyItemCount,
                        'current_items' => 0,
                    ];
                } elseif ($currentItemCount < $legacyItemCount)
                {
                    $hasItems[] = [
                        'invoice_id' => $invoiceId,
                        'legacy_items' => $legacyItemCount,
                        'current_items' => $currentItemCount,
                        'missing' => $legacyItemCount - $currentItemCount,
                    ];
                }
            }

            // Display results
            if (count($noInvoice) > 0)
            {
                $this->warn('⚠️  '.count($noInvoice)." invoices have items in legacy but invoice doesn't exist:");
                $this->table(
                    ['Invoice ID', 'Legacy Items'],
                    array_map(function ($item)
                    {
                        return [$item['invoice_id'], $item['legacy_items']];
                    }, array_slice($noInvoice, 0, 20)),
                );
                if (count($noInvoice) > 20)
                {
                    $this->info('... and '.(count($noInvoice) - 20).' more');
                }
                $this->newLine();
            }

            if (count($missingItems) > 0)
            {
                $this->error('❌ '.count($missingItems).' invoices have NO items imported (but have items in legacy):');
                $this->table(
                    ['Invoice ID', 'Legacy Items', 'Current Items'],
                    array_map(function ($item)
                    {
                        return [$item['invoice_id'], $item['legacy_items'], $item['current_items']];
                    }, array_slice($missingItems, 0, 20)),
                );
                if (count($missingItems) > 20)
                {
                    $this->info('... and '.(count($missingItems) - 20).' more');
                }
                $this->newLine();
            }

            if (count($hasItems) > 0)
            {
                $this->warn('⚠️  '.count($hasItems).' invoices have PARTIAL items imported:');
                $this->table(
                    ['Invoice ID', 'Legacy Items', 'Current Items', 'Missing'],
                    array_map(function ($item)
                    {
                        return [$item['invoice_id'], $item['legacy_items'], $item['current_items'], $item['missing']];
                    }, array_slice($hasItems, 0, 20)),
                );
                if (count($hasItems) > 20)
                {
                    $this->info('... and '.(count($hasItems) - 20).' more');
                }
                $this->newLine();
            }

            // Summary
            $this->info('📊 Summary:');
            $this->line('   • Invoices with items in legacy: '.count($legacyItems));
            $this->line('   • Invoices missing all items: '.count($missingItems));
            $this->line('   • Invoices with partial items: '.count($hasItems));
            $this->line("   • Invoices that don't exist: ".count($noInvoice));

            if (count($missingItems) > 0 || count($hasItems) > 0)
            {
                $this->newLine();
                $this->info('💡 Recommended: php artisan invoices:resync-items --sync-currency --team_id=2');
            }

            return Command::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('Error checking invoice items: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
