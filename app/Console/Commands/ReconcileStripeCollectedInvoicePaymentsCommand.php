<?php

namespace App\Console\Commands;

use App\Services\Billing\StripeCollectedInvoicePaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcileStripeCollectedInvoicePaymentsCommand extends Command
{
    protected $signature = 'invoices:reconcile-stripe-collected-payments
                            {--team_id= : Limit to one team}
                            {--limit=100 : Max collected invoices to inspect per run}
                            {--dry-run : Preview without writing}';

    protected $description = 'Create missing payments for Stripe invoices marked collected without linked payments';

    public function handle(StripeCollectedInvoicePaymentReconciliationService $service): int
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
            'Core refreshed: '.$stats['core_refreshed']
            .' | matched: '.$stats['matched']
            .' | from payment_sync: '.$stats['from_payment_sync']
            .' | from invoice_sync: '.$stats['from_invoice_sync']
            .' | skipped: '.$stats['skipped']
            .($dryRun ? ' | dry-run' : ''),
        );

        return self::SUCCESS;
    }
}
