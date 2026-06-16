<?php

namespace App\Console\Commands;

use App\Services\Billing\CuenticaCollectedInvoicePaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcileCuenticaCollectedInvoicePaymentsCommand extends Command
{
    protected $signature = 'invoices:reconcile-cuentica-collected-payments
                            {--team_id= : Limit to one team}
                            {--limit=100 : Max Cuéntica invoices to inspect per run}
                            {--dry-run : Preview without writing}';

    protected $description = 'Create missing payments for Cuéntica invoices with collected charges or paid status';

    public function handle(CuenticaCollectedInvoicePaymentReconciliationService $service): int
    {
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $stats = $service->reconcile(
            teamId: $teamId,
            limit: $limit,
            dryRun: $dryRun,
        );

        $this->info(
            'Matched: '.$stats['matched']
            .' | created: '.$stats['created']
            .' | skipped: '.$stats['skipped']
            .($dryRun ? ' | dry-run' : ''),
        );

        return self::SUCCESS;
    }
}
