<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\TokenUsageLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Audio;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Transcription;

use function Laravel\Ai\agent;

class AssistantChatService
{
    /**
     * Run the assistant chat: route the user message through the general router, then run the target prompt.
     * If $promptKey is provided, skip the router and use that prompt directly.
     * Optionally accept image and audio uploads, and return TTS audio when requested.
     *
     * @return array{response: string, routed_to: string|null, audio_base64?: string, audio_mime?: string}
     */
    public function run(string $userMessage, ?int $teamId = null, ?UploadedFile $image = null, ?UploadedFile $audio = null, bool $respondWithVoice = false, ?string $promptKey = null): array
    {
        $content = trim($userMessage);
        if ($content === '' && ($image || $audio))
        {
            $content = $image && $audio
                ? __('El usuario ha enviado una imagen y un audio.')
                : ($image ? __('El usuario ha enviado una imagen.') : __('El usuario ha enviado un audio.'));
        }
        if ($audio)
        {
            try
            {
                $transcript = (string) Transcription::fromUpload($audio)->generate(provider: Lab::OpenAI);
                $content = trim($content."\n\n[Transcripción del audio]:\n".$transcript);
            } catch (\Throwable $e)
            {
                Log::warning('AssistantChat transcription failed', ['error' => $e->getMessage()]);

                return [
                    'response' => __('No se pudo transcribir el audio. Comprueba que OPENAI_API_KEY esté configurada.'),
                    'routed_to' => null,
                    'usage' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                ];
            }
        }

        // When a promptKey is specified, skip the router and use that prompt directly.
        if ($promptKey !== null)
        {
            $prompt = Prompt::findByRoutingKey($promptKey, $teamId);
            if (! $prompt)
            {
                return [
                    'response' => __('No se encontró el prompt con la clave: ').$promptKey,
                    'routed_to' => null,
                    'usage' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                ];
            }
        } else
        {
            $routerBase = $teamId !== null ? Prompt::forTeam($teamId) : Prompt::query();
            $routerPrompt = $routerBase->active()->where('section_key', 'general')->first();
            if (! $routerPrompt)
            {
                return [
                    'response' => __('No hay prompt general configurado. Configura el enrutador en Prompts.'),
                    'routed_to' => null,
                    'usage' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                ];
            }

            $prompt = $this->resolveRoute($routerPrompt, $content, $teamId);
            if ($prompt === null)
            {
                return [
                    'response' => __('No se pudo determinar el flujo. Intenta ser más específico.'),
                    'routed_to' => null,
                    'usage' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                ];
            }
        }

        $instruction = $this->resolveInstruction($prompt, $teamId);
        $userContent = $instruction."\n\n---\n\nEntrada del usuario:\n\n".$content;
        $attachments = $image ? [$image] : [];

        try
        {
            $agent = agent(
                instructions: $instruction,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($userContent, $attachments, Lab::Anthropic);
            $text = $response->text ?: '';
        } catch (\Throwable $e)
        {
            Log::error('AssistantChat run failed', ['error' => $e->getMessage(), 'prompt_id' => $prompt->id]);

            return [
                'response' => __('Error al comunicar con la IA: ').$e->getMessage(),
                'routed_to' => $prompt->section_label,
                'usage' => [],
                'tool_calls' => [],
                'tool_results' => [],
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

        $usageArray = [];
        if (isset($response->usage))
        {
            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;
            $usageArray = [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
            ];
        }

        $toolCalls = isset($response->toolCalls) && is_array($response->toolCalls) ? $response->toolCalls : [];
        $toolResults = isset($response->toolResults) && is_array($response->toolResults) ? $response->toolResults : [];

        $result = [
            'response' => $text,
            'routed_to' => $prompt->section_label,
            'usage' => $usageArray,
            'tool_calls' => $toolCalls,
            'tool_results' => $toolResults,
        ];

        if ($respondWithVoice && $text !== '' && config('ai.providers.eleven.key'))
        {
            $maxCharsForTts = 1000;
            $textForTts = strlen($text) > $maxCharsForTts ? substr($text, 0, $maxCharsForTts).'…' : $text;
            try
            {
                $audioResponse = Audio::of($textForTts)->generate(provider: Lab::ElevenLabs);
                $result['audio_base64'] = $audioResponse->audio;
                $result['audio_mime'] = $audioResponse->mimeType() ?? 'audio/mpeg';
            } catch (\Throwable $e)
            {
                Log::warning('AssistantChat TTS failed', ['error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /**
     * Resolve the full instruction for a prompt, replacing any dynamic placeholders.
     * Supports: {{WORDPRESS_CONTEXT}} — injects live WordPress content from the team's site.
     */
    private function resolveInstruction(Prompt $prompt, ?int $teamId): string
    {
        return $prompt->resolvedInstruction($teamId);
    }

    /**
     * Resolve the router instruction, replacing {{ROUTING_KEYS}} with the dynamic list from the DB.
     */
    private function resolveRouterInstruction(Prompt $routerPrompt, ?int $teamId = null): string
    {
        $instruction = $routerPrompt->prompt_instruction;
        if (str_contains($instruction, '{{ROUTING_KEYS}}'))
        {
            $instruction = str_replace('{{ROUTING_KEYS}}', Prompt::buildRoutableKeysList($teamId), $instruction);
        }

        return $instruction;
    }

    /**
     * Resolve the target prompt from the general router and user message.
     */
    public function resolveRoute(Prompt $routerPrompt, string $userContent, ?int $teamId = null): ?Prompt
    {
        $instruction = $this->resolveRouterInstruction($routerPrompt, $teamId);
        $routerMessage = $instruction."\n\n---\n\nEntrada del usuario:\n\n".$userContent;

        try
        {
            $agent = agent(
                instructions: $instruction,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($routerMessage, [], Lab::Anthropic);
            $text = trim($response->text ?: '');
        } catch (\Throwable $e)
        {
            Log::warning('AssistantChat router failed', ['error' => $e->getMessage()]);

            return Prompt::findByRoutingKey('landing', $teamId);
        }

        $firstLine = trim(explode("\n", $text)[0] ?? '');
        $firstLine = preg_replace('/^[\s`*#\-]+|[\s`*]+$/u', '', $firstLine);
        $key = trim($firstLine);

        $target = Prompt::findByRoutingKey($key, $teamId);

        return $target ?? Prompt::findByRoutingKey('landing', $teamId);
    }
}
