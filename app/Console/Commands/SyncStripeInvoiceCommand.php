<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Team;
use App\Services\Billing\StripeCollectedInvoicePaymentReconciliationService;
use App\Services\Billing\StripeInvoiceSyncRefresher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Stripe\StripeClient;

class SyncStripeInvoiceCommand extends Command
{
    protected $signature = 'stripe:sync-invoice
                            {external_id : Stripe invoice id (in_...)}
                            {--team_id= : Team id when multiple teams have Stripe configured}
                            {--import : Import the row into core invoices after syncing}
                            {--fallback-email : Pass through to invoice-syncs:import-stripe}
                            {--link-code-on-email-match : Pass through to invoice-syncs:import-stripe}';

    protected $description = 'Refresh one Stripe invoice in invoice_syncs (e.g. after external payment marked paid in Stripe)';

    public function handle(
        StripeInvoiceSyncRefresher $refresher,
        StripeCollectedInvoicePaymentReconciliationService $paymentReconciliationService,
    ): int {
        if (! Schema::hasTable('invoice_syncs'))
        {
            $this->error('Table invoice_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $externalId = trim((string) $this->argument('external_id'));
        if ($externalId === '' || ! str_starts_with($externalId, 'in_'))
        {
            $this->error('external_id must be a Stripe invoice id starting with in_');

            return self::FAILURE;
        }

        $team = $this->resolveTeam($externalId);
        if (! $team instanceof Team)
        {
            return self::FAILURE;
        }

        $secret = trim((string) $team->getSetting('stripe_secret'));
        if ($secret === '')
        {
            $this->error("Team {$team->id} has no stripe_secret configured.");

            return self::FAILURE;
        }

        $client = new StripeClient($secret);
        $sync = $refresher->refreshFromStripe($client, $team->id, $externalId);

        if (! $sync instanceof InvoiceSync)
        {
            $this->error('Could not upsert invoice_sync row.');

            return self::FAILURE;
        }

        $this->info(
            "Synced {$sync->external_id}: status={$sync->status}, paid=".($sync->paid ? 'yes' : 'no')
            .", amount_paid={$sync->amount_paid}, amount_remaining={$sync->amount_remaining}",
        );

        if (! (bool) $this->option('import'))
        {
            $this->line('Run invoice-syncs:import-stripe --reconcile --external_id='.$externalId.' to update the core invoice.');

            return self::SUCCESS;
        }

        $importOptions = [
            '--reconcile' => true,
            '--external_id' => $externalId,
            '--team_id' => (string) $team->id,
            '--limit' => '1',
        ];

        if ((bool) $this->option('fallback-email'))
        {
            $importOptions['--fallback-email'] = true;
        }

        if ((bool) $this->option('link-code-on-email-match'))
        {
            $importOptions['--link-code-on-email-match'] = true;
        }

        $exitCode = Artisan::call('invoice-syncs:import-stripe', $importOptions);
        $this->output->write(Artisan::output());

        if ($exitCode !== 0)
        {
            return self::FAILURE;
        }

        $invoice = Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', $externalId)
            ->first();

        if ($invoice instanceof Invoice && (float) $invoice->balance <= 0)
        {
            $reconciled = $paymentReconciliationService->reconcileInvoice($invoice);
            $this->line($reconciled
                ? 'Payment reconciliation: created or linked payment for collected invoice.'
                : 'Payment reconciliation: no missing payment to create.');
        }

        return self::SUCCESS;
    }

    private function resolveTeam(string $externalId): ?Team
    {
        $teamIdOption = $this->option('team_id');
        if ($teamIdOption !== null && $teamIdOption !== '')
        {
            $team = Team::query()->find((int) $teamIdOption);

            if (! $team instanceof Team)
            {
                $this->error('Team not found.');

                return null;
            }

            return $team;
        }

        $existing = InvoiceSync::query()
            ->where('provider', 'stripe')
            ->where('external_id', $externalId)
            ->first();

        if ($existing instanceof InvoiceSync)
        {
            $team = Team::query()->find($existing->team_id);
            if ($team instanceof Team)
            {
                return $team;
            }
        }

        $teamsWithSecret = Team::query()->get()->filter(
            fn (Team $team): bool => trim((string) $team->getSetting('stripe_secret')) !== '',
        );

        if ($teamsWithSecret->count() === 1)
        {
            return $teamsWithSecret->first();
        }

        $this->error('Pass --team_id= when more than one team has stripe_secret configured.');

        return null;
    }
}
