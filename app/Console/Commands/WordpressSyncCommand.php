<?php

namespace App\Console\Commands;

use App\Jobs\SyncWordPressContentJob;
use App\Models\Team;
use App\Services\WordPressService;
use Illuminate\Console\Command;

class WordpressSyncCommand extends Command
{
    protected $signature = 'wordpress:sync
                            {--team= : Sync only this team ID}';

    protected $description = 'Sync WordPress content (pages, posts, products) to local DB for the assistant.';

    public function handle(): int
    {
        $teamId = $this->option('team');

        if ($teamId !== null)
        {
            $team = Team::find($teamId);
            if (! $team)
            {
                $this->error("Team {$teamId} not found.");

                return self::FAILURE;
            }
            $teams = collect([$team]);
        } else
        {
            $teams = Team::with('settings')->get();
        }

        $wp = null;
        $count = 0;
        foreach ($teams as $team)
        {
            $wp = new WordPressService($team);
            if (! $wp->isConfigured())
            {
                continue;
            }
            SyncWordPressContentJob::dispatch($team);
            $count++;
            $this->info("Dispatched sync for team: {$team->name} (ID: {$team->id})");
        }

        if ($count === 0)
        {
            $this->warn('No teams with WordPress configured found.');

            return self::SUCCESS;
        }

        $this->info("Dispatched {$count} sync job(s). Run the queue worker to process them.");

        return self::SUCCESS;
    }
}
