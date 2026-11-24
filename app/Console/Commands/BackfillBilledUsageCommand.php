<?php

namespace App\Console\Commands;

use App\Models\TokenUsageLog;
use App\Services\Billing\BilledUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillBilledUsageCommand extends Command
{
    protected $signature = 'billing:backfill-billed-usage
                            {--team= : Limit to one team id}
                            {--dry-run : Preview without writing}';

    protected $description = 'Fill billed_usage on past token logs using the multiplier in effect on each created_at';

    public function handle(): int
    {
        if (! Schema::hasColumn('token_usage_logs', 'billed_usage'))
        {
            $this->error('billed_usage is missing. Run the migration first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $teamId = is_numeric($this->option('team')) ? (int) $this->option('team') : null;

        $logs = $this->backfillLogs($teamId, $dryRun);

        $this->info(
            "Logs updated: {$logs}".
            ($dryRun ? ' | dry-run' : ''),
        );

        return self::SUCCESS;
    }

    private function backfillLogs(?int $teamId, bool $dryRun): int
    {
        $updated = 0;

        TokenUsageLog::withoutGlobalScopes()
            ->whereNull('billed_usage')
            ->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->orderBy('id')
            ->chunkById(200, function ($logs) use ($dryRun, &$updated): void
            {
                foreach ($logs as $log)
                {
                    $used = $log->used_toon ? (int) $log->toon_tokens : (int) $log->json_tokens;
                    if ($used <= 0)
                    {
                        $used = (int) $log->json_tokens;
                    }
                    $saved = $log->used_toon
                        ? max(0, (int) $log->json_tokens - (int) $log->toon_tokens)
                        : 0;

                    $updated++;
                    if ($dryRun)
                    {
                        continue;
                    }

                    $log->forceFill([
                        'billed_usage' => BilledUsage::capture(
                            $log->team_id !== null ? (int) $log->team_id : null,
                            $used,
                            0,
                            $saved,
                            null,
                            $log->created_at,
                        ),
                    ])->saveQuietly();
                }
            });

        return $updated;
    }
}
