<?php

namespace App\Services;

use App\Models\Team;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Laravel\Ai\agent;

class StrategyFieldSuggestionService
{
    private const MAX_NOTES = 8;

    public function __construct(private WeeklyWorkPlanService $plans) {}

    /**
     * @param  array<string, mixed>  $siblings
     */
    public function suggest(Team $team, string $fieldKey, string $draft, array $siblings = []): ?string
    {
        $context = $this->context($team, $fieldKey, $draft, $siblings);

        if ($context === null)
        {
            return null;
        }

        $instructions = <<<'TXT'
Eres el director de estrategia. Devuelve el texto completo del campo, en español, sin título, sin markdown y sin comillas.
Ese texto sustituye lo que ya hay.
Si el borrador tiene texto, consérvalo y modifícalo con las otras notas: añade lo que falte y ajusta lo que no encaje. No borres datos concretos que ya estén.
Si el borrador está vacío, redacta el campo a partir de esas notas.
No inventes clientes, cifras ni canales que no estén en las notas o en el borrador.
Máximo 120 palabras.
TXT;

        $userMessage = "CAMPO A REDACTAR\n\n".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try
        {
            $agent = agent(
                instructions: $instructions,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt(
                $userMessage,
                [],
                AiTasks::provider('assistant'),
                AiTasks::model('assistant'),
                60,
            );
            $text = trim((string) ($response->text ?? ''));
        } catch (Throwable $e)
        {
            Log::error('StrategyFieldSuggestionService::suggest failed', ['error' => $e->getMessage()]);

            return null;
        }

        $text = $this->plain($text);

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $siblings
     * @return array{
     *     field: string,
     *     label: string,
     *     level: int,
     *     title: string,
     *     tip: string,
     *     draft: string,
     *     notes: list<array{level: int, label: string, text: string}>
     * }|null
     */
    public function context(Team $team, string $fieldKey, string $draft, array $siblings = []): ?array
    {
        $target = $this->target($fieldKey);

        if ($target === null)
        {
            return null;
        }

        return [
            'field' => $fieldKey,
            'label' => $target['label'],
            'level' => $target['level'],
            'title' => $target['title'],
            'tip' => $target['tip'],
            'draft' => mb_substr(trim($draft), 0, 5000),
            'notes' => $this->relevantNotes($team, $fieldKey, $target['level'], $siblings),
        ];
    }

    /**
     * @return array{level: int, title: string, tip: string, label: string}|null
     */
    private function target(string $fieldKey): ?array
    {
        foreach (config('strategy.steps', []) as $step)
        {
            foreach ($step['fields'] ?? [] as $field)
            {
                if ((string) ($field['key'] ?? '') !== $fieldKey)
                {
                    continue;
                }

                return [
                    'level' => (int) ($step['number'] ?? 0),
                    'title' => (string) ($step['title'] ?? ''),
                    'tip' => (string) ($step['tip'] ?? ''),
                    'label' => (string) ($field['label'] ?? $fieldKey),
                ];
            }
        }

        return null;
    }

    /**
     * Same-level notes first, then earlier levels from the closest one, then later levels.
     *
     * @param  array<string, mixed>  $siblings
     * @return list<array{level: int, label: string, text: string}>
     */
    private function relevantNotes(Team $team, string $fieldKey, int $targetLevel, array $siblings = []): array
    {
        $values = $this->plans->strategyFieldValues($team);
        $allowed = array_flip($this->plans->allowedStrategyFieldKeys());

        foreach ($siblings as $key => $value)
        {
            if (! is_string($key) || ! isset($allowed[$key]) || $key === $fieldKey)
            {
                continue;
            }

            $values[$key] = is_scalar($value) ? trim((string) $value) : '';
        }
        $same = [];
        $earlier = [];
        $later = [];
        $index = 0;

        foreach (config('strategy.steps', []) as $step)
        {
            $level = (int) ($step['number'] ?? 0);

            foreach ($step['fields'] ?? [] as $field)
            {
                $key = (string) ($field['key'] ?? '');
                $text = trim((string) ($values[$key] ?? ''));

                if ($key === '' || $key === $fieldKey || $text === '')
                {
                    continue;
                }

                $note = [
                    'level' => $level,
                    'label' => (string) ($field['label'] ?? $key),
                    'text' => mb_substr($text, 0, 800),
                    'index' => $index,
                ];
                $index++;

                if ($level === $targetLevel)
                {
                    $same[] = $note;
                } elseif ($level < $targetLevel)
                {
                    $earlier[] = $note;
                } else
                {
                    $later[] = $note;
                }
            }
        }

        usort($earlier, fn (array $left, array $right): int => $right['level'] <=> $left['level'] ?: $left['index'] <=> $right['index']);
        usort($later, fn (array $left, array $right): int => $left['level'] <=> $right['level'] ?: $left['index'] <=> $right['index']);

        $notes = array_slice(array_merge($same, $earlier, $later), 0, self::MAX_NOTES);

        return array_map(fn (array $note): array => [
            'level' => $note['level'],
            'label' => $note['label'],
            'text' => $note['text'],
        ], $notes);
    }

    private function plain(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json|text|markdown)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text);

        if (str_starts_with($text, '{'))
        {
            $decoded = json_decode($text, true);

            if (is_array($decoded))
            {
                $text = trim((string) ($decoded['suggestion'] ?? $decoded['text'] ?? ''));
            }
        }

        return trim($text, " \n\r\t\"'");
    }
}
