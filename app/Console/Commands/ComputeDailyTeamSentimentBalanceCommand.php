<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\ContactDailySentimentService;
use Illuminate\Console\Command;

class ComputeDailyTeamSentimentBalanceCommand extends Command
{
    protected $signature = 'sentiment:compute-daily {--team=}';

    protected $description = 'Analyze the last 24 hours of inbound chat and email, then store a 3-line digest on the contact.';

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
            $contacts = $service->processTeam($team);
            if ($contacts < 1)
            {
                continue;
            }

            $teamsProcessed++;
            $contactsProcessed += $contacts;
        }

        $this->info("Processed {$contactsProcessed} contact(s) across {$teamsProcessed} team(s).");

        return self::SUCCESS;
    }
}
