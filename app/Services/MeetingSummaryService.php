<?php

namespace App\Services;

use App\Enums\MeetingTranscriptStatus;
use App\Models\Contact;
use App\Models\MeetingTranscript;
use App\Support\AiTasks;
use RuntimeException;

use function Laravel\Ai\agent;

class MeetingSummaryService
{
    private const INSTRUCTIONS = <<<'PROMPT'
You summarize a meeting transcript for the team.

The transcript lists each turn as speaker and text, in order. Use only what was said. Do not invent people, opinions, topics, deadlines, or tasks.

Write in the language of the transcript.

Respond with ONLY a valid JSON object, no markdown:
{"synthesis":"short synthesis of the whole meeting","viewpoints":[{"speaker":"name as given","view":"their point of view"}],"intention":"what the meeting was trying to achieve","items":["topic still to address"],"tasks":[{"title":"task to do","owner":"speaker name or empty string"}]}

Use an empty string or an empty array when that part was not in the transcript.
PROMPT;

    /**
     * @return array{
     *     synthesis: string,
     *     viewpoints: list<array{speaker: string, view: string}>,
     *     intention: string,
     *     items: list<string>,
     *     tasks: list<array{title: string, owner: string}>
     * }
     */
    public function summarize(MeetingTranscript $meeting): array
    {
        if ($meeting->status !== MeetingTranscriptStatus::Ready)
        {
            throw new RuntimeException('La reunión todavía no tiene una transcripción para resumir.');
        }

        $rows = $this->rows($meeting);
        if ($rows === [])
        {
            throw new RuntimeException('La reunión todavía no tiene una transcripción para resumir.');
        }

        $encoded = ToonPayloadService::encode(['title' => $meeting->title, 'turns' => $rows]);
        $agent = agent(
            instructions: self::INSTRUCTIONS,
            messages: [],
            tools: [],
        );
        $response = AiTasks::prompt($agent, $encoded['text'], 120, 'summary');
        $raw = trim((string) ($response->text ?? ''));
        $summary = $this->parse($raw);

        if ($summary['synthesis'] === '' && $summary['viewpoints'] === [] && $summary['intention'] === '' && $summary['items'] === [] && $summary['tasks'] === [])
        {
            throw new RuntimeException('No se pudo resumir la reunión.');
        }

        $this->logUsage($meeting, $response->usage ?? null, $encoded, $raw);
        $meeting->forceFill(['summary' => $summary])->save();

        return $summary;
    }

    /**
     * @return list<array{speaker: string, text: string}>
     */
    private function rows(MeetingTranscript $meeting): array
    {
        $turns = $meeting->turns ?? [];
        $contactIds = collect($turns)->pluck('contact_id')->filter()->unique()->values();
        $contacts = Contact::withoutGlobalScope('team')
            ->where('team_id', $meeting->team_id)
            ->whereIn('id', $contactIds)
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($turns as $turn)
        {
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text === '')
            {
                continue;
            }

            $contact = isset($turn['contact_id']) ? $contacts->get($turn['contact_id']) : null;
            $speaker = $contact
                ? trim($contact->name.' '.($contact->surname ?? ''))
                : trim((string) ($turn['label'] ?? 'Persona'));

            $rows[] = [
                'speaker' => $speaker !== '' ? $speaker : 'Persona',
                'text' => $text,
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     synthesis: string,
     *     viewpoints: list<array{speaker: string, view: string}>,
     *     intention: string,
     *     items: list<string>,
     *     tasks: list<array{title: string, owner: string}>
     * }
     */
    private function parse(string $raw): array
    {
        $raw = preg_replace('/^```\w*\s*|\s*```$/u', '', $raw) ?? $raw;
        $data = json_decode(trim($raw), true);
        if (! is_array($data))
        {
            throw new RuntimeException('No se pudo resumir la reunión.');
        }

        return [
            'synthesis' => $this->line($data['synthesis'] ?? ''),
            'viewpoints' => $this->viewpoints($data['viewpoints'] ?? []),
            'intention' => $this->line($data['intention'] ?? ''),
            'items' => $this->lines($data['items'] ?? []),
            'tasks' => $this->tasks($data['tasks'] ?? []),
        ];
    }

    /**
     * @return list<array{speaker: string, view: string}>
     */
    private function viewpoints(mixed $value): array
    {
        if (! is_array($value))
        {
            return [];
        }

        $rows = [];
        foreach ($value as $item)
        {
            if (is_string($item))
            {
                $view = $this->line($item);
                if ($view !== '')
                {
                    $rows[] = ['speaker' => '', 'view' => $view];
                }

                continue;
            }

            if (! is_array($item))
            {
                continue;
            }

            $view = $this->line($item['view'] ?? '');
            if ($view === '')
            {
                continue;
            }

            $rows[] = [
                'speaker' => $this->line($item['speaker'] ?? ''),
                'view' => $view,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function lines(mixed $value): array
    {
        if (! is_array($value))
        {
            return [];
        }

        $lines = [];
        foreach ($value as $item)
        {
            $line = $this->line($item);
            if ($line !== '')
            {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return list<array{title: string, owner: string}>
     */
    private function tasks(mixed $value): array
    {
        if (! is_array($value))
        {
            return [];
        }

        $tasks = [];
        foreach ($value as $item)
        {
            if (is_string($item))
            {
                $title = $this->line($item);
                if ($title !== '')
                {
                    $tasks[] = ['title' => $title, 'owner' => ''];
                }

                continue;
            }

            if (! is_array($item))
            {
                continue;
            }

            $title = $this->line($item['title'] ?? '');
            if ($title === '')
            {
                continue;
            }

            $tasks[] = [
                'title' => $title,
                'owner' => $this->line($item['owner'] ?? ''),
            ];
        }

        return $tasks;
    }

    private function line(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param  array{used_toon?: bool, json_size?: int, toon_size?: int, json_tokens?: int, toon_tokens?: int, savings_percentage?: float|int, tokens_saved?: int}  $encoded
     */
    private function logUsage(MeetingTranscript $meeting, mixed $usage, array $encoded, string $raw): void
    {
        $total = TokenUsageLogService::totalTokensFromUsage($usage);
        if ($total > 0)
        {
            TokenUsageLogService::logFromAiResponse(
                teamId: (int) $meeting->team_id,
                service: 'MeetingSummaryService',
                usage: $usage,
                inputSize: strlen($encoded['text']),
                outputSize: strlen($raw),
                toon: $encoded,
            );

            return;
        }

        $estimated = (int) max(1, round(strlen($encoded['text']) / 4) + round(strlen($raw) / 4));
        TokenUsageLogService::log(
            teamId: (int) $meeting->team_id,
            service: 'MeetingSummaryService',
            totalTokens: $estimated,
            inputSize: strlen($encoded['text']),
            outputSize: strlen($raw),
            usedToon: (bool) ($encoded['used_toon'] ?? false),
            jsonTokens: (int) ($encoded['json_tokens'] ?? 0),
            toonTokens: (int) ($encoded['toon_tokens'] ?? 0),
            savingsPercentage: (int) ($encoded['savings_percentage'] ?? 0),
        );
    }
}
