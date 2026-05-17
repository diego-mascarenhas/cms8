<?php

namespace App\Services;

use App\Models\Module;
use App\Models\TokenUsageLog;
use Illuminate\Support\Facades\Log;

final class TokenUsageLogService
{
    /**
     * Persist API token usage for a team, attributed to a module when possible.
     */
    public static function log(
        int $teamId,
        string $service,
        int $totalTokens,
        ?int $moduleId = null,
        ?string $moduleKey = null,
        int $inputSize = 0,
        int $outputSize = 0,
        bool $usedToon = false,
        int $jsonTokens = 0,
        int $toonTokens = 0,
        int $savingsPercentage = 0,
    ): void {
        if ($totalTokens <= 0)
        {
            return;
        }

        try
        {
            TokenUsageLog::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'module_id' => self::resolveModuleId($moduleId, $moduleKey),
                'service' => $service,
                'json_size' => max(0, $inputSize),
                'toon_size' => max(0, $outputSize),
                'json_tokens' => $usedToon ? ($jsonTokens > 0 ? $jsonTokens : $totalTokens) : $totalTokens,
                'toon_tokens' => $usedToon ? ($toonTokens > 0 ? $toonTokens : $totalTokens) : 0,
                'savings_percentage' => $savingsPercentage,
                'used_toon' => $usedToon,
            ]);
        } catch (\Throwable $e)
        {
            Log::warning('Token usage log failed', [
                'service' => $service,
                'team_id' => $teamId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  object|array<string, mixed>|null  $usage  Laravel AI usage object or array.
     */
    public static function logFromAiResponse(
        int $teamId,
        string $service,
        mixed $usage,
        ?int $moduleId = null,
        ?string $moduleKey = null,
        int $inputSize = 0,
        int $outputSize = 0,
    ): void {
        $totalTokens = self::totalTokensFromUsage($usage);
        if ($totalTokens <= 0)
        {
            return;
        }

        self::log(
            teamId: $teamId,
            service: $service,
            totalTokens: $totalTokens,
            moduleId: $moduleId,
            moduleKey: $moduleKey,
            inputSize: $inputSize,
            outputSize: $outputSize,
        );
    }

    /**
     * @param  object|array<string, mixed>|null  $usage
     */
    public static function totalTokensFromUsage(mixed $usage): int
    {
        if ($usage === null)
        {
            return 0;
        }

        if (is_array($usage))
        {
            $prompt = (int) ($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? 0);
            $completion = (int) ($usage['completion_tokens'] ?? $usage['completionTokens'] ?? 0);
            $total = (int) ($usage['total_tokens'] ?? $usage['totalTokens'] ?? 0);

            return $total > 0 ? $total : ($prompt + $completion);
        }

        $prompt = (int) ($usage->promptTokens ?? $usage->prompt_tokens ?? 0);
        $completion = (int) ($usage->completionTokens ?? $usage->completion_tokens ?? 0);
        $total = (int) ($usage->totalTokens ?? $usage->total_tokens ?? 0);

        return $total > 0 ? $total : ($prompt + $completion);
    }

    public static function resolveModuleId(?int $moduleId, ?string $moduleKey): ?int
    {
        if ($moduleId !== null)
        {
            return $moduleId;
        }

        if ($moduleKey !== null && $moduleKey !== '')
        {
            $resolved = Module::query()->where('key', $moduleKey)->value('id');

            return $resolved !== null ? (int) $resolved : null;
        }

        return TokenUsageLog::inferModuleId();
    }
}
