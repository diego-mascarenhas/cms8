<?php

namespace App\Console\Commands;

use App\Mail\DailyPerformanceInsightMail;
use App\Models\Team;
use App\Models\UserDailyPerformanceInsight;
use App\Services\UserDailyPerformanceInsightNotificationService;
use App\Services\UserDailyPerformanceInsightService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class GenerateDailyPerformanceInsightsCommand extends Command
{
    protected $signature = 'performance-insights:generate {--date=} {--team=} {--user=} {--force}';

    protected $description = 'Create or refresh daily performance insight rows for admin users only (per team). Without --force, existing rows for that calendar day are left unchanged.';

    public function handle(
        UserDailyPerformanceInsightService $service,
        UserDailyPerformanceInsightNotificationService $notificationService,
    ): int {
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now();
        $teamId = $this->option('team');
        $userId = $this->option('user');
        $force = (bool) $this->option('force');

        $teams = Team::query()
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
                if (! UserDailyPerformanceInsight::userEligibleForEvaluation($user))
                {
                    $skippedNonPrivileged++;

                    continue;
                }

                $insightDate = $date->toDateString();
                $hadInsight = $notificationService->insightExistsForDay($user, $team, $insightDate);

                $insight = $service->ensureTodayRecord($user, $team, null, $date, $force);
                $count++;

                if ($team->performanceInsightsInAppNotificationEnabled())
                {
                    $notificationService->syncForInsight($insight, $team, markUnread: $force || ! $hadInsight);
                }

                if (config('daily_performance_insight.send_email', true) && $user->email)
                {
                    Mail::to($user->email)->send(new DailyPerformanceInsightMail($insight));
                }
            }
        }

        if ($skippedNonPrivileged > 0)
        {
            $this->line("Skipped {$skippedNonPrivileged} user-team pair(s) (not admin).");
        }

        $this->info("Processed {$count} user-team pairs".($force ? ' (force refresh).' : '.'));

        return self::SUCCESS;
    }
}
