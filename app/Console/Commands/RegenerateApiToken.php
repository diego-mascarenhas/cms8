<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;

class RegenerateApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:regenerate-token {team_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new API token for a team (keeps existing tokens)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $teamId = $this->argument('team_id');

        if ($teamId)
        {
            $team = Team::find($teamId);
            if (! $team)
            {
                $this->error("Team with ID {$teamId} not found.");

                return 1;
            }
            $this->regenerateTeamToken($team);
        } else
        {
            $teams = Team::whereHas('settings', function ($query)
            {
                $query->whereIn('key', ['api_token_hash', 'api_tokens']);
            })->get();

            if ($teams->isEmpty())
            {
                $this->info('No teams with API tokens found.');

                return 0;
            }

            foreach ($teams as $team)
            {
                $this->regenerateTeamToken($team);
            }
        }

        return 0;
    }

    private function regenerateTeamToken(Team $team): void
    {
        $created = $team->createApiToken('API Access Token', '*');

        $this->info("Created API token for team: {$team->name}");
        $this->line('New token: '.$created['plain']);
    }
}
