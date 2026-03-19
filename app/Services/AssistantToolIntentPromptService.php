<?php

namespace App\Services;

use App\Models\Prompt;

/**
 * Picks an optional {@see Prompt} from module_prompts to merge into the tool-assistant system
 * instructions, based on lightweight intent detection (no extra LLM call).
 */
class AssistantToolIntentPromptService
{
    public function findPromptForMessage(int $teamId, string $message): ?Prompt
    {
        $pair = $this->findPromptAndRoutingKeyForMessage($teamId, $message);

        return $pair['prompt'] ?? null;
    }

    /**
     * @return array{prompt: Prompt, routing_key: string}|null
     */
    public function findPromptAndRoutingKeyForMessage(int $teamId, string $message): ?array
    {
        if (! config('assistant_tool_intent_prompts.enabled', true))
        {
            return null;
        }

        $intentId = $this->detectBestIntentId($message);
        if ($intentId === null)
        {
            return null;
        }

        $intentCfg = config('assistant_tool_intent_prompts.intents.'.$intentId);
        if (! is_array($intentCfg))
        {
            return null;
        }

        $keys = $intentCfg['routing_keys'] ?? [];
        if (! is_array($keys))
        {
            return null;
        }

        foreach ($keys as $routingKey)
        {
            $key = trim((string) $routingKey);
            if ($key === '')
            {
                continue;
            }

            $prompt = Prompt::findByRoutingKey($key, $teamId);
            if ($prompt && $prompt->is_active && ! $prompt->isGeneralRouter())
            {
                return [
                    'prompt' => $prompt,
                    'routing_key' => $key,
                ];
            }
        }

        return null;
    }

    public function matchesFlowReset(string $message): bool
    {
        $normalized = $this->normalizeMessage($message);
        if ($normalized === '')
        {
            return false;
        }

        $phrases = config('assistant_tool_intent_prompts.flow_reset_phrases', []);
        if (! is_array($phrases))
        {
            return false;
        }

        foreach ($phrases as $phrase)
        {
            $p = mb_strtolower(trim((string) $phrase));
            if ($p !== '' && str_contains($normalized, $p))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve tool-assistant flow: sticky routing key keeps the same {@see Prompt} until reset
     * or until the key no longer resolves to an active non-general prompt.
     *
     * @return array{
     *     prompt: ?Prompt,
     *     routing_key: ?string,
     *     persist_assistant_flow_key: 'omit'|'set'|'clear',
     * }
     */
    public function resolveFlowForToolAssistant(int $teamId, string $message, ?string $stickyRoutingKey): array
    {
        if (! config('assistant_tool_intent_prompts.enabled', true))
        {
            return [
                'prompt' => null,
                'routing_key' => null,
                'persist_assistant_flow_key' => 'omit',
            ];
        }

        if ($this->matchesFlowReset($message))
        {
            $found = $this->findPromptAndRoutingKeyForMessage($teamId, $message);

            return [
                'prompt' => $found['prompt'] ?? null,
                'routing_key' => $found['routing_key'] ?? null,
                'persist_assistant_flow_key' => $found !== null ? 'set' : 'clear',
            ];
        }

        $stickyRoutingKey = $stickyRoutingKey !== null ? trim($stickyRoutingKey) : null;
        if ($stickyRoutingKey !== null && $stickyRoutingKey !== '')
        {
            $stickyPrompt = Prompt::findByRoutingKey($stickyRoutingKey, $teamId);
            if ($stickyPrompt && $stickyPrompt->is_active && ! $stickyPrompt->isGeneralRouter())
            {
                return [
                    'prompt' => $stickyPrompt,
                    'routing_key' => $stickyRoutingKey,
                    'persist_assistant_flow_key' => 'omit',
                ];
            }

            $found = $this->findPromptAndRoutingKeyForMessage($teamId, $message);
            if ($found !== null)
            {
                return [
                    'prompt' => $found['prompt'],
                    'routing_key' => $found['routing_key'],
                    'persist_assistant_flow_key' => 'set',
                ];
            }

            return [
                'prompt' => null,
                'routing_key' => null,
                'persist_assistant_flow_key' => 'clear',
            ];
        }

        $found = $this->findPromptAndRoutingKeyForMessage($teamId, $message);
        if ($found !== null)
        {
            return [
                'prompt' => $found['prompt'],
                'routing_key' => $found['routing_key'],
                'persist_assistant_flow_key' => 'set',
            ];
        }

        return [
            'prompt' => null,
            'routing_key' => null,
            'persist_assistant_flow_key' => 'omit',
        ];
    }

    public function detectBestIntentId(string $message): ?string
    {
        $normalized = $this->normalizeMessage($message);
        if ($normalized === '')
        {
            return null;
        }

        $order = config('assistant_tool_intent_prompts.intents_order', []);
        $intents = config('assistant_tool_intent_prompts.intents', []);
        $minScore = (int) config('assistant_tool_intent_prompts.minimum_score', 1);

        $bestId = null;
        $bestScore = -1;

        foreach ($order as $intentId)
        {
            if (! is_string($intentId) || ! isset($intents[$intentId]) || ! is_array($intents[$intentId]))
            {
                continue;
            }

            $score = $this->scoreIntent($normalized, $intents[$intentId]);
            if ($score > $bestScore)
            {
                $bestScore = $score;
                $bestId = $intentId;
            }
        }

        if ($bestId === null || $bestScore < $minScore)
        {
            return null;
        }

        return $bestId;
    }

    /**
     * @param  array{phrases?: list<string>, words?: list<string>}  $cfg
     */
    protected function scoreIntent(string $normalized, array $cfg): int
    {
        $score = 0;

        foreach ($cfg['phrases'] ?? [] as $phrase)
        {
            $p = mb_strtolower(trim((string) $phrase));
            if ($p !== '' && str_contains($normalized, $p))
            {
                $score += 2;
            }
        }

        foreach ($cfg['words'] ?? [] as $word)
        {
            $w = mb_strtolower(trim((string) $word));
            if ($w === '')
            {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($w, '/').'(?![\p{L}\p{N}_])/u';
            if (preg_match($pattern, $normalized) === 1)
            {
                $score += 1;
            }
        }

        return $score;
    }

    protected function normalizeMessage(string $message): string
    {
        $t = mb_strtolower(trim($message));
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t, " \t\n\r\0\x0B!?.¡¿");
    }
}
