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
    protected $description = 'Regenerate API token for a team to include plain token storage';

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
            // Regenerate for all teams that have tokens
            $teams = Team::whereHas('settings', function ($query)
            {
                $query->where('key', 'api_token_hash');
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

    private function regenerateTeamToken(Team $team)
    {
        // Generate new token
        $tokenValue = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenValue);

        // Update settings
        $team->setSetting('api_token_hash', $tokenHash, [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $team->setSetting('api_token_plain', $tokenValue, [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $this->info("Regenerated API token for team: {$team->name}");
        $this->line("New token: {$tokenValue}");
    }
}
