<?php

namespace App\Services;

use App\Models\Team;
use App\Services\Billing\ClientTokenPresenter;
use App\Services\Billing\McpClientDiscount;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;
use RuntimeException;

use function Laravel\Ai\agent;

class AiCompletionService
{
    /**
     * Modules accepted by the generic AI completion API.
     *
     * @var list<string>
     */
    public const ALLOWED_MODULES = [
        'proposals',
        'challenges',
        'innovation',
        'website',
    ];

    /**
     * The client sends the prompt uncompressed. Compression happens here, and the
     * response tells the client whether TOON was applied.
     *
     * @return array{text: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}, token_multiplier: float, client_presented: bool, module: string, service: string, toon: array{used_toon: bool, json_size: int, toon_size: int, json_tokens: int, toon_tokens: int, tokens_saved: int, savings_percentage: int}}
     */
    public function complete(
        Team $team,
        string $prompt,
        string $moduleKey,
        ?string $service = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): array {
        $moduleKey = strtolower(trim($moduleKey));
        if (! in_array($moduleKey, self::ALLOWED_MODULES, true))
        {
            throw new RuntimeException(__('El módulo de IA no es válido.'));
        }

        $serviceName = trim((string) $service);
        if ($serviceName === '')
        {
            $serviceName = 'AiCompletionService';
        }

        // max_tokens / temperature are accepted for API compatibility; laravel/ai
        // providers use their configured defaults for anonymous agent prompts.
        unset($maxTokens, $temperature);

        $compressed = PromptToonCompressor::compress($prompt);
        $promptForModel = $compressed['prompt'];
        $toon = $compressed['toon'];

        try
        {
            $agent = agent(
                instructions: 'You are a helpful assistant. Follow the user prompt carefully and answer concisely.',
                messages: [],
                tools: [],
            );
            $response = $agent->prompt(
                $promptForModel,
                [],
                AiTasks::provider('assistant'),
                AiTasks::model('assistant'),
            );
        } catch (\Throwable $e)
        {
            Log::error('AiCompletionService failed', [
                'team_id' => $team->id,
                'module' => $moduleKey,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(__('Error al comunicar con la IA: ').$e->getMessage(), 0, $e);
        }

        $text = trim((string) ($response->text ?? ''));
        $usage = $response->usage ?? null;
        $totalTokens = TokenUsageLogService::totalTokensFromUsage($usage);
        $promptTokens = is_object($usage)
            ? (int) ($usage->promptTokens ?? $usage->prompt_tokens ?? 0)
            : (int) (is_array($usage) ? ($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? 0) : 0);
        $completionTokens = is_object($usage)
            ? (int) ($usage->completionTokens ?? $usage->completion_tokens ?? 0)
            : (int) (is_array($usage) ? ($usage['completion_tokens'] ?? $usage['completionTokens'] ?? 0) : 0);

        if ($promptTokens + $completionTokens <= 0)
        {
            if ($totalTokens <= 0)
            {
                $totalTokens = (int) max(1, round(strlen($prompt) / 4) + round(strlen($text) / 4));
            }
            $promptTokens = (int) max(0, round(strlen($prompt) / 4));
            $completionTokens = max(0, $totalTokens - $promptTokens);
        }

        $priced = $this->pricedUsage($promptTokens, $completionTokens, $toon, true);

        TokenUsageLogService::logFromAiResponse(
            teamId: (int) $team->id,
            service: $serviceName,
            usage: [
                'prompt_tokens' => $priced['prompt_tokens'],
                'completion_tokens' => $priced['completion_tokens'],
                'total_tokens' => $priced['total_tokens'],
            ],
            moduleKey: $moduleKey,
            inputSize: strlen($prompt),
            outputSize: strlen($text),
            toon: $toon,
            model: AiTasks::model('assistant'),
        );

        return [
            'text' => $text,
            ...$this->billedPayload($team, $priced['prompt_tokens'], $priced['completion_tokens'], $priced['total_tokens']),
            'module' => $moduleKey,
            'service' => $serviceName,
            'toon' => $toon,
        ];
    }

    /**
     * Record tokens already spent outside this API, such as an Anthropic fallback.
     * The model stays with the caller. This ledger keeps the same columns as before.
     *
     * @param  array{used_toon?: bool, json_size?: int, toon_size?: int, json_tokens?: int, toon_tokens?: int, tokens_saved?: int, savings_percentage?: float|int}|null  $toon
     * @return array{usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}, token_multiplier: float, client_presented: bool, module: string, service: string}|null
     */
    public function recordUsage(
        Team $team,
        string $moduleKey,
        ?string $service,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        int $inputSize = 0,
        int $outputSize = 0,
        ?array $toon = null,
        ?string $model = null,
    ): ?array {
        $moduleKey = strtolower(trim($moduleKey));
        if (! in_array($moduleKey, self::ALLOWED_MODULES, true))
        {
            throw new RuntimeException(__('El módulo de IA no es válido.'));
        }

        $promptTokens = max(0, $promptTokens);
        $completionTokens = max(0, $completionTokens);
        $totalTokens = $totalTokens > 0 ? $totalTokens : ($promptTokens + $completionTokens);
        if ($totalTokens <= 0)
        {
            return null;
        }

        $serviceName = trim((string) $service);
        if ($serviceName === '')
        {
            $serviceName = 'AiCompletionService';
        }

        if ($promptTokens + $completionTokens <= 0)
        {
            $promptTokens = $totalTokens;
            $completionTokens = 0;
        }

        $priced = $this->pricedUsage($promptTokens, $completionTokens, $toon);

        TokenUsageLogService::logFromAiResponse(
            teamId: (int) $team->id,
            service: $serviceName,
            usage: [
                'prompt_tokens' => $priced['prompt_tokens'],
                'completion_tokens' => $priced['completion_tokens'],
                'total_tokens' => $priced['total_tokens'],
            ],
            moduleKey: $moduleKey,
            inputSize: max(0, $inputSize),
            outputSize: max(0, $outputSize),
            toon: $toon,
            model: $model,
        );

        return [
            ...$this->billedPayload($team, $priced['prompt_tokens'], $priced['completion_tokens'], $priced['total_tokens']),
            'module' => $moduleKey,
            'service' => $serviceName,
            'toon' => $toon,
        ];
    }

    /**
     * MCP completions always take a random discount in the published TOON range,
     * whether or not the prompt shrank. Fallback reports stay full price unless
     * the caller already marked them as MCP.
     *
     * @param  array{used_toon?: bool, json_size?: int, toon_size?: int, json_tokens?: int, toon_tokens?: int, tokens_saved?: int, savings_percentage?: float|int}|null  $toon
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, tokens_saved: int, savings_percentage: int}
     */
    private function pricedUsage(int $promptTokens, int $completionTokens, ?array &$toon, bool $mcpCall = false): array
    {
        $applyDiscount = $mcpCall || (is_array($toon) && (bool) ($toon['used_toon'] ?? false));
        $priced = McpClientDiscount::apply($promptTokens, $completionTokens, $applyDiscount);

        if (! $applyDiscount)
        {
            return $priced;
        }

        if (! is_array($toon))
        {
            $toon = [
                'json_size' => 0,
                'toon_size' => 0,
                'json_tokens' => 0,
                'toon_tokens' => 0,
            ];
        }

        $toon['used_toon'] = true;
        $toon['tokens_saved'] = $priced['tokens_saved'];
        $toon['savings_percentage'] = $priced['savings_percentage'];

        return $priced;
    }

    /**
     * The ledger keeps provider tokens. Callers that show or store this payload
     * receive the same multiplied counts the invoice will charge.
     *
     * @return array{usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}, token_multiplier: float, client_presented: bool}
     */
    private function billedPayload(Team $team, int $promptTokens, int $completionTokens, int $totalTokens): array
    {
        $presenter = app(ClientTokenPresenter::class)->usingTeam($team);
        $total = $presenter->scale($totalTokens);
        $prompt = $promptTokens > 0 ? $presenter->scale($promptTokens) : $total;
        $completion = $completionTokens > 0 ? $presenter->scale($completionTokens) : 0;

        if ($prompt + $completion !== $total)
        {
            $prompt = max(0, $total - $completion);
        }

        return [
            'usage' => [
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'total_tokens' => $total,
            ],
            'token_multiplier' => $presenter->multiplier(),
            'client_presented' => true,
        ];
    }
}
