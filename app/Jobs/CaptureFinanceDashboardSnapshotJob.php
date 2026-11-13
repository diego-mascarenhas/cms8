<?php

namespace App\Jobs;

use App\Models\Team;
use App\Services\Finance\FinanceDashboardSnapshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CaptureFinanceDashboardSnapshotJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $teamId,
        public readonly ?int $year = null,
    ) {}

    public function handle(FinanceDashboardSnapshotService $snapshots): void
    {
        $team = Team::query()->find($this->teamId);

        if ($team === null)
        {
            return;
        }

        try
        {
            $snapshots->capture($team, $this->year);
        } catch (Throwable $exception)
        {
            Log::warning('Finance dashboard snapshot failed', [
                'team_id' => $this->teamId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
