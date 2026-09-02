<?php

namespace App\Services\Billing;

use App\Services\OpenRouterModelCatalog;
use App\Services\TokenBillingRateService;
use DateTimeInterface;

class ClientTokenPresenter
{
    public function __construct(
        private readonly OpenRouterModelCatalog $catalog,
    ) {}

    public function multiplier(): float
    {
        return TokenBillingRateService::clientTokenMultiplier();
    }

    public function scale(int $tokens): int
    {
        return (int) round(max(0, $tokens) * $this->multiplier());
    }

    /**
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, amount_cents: int}
     */
    public function present(int $promptTokens, int $completionTokens, ?string $model, DateTimeInterface|string|null $on = null): array
    {
        $prompt = $this->scale($promptTokens);
        $completion = $this->scale($completionTokens);
        $total = $prompt + $completion;

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'amount_cents' => $this->costCents($prompt, $completion, $model, $on),
        ];
    }

    public function billingModel(?string $model): string
    {
        $value = is_string($model) ? trim($model) : '';
        if ($value !== '' && strtolower($value) !== 'cheapest')
        {
            return $value;
        }

        $configured = trim((string) config('ai.assistant_model', 'cheapest'));
        if ($configured !== '' && strtolower($configured) !== 'cheapest')
        {
            return $configured;
        }

        return 'claude-haiku-4.5';
    }

    public function costCents(int $promptTokens, int $completionTokens, ?string $model, DateTimeInterface|string|null $on = null): int
    {
        $match = $this->catalog->find($this->billingModel($model));
        $fx = TokenBillingRateService::usdToDisplay($on);

        $match ??= $this->catalog->find('claude-haiku-4.5');
        $promptRate = is_array($match) ? (float) ($match['prompt_per_million'] ?? 1) : 1.0;
        $completionRate = is_array($match) ? (float) ($match['completion_per_million'] ?? 5) : 5.0;
        $usd = ($promptTokens / 1_000_000) * $promptRate
            + ($completionTokens / 1_000_000) * $completionRate;

        return (int) round($usd * $fx * 100);
    }
}
