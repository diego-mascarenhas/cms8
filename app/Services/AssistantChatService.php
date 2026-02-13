<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\TokenUsageLog;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;

use function Laravel\Ai\agent;

class AssistantChatService
{
    /**
     * Run the assistant chat: route the user message through the general router, then run the target prompt.
     * Returns response text and the flow label (routed_to).
     *
     * @return array{response: string, routed_to: string|null}
     */
    public function run(string $userMessage, ?int $teamId = null): array
    {
        $routerPrompt = Prompt::active()->where('section_key', 'general')->first();
        if (! $routerPrompt)
        {
            return [
                'response' => __('No hay prompt general configurado. Configura el enrutador en Prompts.'),
                'routed_to' => null,
            ];
        }

        $prompt = $this->resolveRoute($routerPrompt, $userMessage);
        if ($prompt === null)
        {
            return [
                'response' => __('No se pudo determinar el flujo. Intenta ser más específico.'),
                'routed_to' => null,
            ];
        }

        $userContent = $prompt->prompt_instruction."\n\n---\n\nEntrada del usuario:\n\n".$userMessage;

        try
        {
            $agent = agent(
                instructions: $prompt->prompt_instruction,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($userContent, [], Lab::Anthropic);
            $text = $response->text ?: '';
        } catch (\Throwable $e)
        {
            Log::error('AssistantChat run failed', ['error' => $e->getMessage(), 'prompt_id' => $prompt->id]);

            return [
                'response' => __('Error al comunicar con la IA: ').$e->getMessage(),
                'routed_to' => $prompt->section_label,
            ];
        }

        $usage = $response->usage;
        $totalTokens = $usage->promptTokens + $usage->completionTokens;
        if ($teamId)
        {
            try
            {
                TokenUsageLog::withoutGlobalScopes()->create([
                    'team_id' => $teamId,
                    'module_id' => $prompt->module_id ?? TokenUsageLog::inferModuleId(),
                    'service' => 'AssistantChatService',
                    'json_size' => strlen($userContent),
                    'toon_size' => 0,
                    'json_tokens' => $totalTokens,
                    'toon_tokens' => 0,
                    'savings_percentage' => 0,
                    'used_toon' => false,
                ]);
            } catch (\Exception $e)
            {
                Log::error('Failed to log token usage (AssistantChat)', ['error' => $e->getMessage()]);
            }
        }

        return [
            'response' => $text,
            'routed_to' => $prompt->section_label,
        ];
    }

    /**
     * Resolve the target prompt from the general router and user message.
     */
    public function resolveRoute(Prompt $routerPrompt, string $userContent): ?Prompt
    {
        $routerMessage = $routerPrompt->prompt_instruction."\n\n---\n\nEntrada del usuario:\n\n".$userContent;

        try
        {
            $agent = agent(
                instructions: $routerPrompt->prompt_instruction,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($routerMessage, [], Lab::Anthropic);
            $text = trim($response->text ?: '');
        } catch (\Throwable $e)
        {
            Log::warning('AssistantChat router failed', ['error' => $e->getMessage()]);

            return Prompt::findByRoutingKey('landing');
        }

        $firstLine = trim(explode("\n", $text)[0] ?? '');
        $firstLine = preg_replace('/^[\s`*#\-]+|[\s`*]+$/u', '', $firstLine);
        if (preg_match('/[a-z0-9_]+:[a-z0-9_]+/u', $firstLine, $m))
        {
            $key = $m[0];
        } else
        {
            $key = trim($firstLine);
        }

        $target = Prompt::findByRoutingKey($key);

        return $target ?? Prompt::findByRoutingKey('landing');
    }
}
