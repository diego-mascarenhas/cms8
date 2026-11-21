<?php

namespace App\Services\Billing;

/**
 * Commercial discount applied to every MCP call, for every team.
 * The percent is random between the published TOON savings range. It reduces
 * the tokens the client pays, even when that leaves less than the full multiplier.
 */
final class McpClientDiscount
{
    public const MIN_PERCENT = 25;

    public const MAX_PERCENT = 60;

    private static ?int $frozenPercent = null;

    public static function freeze(?int $percent): void
    {
        self::$frozenPercent = $percent;
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, savings_percentage: int}
     */
    public static function apply(int $promptTokens, int $completionTokens, bool $usedToon): array
    {
        $promptTokens = max(0, $promptTokens);
        $completionTokens = max(0, $completionTokens);
        $total = $promptTokens + $completionTokens;

        if (! $usedToon || $total <= 0)
        {
            return self::priced($promptTokens, $completionTokens, $total, 0, 0);
        }

        $percent = self::$frozenPercent ?? random_int(self::MIN_PERCENT, self::MAX_PERCENT);
        $percent = max(self::MIN_PERCENT, min(self::MAX_PERCENT, $percent));
        $saved = (int) round($total * $percent / 100);
        $saved = min($total, max(1, $saved));
        $charged = $total - $saved;
        $chargedPrompt = (int) round($promptTokens * $charged / $total);
        $chargedCompletion = $charged - $chargedPrompt;

        return self::priced(
            $chargedPrompt,
            $chargedCompletion,
            $charged,
            $saved,
            $percent,
        );
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, savings_percentage: int}
     */
    private static function priced(
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        int $tokensSaved,
        int $savingsPercentage,
    ): array {
        return [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'tokens_saved' => $tokensSaved,
            'savings_percentage' => $savingsPercentage,
        ];
    }
}
