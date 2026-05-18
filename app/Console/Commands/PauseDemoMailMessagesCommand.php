<?php

namespace App\Console\Commands;

use App\Models\Team;
use Database\Seeders\DemoMailCampaignData;
use Illuminate\Console\Command;

class PauseDemoMailMessagesCommand extends Command
{
    protected $signature = 'demo:pause-mail-messages
                            {--team= : Team ID (omit to pause demo mail on every team)}';

    protected $description = 'Pause all [Demo] messages and campaigns from DemoMailCampaignData and cancel pending deliveries';

    public function handle(): int
    {
        $teamOption = $this->option('team');

        if ($teamOption !== null && $teamOption !== '')
        {
            $team = Team::withoutGlobalScopes()->find((int) $teamOption);
            if (! $team)
            {
                $this->error('Team not found: '.$teamOption);

                return self::FAILURE;
            }

            DemoMailCampaignData::pauseDemoMailFixtures((int) $team->id, $this);

            return self::SUCCESS;
        }

        DemoMailCampaignData::pauseDemoMailFixtures(null, $this);

        return self::SUCCESS;
    }
}
