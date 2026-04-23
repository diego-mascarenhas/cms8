<?php

namespace App\Console\Commands;

use App\Actions\Subscriptions\SyncStripeSubscriptions as SyncStripeSubscriptionsAction;
use App\Models\Team;
use App\Services\Stripe\StripeSubscriptionService;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class SyncStripeSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync
                            {--team_id= : Sync only one team using its Stripe secret from team settings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza las suscripciones de Stripe y registra cambios locales.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $teamId = $this->option('team_id');
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
            $count = $sync->handle($team);

            $this->line("Team {$team->id} ({$team->name}): {$count} subscriptions processed.");
            $totalProcessed += $count;
            $processedTeams++;
        }

        if ($processedTeams === 0)
        {
            $this->warn('No team with stripe_secret found. Configure team Stripe secret key first.');

            return self::SUCCESS;
        }

        $this->info("Sincronización completada: {$totalProcessed} suscripciones procesadas en {$processedTeams} equipos.");

        return self::SUCCESS;
    }
}
