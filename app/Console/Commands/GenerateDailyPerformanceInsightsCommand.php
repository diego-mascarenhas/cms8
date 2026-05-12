<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\UserDailyPerformanceInsightService;
use Illuminate\Console\Command;

class GenerateDailyPerformanceInsightsCommand extends Command
{
    protected $signature = 'performance-insights:generate {--date=} {--team=} {--user=} {--force}';

    protected $description = 'Create or refresh daily performance insight rows for admin and root users only (per team). Without --force, existing rows for that calendar day are left unchanged.';

    public function handle(UserDailyPerformanceInsightService $service): int
    {
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now();
        $teamId = $this->option('team');
        $userId = $this->option('user');
        $force = (bool) $this->option('force');

        $teams = Team::query()
            ->with('modules')
            ->when($teamId, fn ($q) => $q->where('id', (int) $teamId))
            ->cursor();

        $count = 0;
        $skippedNonPrivileged = 0;
        foreach ($teams as $team)
        {
            if (! $team->hasModule('performance_insights'))
            {
                continue;
            }

            foreach ($team->allUsers()->unique('id') as $user)
            {
                if ($userId && (int) $user->id !== (int) $userId)
                {
                    continue;
                }
                if (! $user->hasAnyRole(['admin', 'root']))
                {
                    $skippedNonPrivileged++;

                    continue;
                }
                $service->ensureTodayRecord($user, $team, null, $date, $force);
                $count++;
            }
        }

        if ($skippedNonPrivileged > 0)
        {
            $this->line("Skipped {$skippedNonPrivileged} user-team pair(s) (not admin/root).");
        }

        $this->info("Processed {$count} user-team pairs".($force ? ' (force refresh).' : '.'));

        return self::SUCCESS;
    }
}
