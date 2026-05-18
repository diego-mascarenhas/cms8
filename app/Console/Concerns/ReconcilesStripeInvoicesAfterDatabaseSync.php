<?php

namespace App\Console\Concerns;

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ReconcilesStripeInvoicesAfterDatabaseSync
{
    protected function reconcileStripeInvoicesAfterDatabaseSync(bool $isDryRun): int
    {
        if ($isDryRun || (bool) $this->option('no-reconcile-stripe-invoices'))
        {
            return self::SUCCESS;
        }

        if (! Schema::hasTable('invoice_syncs') || ! Schema::hasTable('invoices'))
        {
            return self::SUCCESS;
        }

        $stripeSyncCount = (int) DB::table('invoice_syncs')
            ->where('provider', 'stripe')
            ->count();

        if ($stripeSyncCount === 0)
        {
            $this->warn('invoice_syncs is empty — pulling from Stripe API for teams with stripe_secret…');
            $this->syncStripeInvoicesFromApiForConfiguredTeams();
        }

        $limit = max(1, (int) env('DB_SYNC_STRIPE_INVOICE_IMPORT_LIMIT', 50000));

        $this->info('Mapping Stripe invoice status and balance into invoices…');

        return $this->call('invoice-syncs:import-stripe', [
            '--reconcile' => true,
            '--fallback-email' => true,
            '--link-code-on-email-match' => true,
            '--limit' => $limit,
        ]);
    }

    protected function syncStripeInvoicesFromApiForConfiguredTeams(): void
    {
        $limit = max(1, (int) env('DB_SYNC_STRIPE_INVOICE_SYNC_LIMIT', 5000));
        $recentDays = max(1, (int) env('DB_SYNC_STRIPE_INVOICE_RECENT_DAYS', 3650));

        $teams = Team::query()->with('settings')->get()->filter(
            static fn (Team $team): bool => filled(trim((string) $team->getSetting('stripe_secret'))),
        );

        if ($teams->isEmpty())
        {
            $this->warn('No team with stripe_secret configured. Invoice rows copied from prod will keep prod status/balance.');
            $this->line('Add stripe_secret in team settings, then run:');
            $this->line('  php artisan stripe:sync-invoices --mode=auto --limit=5000');
            $this->line('  php artisan invoice-syncs:import-stripe --reconcile --limit=50000');

            return;
        }

        foreach ($teams as $team)
        {
            $this->info("Stripe invoice sync for team {$team->id} ({$team->name})…");
            $this->call('stripe:sync-invoices', [
                '--team_id' => $team->id,
                '--mode' => 'auto',
                '--limit' => $limit,
                '--recent-days' => $recentDays,
            ]);
        }
    }
}
