<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\WeeklyWorkPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class StrategyWeeklyAnalysisCommand extends Command
{
    protected $signature = 'strategy:weekly-analysis {--team=}';

    protected $description = 'Store the CFO projection and refresh the weekly analysis for each team.';

    public function handle(FinanceCfoBriefService $briefs, WeeklyWorkPlanService $plans): int
    {
        $teamId = $this->option('team');
        $year = (int) now()->year;
        $teams = Team::query()
            ->when($teamId, fn ($query) => $query->where('id', (int) $teamId))
            ->cursor();

        foreach ($teams as $team)
        {
            try
            {
                $briefs->storeProjection($team, $year);
                $this->line("Team {$team->id} projection stored.");
            } catch (Throwable $exception)
            {
                Log::warning('CFO projection failed.', [
                    'team_id' => $team->id,
                    'message' => $exception->getMessage(),
                ]);
                $this->error("Team {$team->id} projection failed.");
            }

            foreach ($team->allUsers()->unique('id') as $user)
            {
                try
                {
                    $plans->present($user, $team, now());
                } catch (Throwable $exception)
                {
                    Log::warning('Weekly analysis failed.', [
                        'team_id' => $team->id,
                        'user_id' => $user->id,
                        'message' => $exception->getMessage(),
                    ]);
                    $this->error("Team {$team->id} weekly analysis failed for user {$user->id}.");
                }
            }
        }

        return self::SUCCESS;
    }
}
