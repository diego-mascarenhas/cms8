<?php

namespace App\Services\Billing;

use App\Models\Team;
use App\Services\TokenBillingRateService;
use DateTimeInterface;

/**
 * Tokens the customer is charged, frozen with the multiplier in effect
 * when the call was made.
 */
final class BilledUsage
{
    /**
     * @return array{model: string|null, prompt_tokens: int, completion_tokens: int, tokens_saved: int}
     */
    public static function capture(
        Team|int|null $team,
        int $promptTokens,
        int $completionTokens,
        int $tokensSaved,
        ?string $model = null,
        DateTimeInterface|string|null $on = null,
    ): array {
        $multiplier = TokenBillingRateService::clientTokenMultiplier($team, $on);
        $scale = static fn (int $tokens): int => (int) round(max(0, $tokens) * $multiplier);
        $model = is_string($model) ? trim($model) : '';

        return [
            'model' => $model !== '' ? $model : null,
            'prompt_tokens' => $scale($promptTokens),
            'completion_tokens' => $scale($completionTokens),
            'tokens_saved' => $scale($tokensSaved),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{model: string|null, prompt_tokens: int, completion_tokens: int, tokens_saved: int}|null
     */
    public static function read(?array $stored): ?array
    {
        if (! is_array($stored))
        {
            return null;
        }

        if (! array_key_exists('prompt_tokens', $stored)
            && ! array_key_exists('completion_tokens', $stored)
            && ! array_key_exists('tokens_saved', $stored))
        {
            return null;
        }

        $model = trim((string) ($stored['model'] ?? ''));

        return [
            'model' => $model !== '' ? $model : null,
            'prompt_tokens' => max(0, (int) ($stored['prompt_tokens'] ?? 0)),
            'completion_tokens' => max(0, (int) ($stored['completion_tokens'] ?? 0)),
            'tokens_saved' => max(0, (int) ($stored['tokens_saved'] ?? 0)),
        ];
    }

    /**
     * @param  array{prompt_tokens: int, completion_tokens: int, tokens_saved: int}  $billed
     */
    public static function used(array $billed): int
    {
        return $billed['prompt_tokens'] + $billed['completion_tokens'];
    }
}
