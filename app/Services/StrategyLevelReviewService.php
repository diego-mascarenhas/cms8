<?php

namespace App\Services;

use App\Models\Team;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Laravel\Ai\agent;

class StrategyLevelReviewService
{
    /**
     * @return array{
     *     level: int,
     *     summary: string,
     *     approved: array<string, string>,
     *     items: list<array{level: int, title: string, key: string, label: string, status: string, note: string}>,
     *     generated_at: string
     * }|null
     */
    public function stored(Team $team): ?array
    {
        $config = $this->businessConfig($team);
        $review = $config['strategy_review'] ?? null;

        if (! is_array($review) || ($review['items'] ?? []) === [])
        {
            return null;
        }

        return $this->normalizeStored($review);
    }

    /**
     * @return array<string, string>
     */
    public function approvedTexts(Team $team): array
    {
        return $this->stored($team)['approved'] ?? [];
    }

    /**
     * @return array{
     *     level: int,
     *     summary: string,
     *     approved: array<string, string>,
     *     items: list<array{level: int, title: string, key: string, label: string, status: string, note: string}>,
     *     generated_at: string
     * }|null
     */
    public function evaluate(Team $team): ?array
    {
        $text = $this->suggest($team);
        $failed = __('app.strategy_review_failed');

        if ($text === $failed)
        {
            return null;
        }

        $parsed = $this->parse($text);

        if ($parsed === null)
        {
            return null;
        }

        return $this->store($team, $parsed['validated'], $parsed['notes'], $parsed['summary']);
    }

    public function suggest(Team $team): string
    {
        $instructions = <<<'TXT'
Eres el director de estrategia. Responde solo con JSON válido, en español, sin markdown.
Lee las notas del equipo y decide cuáles están listas para dar por cerrado ese punto.
Una nota está validada solo si es concreta: nombra a quién, qué ofrece, un canal, una cifra o un paso real. Rechaza vacíos, placeholders y frases genéricas.
No inventes contenido que no esté escrito.
El JSON tiene esta forma:
{"validated":["ideal_client"],"notes":{"ideal_client":"","web":"Falta la URL y a quién convierte."},"summary":""}
validated es una lista de keys con texto suficiente. notes es un objeto de textos: una frase de lo que falta, vacía si la nota ya vale. summary es un párrafo de lo que ya está y lo que falta.
TXT;

        $userMessage = "NOTAS DE ESTRATEGIA\n\n".json_encode($this->context($team), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

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
            Log::error('StrategyLevelReviewService::suggest failed', ['error' => $e->getMessage()]);

            return __('app.strategy_review_failed');
        }

        if ($text === '')
        {
            return __('app.strategy_review_failed');
        }

        return $text;
    }

    /**
     * @param  list<string>  $validated
     * @param  array<string, string>  $notes
     * @return array{
     *     level: int,
     *     summary: string,
     *     approved: array<string, string>,
     *     items: list<array{level: int, title: string, key: string, label: string, status: string, note: string}>,
     *     generated_at: string
     * }
     */
    private function store(Team $team, array $validated, array $notes, string $summary): array
    {
        $values = $this->fieldValues($team);
        $validated = array_flip($validated);
        $approved = [];
        $items = [];
        $level = 1;
        $openLevel = null;

        foreach (config('strategy.steps', []) as $step)
        {
            $number = (int) ($step['number'] ?? 0);
            $title = (string) ($step['title'] ?? '');
            $stepComplete = true;

            foreach ($step['fields'] ?? [] as $field)
            {
                $key = (string) ($field['key'] ?? '');
                $label = (string) ($field['label'] ?? $key);

                if ($key === '')
                {
                    continue;
                }

                $value = $values[$key] ?? '';
                $note = $notes[$key] ?? '';

                if ($value !== '' && isset($validated[$key]))
                {
                    $status = 'validated';
                    $approved[$key] = $value;
                    $note = '';
                } elseif ($value === '')
                {
                    $status = 'missing';
                    $stepComplete = false;
                    if ($note === '')
                    {
                        $note = (string) __('app.weekly_plan_strategy_field_empty', ['field' => $label]);
                    }
                } else
                {
                    $status = 'weak';
                    $stepComplete = false;
                }

                $items[] = [
                    'level' => $number,
                    'title' => $title,
                    'key' => $key,
                    'label' => $label,
                    'status' => $status,
                    'note' => $note,
                ];
            }

            if ($stepComplete && $openLevel === null)
            {
                $level = $number;
            }

            if (! $stepComplete && $openLevel === null)
            {
                $openLevel = $number;
                $level = $number;
            }
        }

        if ($summary === '')
        {
            $summary = (string) __('app.weekly_plan_strategy_current', ['level' => $level]);
        }

        $review = [
            'level' => $level,
            'summary' => $summary,
            'approved' => $approved,
            'items' => $items,
            'generated_at' => now()->toIso8601String(),
        ];

        $config = $this->businessConfig($team);
        $config['strategy_level'] = $level;
        $config['strategy_review'] = $review;
        $team->setSetting('business_config', $config, [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        return $review;
    }

    /**
     * @return array{validated: list<string>, notes: array<string, string>, summary: string}|null
     */
    private function parse(string $text): ?array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean) ?? $clean;
        $decoded = json_decode($clean, true);

        if (! is_array($decoded) && preg_match('/\{.*\}/s', $clean, $match) === 1)
        {
            $decoded = json_decode($match[0], true);
        }

        if (! is_array($decoded))
        {
            return null;
        }

        $validated = [];
        foreach (is_array($decoded['validated'] ?? null) ? $decoded['validated'] : [] as $key)
        {
            $key = $this->plainText($key);
            if ($key !== '')
            {
                $validated[] = $key;
            }
        }

        $notes = [];
        $rawNotes = is_array($decoded['notes'] ?? null) ? $decoded['notes'] : [];
        foreach ($rawNotes as $key => $note)
        {
            $key = $this->plainText($key);
            if ($key === '')
            {
                continue;
            }
            $notes[$key] = $this->plainText($note);
        }

        return [
            'validated' => array_values(array_unique($validated)),
            'notes' => $notes,
            'summary' => $this->plainText($decoded['summary'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $review
     * @return array{
     *     level: int,
     *     summary: string,
     *     approved: array<string, string>,
     *     items: list<array{level: int, title: string, key: string, label: string, status: string, note: string}>,
     *     generated_at: string
     * }
     */
    private function normalizeStored(array $review): array
    {
        $approved = [];
        foreach (is_array($review['approved'] ?? null) ? $review['approved'] : [] as $key => $value)
        {
            $key = $this->plainText($key);
            $value = $this->plainText($value);
            if ($key !== '' && $value !== '')
            {
                $approved[$key] = $value;
            }
        }

        $items = [];
        foreach (is_array($review['items'] ?? null) ? $review['items'] : [] as $item)
        {
            if (! is_array($item))
            {
                continue;
            }
            $status = (string) ($item['status'] ?? '');
            if (! in_array($status, ['validated', 'missing', 'weak'], true))
            {
                $status = 'missing';
            }
            $items[] = [
                'level' => (int) ($item['level'] ?? 0),
                'title' => $this->plainText($item['title'] ?? ''),
                'key' => $this->plainText($item['key'] ?? ''),
                'label' => $this->plainText($item['label'] ?? ''),
                'status' => $status,
                'note' => $this->plainText($item['note'] ?? ''),
            ];
        }

        return [
            'level' => max(1, min(12, (int) ($review['level'] ?? 1))),
            'summary' => $this->plainText($review['summary'] ?? ''),
            'approved' => $approved,
            'items' => $items,
            'generated_at' => $this->plainText($review['generated_at'] ?? ''),
        ];
    }

    /**
     * @return list<array{number: int, title: string, tip: string, fields: list<array{key: string, label: string, value: string}>}>
     */
    private function context(Team $team): array
    {
        $values = $this->fieldValues($team);
        $steps = [];

        foreach (config('strategy.steps', []) as $step)
        {
            $fields = [];
            foreach ($step['fields'] ?? [] as $field)
            {
                $key = (string) ($field['key'] ?? '');
                if ($key === '')
                {
                    continue;
                }
                $fields[] = [
                    'key' => $key,
                    'label' => (string) ($field['label'] ?? $key),
                    'value' => mb_substr($values[$key] ?? '', 0, 1500),
                ];
            }
            $steps[] = [
                'number' => (int) ($step['number'] ?? 0),
                'title' => (string) ($step['title'] ?? ''),
                'tip' => (string) ($step['tip'] ?? ''),
                'fields' => $fields,
            ];
        }

        return $steps;
    }

    /**
     * @return array<string, string>
     */
    private function fieldValues(Team $team): array
    {
        $config = $this->businessConfig($team);
        $strategy = $config['strategy'] ?? [];
        if (! is_array($strategy))
        {
            return [];
        }

        $values = [];
        foreach ($strategy as $key => $value)
        {
            if (! is_string($key))
            {
                continue;
            }
            $values[$key] = is_scalar($value) ? trim((string) $value) : '';
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function businessConfig(Team $team): array
    {
        $saved = $team->getSetting('business_config', []);
        if (is_string($saved))
        {
            $saved = json_decode($saved, true) ?: [];
        }

        return is_array($saved) ? $saved : [];
    }

    private function plainText(mixed $value): string
    {
        if (is_bool($value) || $value === null)
        {
            return '';
        }

        if (is_string($value) || is_numeric($value))
        {
            return trim((string) $value);
        }

        if (! is_array($value))
        {
            return '';
        }

        $parts = [];
        foreach ($value as $item)
        {
            $text = $this->plainText($item);
            if ($text !== '')
            {
                $parts[] = $text;
            }
        }

        return trim(implode(' ', $parts));
    }
}
