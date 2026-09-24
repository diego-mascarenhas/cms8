<?php

namespace App\Console\Commands;

use App\Models\TeamUsageInvoice;
use App\Services\Billing\TeamUsageInvoiceStripeGateway;
use Illuminate\Console\Command;
use Throwable;

class DiscardUsageInvoiceDraftCommand extends Command
{
    protected $signature = 'billing:discard-usage-invoice-draft
                            {invoice : Stripe invoice id (in_...)}
                            {--keep-stripe : Only remove the Humano row}';

    protected $description = 'Delete a usage invoice draft so the period can be issued again';

    public function handle(TeamUsageInvoiceStripeGateway $stripe): int
    {
        $invoiceId = trim((string) $this->argument('invoice'));
        $record = TeamUsageInvoice::query()->where('stripe_invoice_id', $invoiceId)->first();

        if (! $this->option('keep-stripe'))
        {
            try
            {
                $stripe->deleteDraftInvoice($invoiceId);
                $this->info('Deleted Stripe draft '.$invoiceId);
            } catch (Throwable $e)
            {
                $this->error('Stripe: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        if ($record)
        {
            $record->delete();
            $this->info('Removed Humano row for team '.$record->team_id.' '.$record->period_from?->toDateString().'–'.$record->period_to?->toDateString());
        } else
        {
            $this->warn('No Humano row for '.$invoiceId);
        }

        return self::SUCCESS;
    }
}
