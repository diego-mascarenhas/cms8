<?php

namespace App\Services;

use App\Enums\MeetingTranscriptStatus;
use App\Models\Contact;
use App\Models\MeetingTranscript;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use App\Support\AiTasks;
use Illuminate\Support\Collection;
use RuntimeException;

use function Laravel\Ai\agent;

class MeetingSummaryService
{
    private const INSTRUCTIONS = <<<'PROMPT'
You summarize a meeting transcript for the team.

The transcript lists each turn as speaker and text, in order. Use only what was said. Do not invent people, opinions that were not spoken, topics, deadlines, dates, or agreements.

Write in the language of the transcript.

Respond with ONLY a valid JSON object, no markdown:
{"minutes":"a short synthesis","points":[{"topic":"a point that was discussed","agree":["speaker names who clearly agreed"],"disagree":["speaker names who clearly disagreed"]}],"opinions":[{"speaker":"name as given","text":"the view that person stated, in one sentence"}],"conclusions":[{"title":"what was explicitly agreed, written as a task","owner":"speaker who took it, or empty string","detail":"the agreement in one sentence"}],"opinion":"your own recommendation of what should be done next. This is your view, not a quote from the meeting.","follow_up":{"suggest":false,"reason":"why another meeting is or is not useful","after":["points that should be handled before meeting again"]}}

Rules:
- minutes is a synthesis in the style of Zoom, Google Meet or Teams recaps: 2 to 4 sentences on what the meeting was about and what came out of it. Do not retell the dialogue. Do not write who asked, who answered, or who said a line. Do not quote turns. Speaker names belong in points and conclusions, not in a play-by-play inside minutes.
- Bad minutes: "Ana preguntó la hora; Luis respondió que eran las nueve."
- Good minutes: "Se consultó la hora y el pronóstico del tiempo. No hubo acuerdos."
- conclusions contains only agreements that were actually reached. If nothing was agreed, use an empty array.
- opinions lists each person who stated a view, preference, or objection. One entry per person. Use only what that person said. If nobody stated a view, use an empty array. Do not put the recommendation in opinions.
- agree and disagree list only people who clearly took that side. If it is unclear, leave the name out.
- Do not invent a date or time for a follow-up meeting. follow_up.suggest is true only when another meeting would help after the listed points, otherwise false.
- opinion is a recommendation. Do not present it as something a participant said.
PROMPT;

    /**
     * @return array<string, mixed>
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

        if ($this->isEmpty($summary))
        {
            throw new RuntimeException('No se pudo resumir la reunión.');
        }

        $summary = $this->assignConclusions($meeting, $summary);
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
     * @return array<string, mixed>
     */
    private function parse(string $raw): array
    {
        $raw = preg_replace('/^```\w*\s*|\s*```$/u', '', $raw) ?? $raw;
        $data = json_decode(trim($raw), true);
        if (! is_array($data))
        {
            throw new RuntimeException('No se pudo resumir la reunión.');
        }

        $minutes = $this->line($data['minutes'] ?? $data['synthesis'] ?? '');
        $conclusions = $this->conclusions($data['conclusions'] ?? []);
        if ($conclusions === [])
        {
            foreach ($this->tasks($data['tasks'] ?? []) as $task)
            {
                $conclusions[] = [
                    'title' => $task['title'],
                    'owner' => $task['owner'],
                    'detail' => '',
                ];
            }
        }

        return [
            'minutes' => $minutes,
            'synthesis' => $minutes,
            'points' => $this->points($data['points'] ?? []),
            'opinions' => $this->opinions($data['opinions'] ?? []),
            'conclusions' => $conclusions,
            'opinion' => $this->line($data['opinion'] ?? ''),
            'follow_up' => $this->followUp($data['follow_up'] ?? []),
            'viewpoints' => $this->viewpoints($data['viewpoints'] ?? []),
            'intention' => $this->line($data['intention'] ?? ''),
            'items' => $this->lines($data['items'] ?? []),
            'tasks' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function assignConclusions(MeetingTranscript $meeting, array $summary): array
    {
        $statusId = TaskStatus::query()->where('name', 'TO_DO')->value('id')
            ?? TaskStatus::query()->orderBy('order')->value('id');
        $team = Team::query()->find($meeting->team_id);
        $users = $team ? $team->allUsers() : collect();
        $previous = collect($meeting->summary['conclusions'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && ! empty($row['task_id']) && $this->line($row['title'] ?? '') !== '')
            ->keyBy(fn (array $row): string => mb_strtolower($this->line($row['title'] ?? '')));

        $tasks = [];
        foreach ($summary['conclusions'] as $index => $conclusion)
        {
            $title = $this->line($conclusion['title'] ?? '');
            $key = mb_strtolower($title);
            $tasks[] = [
                'title' => $title,
                'owner' => $this->line($conclusion['owner'] ?? ''),
            ];

            if ($title === '' || $key === '')
            {
                continue;
            }

            $previousRow = $previous->get($key);
            $existingId = is_array($previousRow) ? (int) ($previousRow['task_id'] ?? 0) : 0;
            if ($existingId > 0 && Task::withoutGlobalScopes()->whereKey($existingId)->exists())
            {
                $summary['conclusions'][$index]['task_id'] = $existingId;

                continue;
            }

            if (! $statusId)
            {
                continue;
            }

            $responsibleId = $this->responsibleId($meeting, $this->line($conclusion['owner'] ?? ''), $users);
            if ($responsibleId === null)
            {
                continue;
            }

            $task = Task::withoutGlobalScopes()->create([
                'team_id' => $meeting->team_id,
                'title' => $title,
                'description' => $this->taskDescription($meeting, $conclusion, $responsibleId, $users),
                'responsible_id' => $responsibleId,
                'status_id' => $statusId,
                'start_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'order' => 0,
            ]);
            $summary['conclusions'][$index]['task_id'] = $task->id;
        }

        $summary['tasks'] = $tasks;

        return $summary;
    }

    /**
     * @param  array{title?: string, owner?: string, detail?: string}  $conclusion
     * @param  Collection<int, User>  $users
     */
    private function taskDescription(MeetingTranscript $meeting, array $conclusion, int $responsibleId, Collection $users): string
    {
        $description = 'Conclusión de la reunión «'.$meeting->title.'».';
        $detail = $this->line($conclusion['detail'] ?? '');
        if ($detail !== '')
        {
            $description .= "\n".$detail;
        }

        $owner = $this->line($conclusion['owner'] ?? '');
        $assigned = $users->first(fn (User $user): bool => (int) $user->id === $responsibleId);
        $assignedName = $assigned instanceof User ? $this->normalize($assigned->name) : '';
        if ($owner !== '' && $this->normalize($owner) !== $assignedName)
        {
            $description .= "\nEn la reunión se nombró a {$owner}.";
        }

        return $description;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function responsibleId(MeetingTranscript $meeting, string $owner, Collection $users): ?int
    {
        $ownerKey = $this->normalize($owner);
        if ($ownerKey !== '')
        {
            $matched = $users->first(fn (User $user): bool => $this->normalize($user->name) === $ownerKey);
            if ($matched instanceof User)
            {
                return (int) $matched->id;
            }

            $contactUserId = $this->contactUserId($meeting, $ownerKey);
            if ($contactUserId !== null)
            {
                return $contactUserId;
            }
        }

        return $meeting->user_id ? (int) $meeting->user_id : null;
    }

    private function contactUserId(MeetingTranscript $meeting, string $ownerKey): ?int
    {
        $ids = collect($meeting->turns ?? [])->pluck('contact_id')->filter()->unique()->values();
        if ($ids->isEmpty())
        {
            return null;
        }

        $contacts = Contact::withoutGlobalScope('team')
            ->where('team_id', $meeting->team_id)
            ->whereIn('id', $ids)
            ->whereNotNull('user_id')
            ->get();

        foreach ($contacts as $contact)
        {
            $name = $this->normalize(trim($contact->name.' '.($contact->surname ?? '')));
            if ($name === $ownerKey)
            {
                return (int) $contact->user_id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function isEmpty(array $summary): bool
    {
        $followUp = is_array($summary['follow_up'] ?? null) ? $summary['follow_up'] : [];

        return $summary['minutes'] === ''
            && $summary['points'] === []
            && $summary['opinions'] === []
            && $summary['conclusions'] === []
            && $summary['opinion'] === ''
            && ($followUp['suggest'] ?? false) !== true
            && ($followUp['reason'] ?? '') === ''
            && ($followUp['after'] ?? []) === []
            && $summary['viewpoints'] === []
            && $summary['intention'] === ''
            && $summary['items'] === [];
    }

    /**
     * @return list<array{speaker: string, text: string}>
     */
    private function opinions(mixed $value): array
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
                $text = $this->line($item);
                if ($text !== '')
                {
                    $rows[] = ['speaker' => '', 'text' => $text];
                }

                continue;
            }

            if (! is_array($item))
            {
                continue;
            }

            $text = $this->line($item['text'] ?? $item['view'] ?? '');
            if ($text === '')
            {
                continue;
            }

            $rows[] = [
                'speaker' => $this->line($item['speaker'] ?? ''),
                'text' => $text,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{topic: string, agree: list<string>, disagree: list<string>}>
     */
    private function points(mixed $value): array
    {
        if (! is_array($value))
        {
            return [];
        }

        $points = [];
        foreach ($value as $item)
        {
            if (! is_array($item))
            {
                continue;
            }

            $topic = $this->line($item['topic'] ?? '');
            if ($topic === '')
            {
                continue;
            }

            $points[] = [
                'topic' => $topic,
                'agree' => $this->names($item['agree'] ?? []),
                'disagree' => $this->names($item['disagree'] ?? []),
            ];
        }

        return $points;
    }

    /**
     * @return list<array{title: string, owner: string, detail: string}>
     */
    private function conclusions(mixed $value): array
    {
        if (! is_array($value))
        {
            return [];
        }

        $conclusions = [];
        foreach ($value as $item)
        {
            if (is_string($item))
            {
                $title = $this->line($item);
                if ($title !== '')
                {
                    $conclusions[] = ['title' => $title, 'owner' => '', 'detail' => ''];
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

            $conclusions[] = [
                'title' => $title,
                'owner' => $this->line($item['owner'] ?? ''),
                'detail' => $this->line($item['detail'] ?? ''),
            ];
        }

        return $conclusions;
    }

    /**
     * @return array{suggest: bool, reason: string, after: list<string>}
     */
    private function followUp(mixed $value): array
    {
        if (! is_array($value))
        {
            return ['suggest' => false, 'reason' => '', 'after' => []];
        }

        return [
            'suggest' => filter_var($value['suggest'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'reason' => $this->line($value['reason'] ?? ''),
            'after' => $this->lines($value['after'] ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    private function names(mixed $value): array
    {
        return $this->lines($value);
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

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
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
