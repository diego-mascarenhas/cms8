<?php

namespace App\Console\Commands;

use App\Models\AgentConversationMessage;
use App\Models\TokenUsageLog;
use App\Services\Billing\BilledUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillBilledUsageCommand extends Command
{
    protected $signature = 'billing:backfill-billed-usage
                            {--team= : Limit to one team id}
                            {--dry-run : Preview without writing}';

    protected $description = 'Fill billed_usage on past token logs and assistant messages using the multiplier in effect on each created_at';

    public function handle(): int
    {
        if (! Schema::hasColumn('token_usage_logs', 'billed_usage') || ! Schema::hasColumn('agent_conversation_messages', 'billed_usage'))
        {
            $this->error('billed_usage is missing. Run the migration first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $teamId = is_numeric($this->option('team')) ? (int) $this->option('team') : null;

        $logs = $this->backfillLogs($teamId, $dryRun);
        $messages = $this->backfillMessages($teamId, $dryRun);

        $this->info(
            "Logs updated: {$logs} | messages updated: {$messages['updated']} | messages skipped: {$messages['skipped']}".
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

    /**
     * @return array{updated: int, skipped: int}
     */
    private function backfillMessages(?int $teamId, bool $dryRun): array
    {
        $updated = 0;
        $skipped = 0;

        AgentConversationMessage::query()
            ->where('role', 'assistant')
            ->whereNull('billed_usage')
            ->when($teamId !== null, function ($query) use ($teamId): void
            {
                $query->whereHas('conversation', fn ($conversation) => $conversation->where('team_id', $teamId));
            })
            ->with('conversation:id,team_id')
            ->orderBy('id')
            ->chunkById(200, function ($messages) use ($dryRun, &$updated, &$skipped): void
            {
                foreach ($messages as $message)
                {
                    $usage = is_array($message->usage) ? $message->usage : [];
                    $meta = is_array($message->meta) ? $message->meta : [];
                    $prompt = (int) ($usage['prompt_tokens'] ?? 0);
                    $completion = (int) ($usage['completion_tokens'] ?? 0);
                    if ($prompt + $completion <= 0)
                    {
                        $prompt = (int) ($usage['total_tokens'] ?? 0);
                        $completion = 0;
                    }
                    if ($prompt + $completion <= 0)
                    {
                        $skipped++;

                        continue;
                    }

                    $updated++;
                    if ($dryRun)
                    {
                        continue;
                    }

                    $model = $usage['model'] ?? $meta['model'] ?? null;
                    $team = $message->conversation?->team_id;

                    $message->forceFill([
                        'billed_usage' => BilledUsage::capture(
                            is_numeric($team) ? (int) $team : null,
                            $prompt,
                            $completion,
                            max(0, (int) ($usage['tokens_saved'] ?? 0)),
                            is_string($model) ? $model : null,
                            $message->created_at,
                        ),
                    ])->saveQuietly();
                }
            });

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
