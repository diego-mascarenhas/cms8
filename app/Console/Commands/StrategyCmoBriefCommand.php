<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Marketing\CmoBriefService;
use Illuminate\Console\Command;

class StrategyCmoBriefCommand extends Command
{
    protected $signature = 'strategy:cmo-brief {--team=} {--year=}';

    protected $description = 'Write the CMO commercial reading for one team.';

    public function handle(CmoBriefService $briefs): int
    {
        $team = Team::query()->find((int) $this->option('team'));

        if ($team === null)
        {
            $this->error('Team not found.');

            return self::FAILURE;
        }

        $year = (int) ($this->option('year') ?: now()->year);
        $briefs->generate($team, $year > 0 ? $year : (int) now()->year);
        $this->line("Team {$team->id} CMO reading finished.");

        return self::SUCCESS;
    }
}
