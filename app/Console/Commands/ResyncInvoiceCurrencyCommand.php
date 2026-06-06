<?php

namespace App\Console\Commands;

use App\Services\Finance\InvoiceCurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ResyncInvoiceCurrencyCommand extends Command
{
    protected $signature = 'invoices:resync-currency
                            {--team_id= : Limit to one team}
                            {--legacy : Also map manual invoices from mysql_legacy facturas.id_moneda}
                            {--only-null : Update only invoices without currency_id}
                            {--dry-run : Preview without writing}';

    protected $description = 'Backfill invoices.currency_id from Stripe invoice_syncs and/or legacy CMS moneda';

    public function handle(InvoiceCurrencyService $service): int
    {
        if (! Schema::hasColumn('invoices', 'currency_id'))
        {
            $this->error('Column invoices.currency_id does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $fromLegacy = (bool) $this->option('legacy');
        $onlyNull = (bool) $this->option('only-null');
        $dryRun = (bool) $this->option('dry-run');

        $stats = $service->resync(
            teamId: $teamId,
            fromLegacy: $fromLegacy,
            onlyNull: $onlyNull,
            dryRun: $dryRun,
        );

        $this->info(
            'Updated: '.$stats['updated']
            .' | stripe: '.$stats['stripe']
            .' | legacy: '.$stats['legacy']
            .($dryRun ? ' | dry-run' : '')
            .($onlyNull ? ' | only-null' : ''),
        );

        return self::SUCCESS;
    }
}
