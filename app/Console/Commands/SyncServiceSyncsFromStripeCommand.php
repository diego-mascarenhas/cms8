<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\SyncStripeSubscriptions as SyncStripeSubscriptionsAction;
use App\Models\Team;
use App\Services\Stripe\StripeSubscriptionService;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class SyncServiceSyncsFromStripeCommand extends Command
{
    protected $signature = 'stripe:sync-service-syncs
                            {--team_id= : Sync only one team using its Stripe secret from team settings}
                            {--limit=1000 : Maximum service_syncs rows to process per team in this run}
                            {--dry-run : Preview without writing}';

    protected $description = 'Sync Stripe subscriptions into service_syncs staging table';

    public function handle(): int
    {
        $teamId = $this->option('team_id');
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $teams = Team::query()->with('settings');
        if ($teamId)
        {
            $teams->whereKey((int) $teamId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
        $teams = $teams->get();
        if ($teams->isEmpty())
        {
            $this->warn('No teams found for synchronization.');

            return self::SUCCESS;
        }

        $totalProcessed = 0;
        $processedTeams = 0;

        foreach ($teams as $team)
        {
            $secret = trim((string) $team->getSetting('stripe_secret'));
            if ($secret === '')
            {
                $this->line("Skipping team {$team->id} ({$team->name}): missing stripe_secret in team settings.");

                continue;
            }

            $service = new StripeSubscriptionService(new StripeClient($secret));
            $sync = new SyncStripeSubscriptionsAction($service);

            if ($dryRun)
            {
                $count = 0;
                foreach ($service->subscriptions(['limit' => 100, 'status' => 'all']) as $_)
                {
                    $count++;
                    if ($count >= $limit)
                    {
                        break;
                    }
                }
            } else
            {
                $count = $sync->handle($team, $limit);
            }

            $this->line("Team {$team->id} ({$team->name}): {$count} service_syncs processed".($dryRun ? ' [dry-run]' : '').'.');
            $totalProcessed += $count;
            $processedTeams++;
        }

        if ($processedTeams === 0)
        {
            $this->warn('No team with stripe_secret found. Configure team Stripe secret key first.');

            return self::SUCCESS;
        }

        $this->info("Service sync staging complete: {$totalProcessed} rows processed in {$processedTeams} teams".($dryRun ? ' [dry-run]' : '').'.');

        return self::SUCCESS;
    }
}
