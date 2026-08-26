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
    /**
     * @param  array{used_toon?: bool, json_size?: int, toon_size?: int, json_tokens?: int, toon_tokens?: int, savings_percentage?: float|int, tokens_saved?: int}|null  $toon
     */
    public static function logFromAiResponse(
        int $teamId,
        string $service,
        mixed $usage,
        ?int $moduleId = null,
        ?string $moduleKey = null,
        int $inputSize = 0,
        int $outputSize = 0,
        ?array $toon = null,
    ): void {
        $totalTokens = self::totalTokensFromUsage($usage);
        if ($totalTokens <= 0)
        {
            return;
        }

        $usedToon = (bool) ($toon['used_toon'] ?? false);
        $tokensSaved = max(0, (int) ($toon['tokens_saved'] ?? 0));
        $jsonTokens = $usedToon ? $totalTokens + $tokensSaved : $totalTokens;
        $toonTokens = $usedToon ? $totalTokens : 0;
        $savings = $jsonTokens > 0 && $usedToon
            ? (int) round(min(100, ($tokensSaved / $jsonTokens) * 100))
            : 0;

        self::log(
            teamId: $teamId,
            service: $service,
            totalTokens: $totalTokens,
            moduleId: $moduleId,
            moduleKey: $moduleKey,
            inputSize: $usedToon ? (int) ($toon['json_size'] ?? $inputSize) : $inputSize,
            outputSize: $usedToon ? (int) ($toon['toon_size'] ?? $outputSize) : $outputSize,
            usedToon: $usedToon,
            jsonTokens: $jsonTokens,
            toonTokens: $toonTokens,
            savingsPercentage: $savings,
        );
    }

    /**
     * Attach TOON compression savings onto a reply usage payload so WhatsApp
     * metadata and the usage table can show the same marked-up euro amount.
     *
     * @param  array<string, mixed>  $usage
     * @param  array{used_toon?: bool, json_tokens?: int, toon_tokens?: int, tokens_saved?: int}  $toon
     * @return array<string, mixed>
     */
    public static function usageWithToonSavings(array $usage, array $toon): array
    {
        $tokensSaved = max(0, (int) ($toon['tokens_saved'] ?? 0));
        if ($tokensSaved <= 0 && ! ($toon['used_toon'] ?? false))
        {
            return $usage;
        }

        $usage['tokens_saved'] = $tokensSaved;
        $usage['json_tokens'] = (int) ($toon['json_tokens'] ?? 0);
        $usage['toon_tokens'] = (int) ($toon['toon_tokens'] ?? 0);

        return $usage;
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
