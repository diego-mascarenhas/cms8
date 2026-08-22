<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\ContactDailySentimentService;
use Illuminate\Console\Command;

class ComputeDailyTeamSentimentBalanceCommand extends Command
{
    protected $signature = 'sentiment:compute-daily {--team=}';

    protected $description = 'Analyze the full inbound chat and email context from the last 24 hours per active contact.';

    public function handle(ContactDailySentimentService $service): int
    {
        $teamId = $this->option('team');

        $teams = Team::query()
            ->when($teamId, fn ($query) => $query->where('id', (int) $teamId))
            ->cursor();

        $teamsProcessed = 0;
        $contactsProcessed = 0;

        foreach ($teams as $team)
        {
            if (! $team->hasModule('insights'))
            {
                continue;
            }

            $teamsProcessed++;
            $contactsProcessed += $service->processTeam($team);
        }

        $this->info("Processed {$contactsProcessed} contact(s) across {$teamsProcessed} team(s).");

        return self::SUCCESS;
    }
}
