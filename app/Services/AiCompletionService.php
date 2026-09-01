<?php

namespace App\Services;

use App\Models\Team;
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
    ];

    /**
     * @return array{text: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}, module: string, service: string}
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

        try
        {
            $agent = agent(
                instructions: 'You are a helpful assistant. Follow the user prompt carefully and answer concisely.',
                messages: [],
                tools: [],
            );
            $response = $agent->prompt(
                $prompt,
                [],
                AiTasks::provider('assistant'),
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

        if ($totalTokens > 0)
        {
            TokenUsageLogService::logFromAiResponse(
                teamId: (int) $team->id,
                service: $serviceName,
                usage: $usage,
                moduleKey: $moduleKey,
                inputSize: strlen($prompt),
                outputSize: strlen($text),
            );
        } else
        {
            $estimated = (int) max(1, round(strlen($prompt) / 4) + round(strlen($text) / 4));
            TokenUsageLogService::log(
                teamId: (int) $team->id,
                service: $serviceName,
                totalTokens: $estimated,
                moduleKey: $moduleKey,
                inputSize: strlen($prompt),
                outputSize: strlen($text),
            );
            $totalTokens = $estimated;
        }

        $promptTokens = is_object($usage)
            ? (int) ($usage->promptTokens ?? $usage->prompt_tokens ?? 0)
            : (int) (is_array($usage) ? ($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? 0) : 0);
        $completionTokens = is_object($usage)
            ? (int) ($usage->completionTokens ?? $usage->completion_tokens ?? 0)
            : (int) (is_array($usage) ? ($usage['completion_tokens'] ?? $usage['completionTokens'] ?? 0) : 0);

        if ($promptTokens + $completionTokens <= 0)
        {
            $promptTokens = (int) max(0, round(strlen($prompt) / 4));
            $completionTokens = max(0, $totalTokens - $promptTokens);
        }

        return [
            'text' => $text,
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
            ],
            'module' => $moduleKey,
            'service' => $serviceName,
        ];
    }
}
