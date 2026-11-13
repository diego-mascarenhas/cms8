<?php

namespace App\Console\Commands;

use App\Jobs\CaptureFinanceDashboardSnapshotJob;
use App\Models\Payment;
use App\Models\Team;
use Illuminate\Console\Command;

class CaptureFinanceDashboardSnapshotsCommand extends Command
{
    protected $signature = 'finance:capture-dashboard-snapshots {--team=} {--sync : Run inline instead of queueing}';

    protected $description = 'Capture finance dashboard snapshots for teams with payment activity.';

    public function handle(): int
    {
        $teamOption = $this->option('team');
        $sync = (bool) $this->option('sync');
        $year = (int) now()->year;

        $teamIds = Payment::query()
            ->withoutGlobalScopes()
            ->when($teamOption, fn ($query) => $query->where('team_id', (int) $teamOption))
            ->distinct()
            ->pluck('team_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($teamIds->isEmpty() && $teamOption)
        {
            $teamIds = collect([(int) $teamOption])->filter(fn (int $id): bool => $id > 0);
        }

        $count = 0;

        foreach ($teamIds as $teamId)
        {
            if (! Team::query()->whereKey($teamId)->exists())
            {
                continue;
            }

            if ($sync)
            {
                CaptureFinanceDashboardSnapshotJob::dispatchSync($teamId, $year);
            } else
            {
                CaptureFinanceDashboardSnapshotJob::dispatch($teamId, $year);
            }

            $count++;
            $this->line("Team {$teamId} snapshot ".($sync ? 'captured' : 'queued').'.');
        }

        $this->info("Processed {$count} team(s).");

        return self::SUCCESS;
    }
}
