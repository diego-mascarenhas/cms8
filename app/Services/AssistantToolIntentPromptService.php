<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\Team;

/**
 * Picks an optional {@see Prompt} from module_prompts to merge into the tool-assistant system
 * instructions, based on lightweight intent detection (no extra LLM call).
 */
class AssistantToolIntentPromptService
{
    /**
     * Per-team setting (Team settings → Chat / Asistente). When false (default), flows are chosen by the LLM
     * (discovery + commit_assistant_flow), not by automatic keyword scoring.
     */
    public function keywordIntentRoutingEnabled(?int $teamId = null): bool
    {
        if (! config('assistant_tool_intent_prompts.enabled', true))
        {
            return false;
        }

        if ($teamId === null || $teamId <= 0)
        {
            return false;
        }

        $team = Team::query()->find($teamId);
        if ($team === null)
        {
            return false;
        }

        return filter_var($team->getSetting('assistant_keyword_intent_routing', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * @return array{prompt: Prompt, routing_key: string, persist_assistant_flow_key: 'set'}|null
     */
    protected function tryKeywordResolution(int $teamId, string $message): ?array
    {
        if (! $this->keywordIntentRoutingEnabled($teamId))
        {
            return null;
        }

        $found = $this->findPromptAndRoutingKeyForMessage($teamId, $message);
        if ($found === null)
        {
            return null;
        }

        return [
            'prompt' => $found['prompt'],
            'routing_key' => $found['routing_key'],
            'persist_assistant_flow_key' => 'set',
        ];
    }

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

        if (! $this->keywordIntentRoutingEnabled($teamId))
        {
            return null;
        }

        $fromSection = $this->findPromptBySectionKeyKeywords($teamId, $message);
        $fromConfig = $this->tryResolvePromptFromConfigIntent($teamId, $message);

        $sectionScore = (int) ($fromSection['match_score'] ?? 0);
        $configScore = (int) ($fromConfig['intent_score'] ?? 0);

        $picked = null;
        if ($fromSection !== null && $fromConfig !== null)
        {
            $picked = $sectionScore > $configScore ? 'section_key' : 'config_intent';
        } elseif ($fromSection !== null)
        {
            $picked = 'section_key';
        } elseif ($fromConfig !== null)
        {
            $picked = 'config_intent';
        }

        if ($picked === 'section_key')
        {
            return [
                'prompt' => $fromSection['prompt'],
                'routing_key' => $fromSection['routing_key'],
            ];
        }

        if ($picked === 'config_intent')
        {
            return [
                'prompt' => $fromConfig['prompt'],
                'routing_key' => $fromConfig['routing_key'],
            ];
        }

        return null;
    }

    /**
     * @return array{prompt: Prompt, routing_key: string, intent_score: int}|null
     */
    protected function tryResolvePromptFromConfigIntent(int $teamId, string $message): ?array
    {
        $normalized = $this->normalizeMessage($message);
        $bestIntent = $this->resolveBestConfigIntent($normalized);
        if ($bestIntent === null)
        {
            return null;
        }

        $intentId = $bestIntent['id'];
        $intentScore = $bestIntent['score'];

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
                    'intent_score' => $intentScore,
                ];
            }
        }

        return null;
    }

    /**
     * Scores the message against {@see Prompt::$section_key} and, when the label is long enough,
     * against a normalized {@see Prompt::$section_label} so teams can express “intent” phrasing
     * in the label without changing the stable routing key. Still deterministic text match (no LLM).
     *
     * @return array{prompt: Prompt, routing_key: string, match_score: int}|null
     */
    protected function findPromptBySectionKeyKeywords(int $teamId, string $message): ?array
    {
        $normalized = $this->normalizeMessage($message);
        if ($normalized === '')
        {
            return null;
        }

        $minScore = (int) config('assistant_tool_intent_prompts.minimum_score', 1);
        $prompts = Prompt::forTeam($teamId)
            ->active()
            ->with('module')
            ->where('section_key', '!=', 'general')
            ->orderBy('order')
            ->get();

        $best = null;
        $bestScore = -1;

        foreach ($prompts as $prompt)
        {
            $score = $this->scorePromptKeywordAttachment($normalized, $prompt);
            if ($score > $bestScore)
            {
                $bestScore = $score;
                $best = $prompt;
            }
        }

        if ($best === null || $bestScore < $minScore)
        {
            return null;
        }

        $routingKey = $best->module
            ? $best->module->key.':'.$best->section_key
            : $best->section_key;

        return [
            'prompt' => $best,
            'routing_key' => $routingKey,
            'match_score' => $bestScore,
        ];
    }

    /**
     * Max of section_key score and (optional) section_label score — same substring / word rules as keys.
     */
    protected function scorePromptKeywordAttachment(string $normalized, Prompt $prompt): int
    {
        $keyScore = $this->scoreMessageAgainstSectionKey($normalized, (string) $prompt->section_key);
        $labelPhrase = $this->normalizeSectionLabelForIntentScoring((string) $prompt->section_label);
        if ($labelPhrase === '')
        {
            return $keyScore;
        }

        return max($keyScore, $this->scoreMessageAgainstSectionKey($normalized, $labelPhrase));
    }

    /**
     * Strip punctuation from the section label so it can reuse {@see scoreMessageAgainstSectionKey}.
     * Short labels are ignored to limit accidental matches on generic words.
     */
    protected function normalizeSectionLabelForIntentScoring(string $sectionLabel): string
    {
        $t = mb_strtolower(trim($sectionLabel));
        if ($t === '')
        {
            return '';
        }

        $t = preg_replace('/[^\p{L}\p{N}\s\-_]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s+/u', ' ', trim($t)) ?? $t;

        if (mb_strlen($t) < 12)
        {
            return '';
        }

        return $t;
    }

    /**
     * @return array{id: string, score: int}|null
     */
    protected function resolveBestConfigIntent(string $normalized): ?array
    {
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

        return [
            'id' => $bestId,
            'score' => $bestScore,
        ];
    }

    /**
     * Score how well the normalized message matches a routing phrase (underscores → spaces, word tokens).
     * Used for {@see Prompt::$section_key} and for normalized {@see Prompt::$section_label} text.
     */
    protected function scoreMessageAgainstSectionKey(string $normalized, string $sectionKey): int
    {
        $sk = mb_strtolower(trim($sectionKey));
        if ($sk === '' || $sk === 'general')
        {
            return 0;
        }

        $spaced = preg_replace('/[_\-]+/u', ' ', $sk) ?? $sk;
        $spaced = preg_replace('/\s+/u', ' ', trim($spaced)) ?? $spaced;

        if (mb_strlen($spaced) >= 2 && str_contains($normalized, $spaced))
        {
            return 3;
        }

        $score = 0;
        $tokens = preg_split('/[_\s\-]+/u', $sk) ?: [];
        foreach ($tokens as $token)
        {
            $t = mb_strtolower(trim((string) $token));
            if (mb_strlen($t) < 2)
            {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($t, '/').'(?![\p{L}\p{N}_])/u';
            if (preg_match($pattern, $normalized) === 1)
            {
                $score += 1;
            }
        }

        return min($score, 4);
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
            $found = $this->keywordIntentRoutingEnabled($teamId)
                ? $this->findPromptAndRoutingKeyForMessage($teamId, $message)
                : null;

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

            $resolved = $this->tryKeywordResolution($teamId, $message);
            if ($resolved !== null)
            {
                return $resolved;
            }

            return [
                'prompt' => null,
                'routing_key' => null,
                'persist_assistant_flow_key' => 'clear',
            ];
        }

        $resolved = $this->tryKeywordResolution($teamId, $message);
        if ($resolved !== null)
        {
            return $resolved;
        }

        return [
            'prompt' => null,
            'routing_key' => null,
            'persist_assistant_flow_key' => 'omit',
        ];
    }

    public function detectBestIntentId(string $message): ?string
    {
        $resolved = $this->resolveBestConfigIntent($this->normalizeMessage($message));

        return $resolved['id'] ?? null;
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
