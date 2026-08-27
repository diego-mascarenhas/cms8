<?php

namespace App\Services\Assistant;

use App\Helpers\AssistantCategoryAssignmentNote;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use App\Services\TeamSiteAssistantPromptService;

/**
 * When the flow prompt lists tag rules (keyword → CATEGORY) and the model skips
 * assign_contact_to_category, apply the match server-side so the inbox still
 * gets the internal note.
 */
class AssistantInboundCategoryAssignmentService
{
    /**
     * Words that are too generic to treat as a classification keyword.
     *
     * @var list<string>
     */
    private const STOPWORDS = [
        'a', 'al', 'con', 'compra', 'de', 'del', 'el', 'en', 'envio', 'envío',
        'la', 'las', 'los', 'o', 'or', 'para', 'por', 'reclamo', 'un', 'una', 'y',
    ];

    public function __construct(
        protected AssistantToolsService $assistantTools,
        protected TeamSiteAssistantPromptService $sitePrompts,
    ) {}

    /**
     * @param  array<int, mixed>  $existingToolResults
     * @return array{tool_results: list<string>, category_names: list<string>}|null
     */
    public function tryApplyFromUserMessage(
        ?User $user,
        int $teamId,
        string $message,
        ?string $flowRoutingKey,
        ?int $contactId,
        ?string $customerPhoneDigits = null,
        array $existingToolResults = [],
    ): ?array {
        if ($contactId === null || $contactId < 1)
        {
            return null;
        }

        $user ??= $this->teamOwner($teamId);
        if ($user === null)
        {
            return null;
        }

        if ($this->assistantTools->wasToolExecuted('assign_contact_to_category'))
        {
            return null;
        }

        if (AssistantCategoryAssignmentNote::extractCategoryNamesFromToolResults($existingToolResults) !== [])
        {
            return null;
        }

        $instruction = $this->flowInstruction($teamId, $flowRoutingKey);
        if ($instruction === '')
        {
            return null;
        }

        $matches = $this->matchingRules($instruction, $message);
        if ($matches === [])
        {
            return null;
        }

        $this->assistantTools->setRequestContext(
            $user->id,
            $teamId,
            $customerPhoneDigits,
            $contactId,
        );

        $toolResults = [];
        $categoryNames = [];

        foreach ($matches as $rule)
        {
            $toolResult = $this->assistantTools->execute('assign_contact_to_category', [
                'category_name' => $rule['category'],
                'color' => $rule['color'],
            ]);

            $toolResults[] = $toolResult;
            $categoryNames[] = $rule['category'];
        }

        $categoryNames = array_values(array_unique($categoryNames));

        return [
            'tool_results' => $toolResults,
            'category_names' => $categoryNames,
        ];
    }

    /**
     * @return list<array{category: string, color: string|null, keywords: list<string>}>
     */
    public function parseAssignmentRules(string $prompt): array
    {
        $rules = [];
        $lines = preg_split('/\R/u', $prompt) ?: [];

        foreach ($lines as $line)
        {
            if (! preg_match('/^(.+?)\s*(?:→|->)\s*(.+)$/u', trim($line), $parts))
            {
                continue;
            }

            $categoryPart = trim($parts[2]);
            $categoryPart = preg_replace('/^\d+[\.\)]\s*/u', '', $categoryPart) ?? $categoryPart;
            if (! preg_match('/^([A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9][\wÁÉÍÓÚÜÑáéíóúüñ-]*)/u', $categoryPart, $categoryMatch))
            {
                continue;
            }

            $category = trim($categoryMatch[1]);
            if ($category === '')
            {
                continue;
            }

            $color = null;
            if (preg_match('/color\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)/iu', $categoryPart, $colorMatch))
            {
                $color = mb_strtolower(trim($colorMatch[1]));
            }

            $keywords = $this->keywordsFromHaystack($parts[1]);
            if ($keywords === [])
            {
                continue;
            }

            $rules[] = [
                'category' => $category,
                'color' => $color,
                'keywords' => $keywords,
            ];
        }

        return $rules;
    }

    /**
     * @return list<array{category: string, color: string|null, keywords: list<string>}>
     */
    public function matchingRules(string $prompt, string $message): array
    {
        $haystack = $this->normalize($message);
        if ($haystack === '')
        {
            return [];
        }

        $matched = [];
        foreach ($this->parseAssignmentRules($prompt) as $rule)
        {
            foreach ($rule['keywords'] as $keyword)
            {
                if ($this->messageContainsKeyword($haystack, $keyword))
                {
                    $matched[] = $rule;
                    break;
                }
            }
        }

        return $matched;
    }

    private function teamOwner(int $teamId): ?User
    {
        $team = Team::query()->find($teamId);
        if ($team === null || $team->user_id === null)
        {
            return null;
        }

        return User::withoutGlobalScopes()->find($team->user_id);
    }

    private function flowInstruction(int $teamId, ?string $flowRoutingKey): string
    {
        $key = trim((string) $flowRoutingKey);
        if ($key === '')
        {
            $team = Team::query()->find($teamId);
            $key = $team !== null ? (string) ($this->sitePrompts->resolvedRoutingKey($team) ?? '') : '';
        }

        if ($key === '')
        {
            return '';
        }

        $prompt = Prompt::findByRoutingKey($key, $teamId);
        if ($prompt === null || ! $prompt->is_active)
        {
            return '';
        }

        return trim($prompt->resolvedInstruction($teamId));
    }

    /**
     * @return list<string>
     */
    private function keywordsFromHaystack(string $raw): array
    {
        $left = trim($raw);
        $left = preg_replace('/^\d+[\.\)]\s*/u', '', $left) ?? $left;
        $left = preg_replace('/\b(assign_contact_to_category|etiquetas?)\b/iu', '', $left) ?? $left;

        $chunks = preg_split('/\s*(?:,|\/|;|\bo\b|\bor\b)\s*/iu', $left) ?: [];
        $keywords = [];

        foreach ($chunks as $chunk)
        {
            $chunk = trim((string) $chunk, " \t.-");
            if ($chunk === '')
            {
                continue;
            }

            if (preg_match('/mercado\s+libre/iu', $chunk))
            {
                $keywords[] = 'mercado libre';
            }

            $normalized = $this->normalize($chunk);
            if ($normalized === '' || $this->isStopword($normalized))
            {
                continue;
            }

            $words = preg_split('/\s+/u', $normalized) ?: [];
            $kept = [];
            foreach ($words as $word)
            {
                if ($this->isStopword($word))
                {
                    continue;
                }
                $kept[] = $word;
            }

            if ($kept === [])
            {
                continue;
            }

            $keywords[] = implode(' ', $kept);
        }

        return array_values(array_unique($keywords));
    }

    private function messageContainsKeyword(string $normalizedMessage, string $keyword): bool
    {
        $needle = $this->normalize($keyword);
        if ($needle === '')
        {
            return false;
        }

        if (str_contains($needle, ' '))
        {
            return str_contains($normalizedMessage, $needle);
        }

        return (bool) preg_match('/(?:^|[^\p{L}\p{N}])'.preg_quote($needle, '/').'(?:[^\p{L}\p{N}]|$)/u', $normalizedMessage);
    }

    private function isStopword(string $word): bool
    {
        return in_array($this->normalize($word), self::STOPWORDS, true);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
