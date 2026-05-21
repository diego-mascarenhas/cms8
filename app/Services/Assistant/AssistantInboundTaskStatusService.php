<?php

namespace App\Services\Assistant;

use App\Models\Task;
use App\Models\User;
use App\Services\AssistantToolsService;
use App\Support\AssistantTaskStatusUpdate;

/**
 * When the LLM replies without calling update_task_status, apply clear user intent server-side
 * (web assistant + WhatsApp share the same {@see AssistantToolsService} path).
 */
class AssistantInboundTaskStatusService
{
    private const AFFIRMATIVE_PATTERN = '/^(s[ií]|ok|dale|confirmo|confirmar|de acuerdo|perfecto|listo|yes|yep)\.?$/iu';

    public function __construct(
        protected AssistantToolsService $assistantTools,
    ) {}

    /**
     * @param  array<int, array{direction: string, body: string}>  $history
     * @return array{tool_result: string, update: array{task_id: int, status_id: int, status_name: string}}|null
     */
    public function tryApplyFromUserMessage(
        User $user,
        int $teamId,
        string $message,
        array $history,
        array $existingToolResults,
    ): ?array {
        if (AssistantTaskStatusUpdate::extractFromToolResults($existingToolResults) !== null)
        {
            return null;
        }

        $statusInput = $this->resolveStatusInput($message, $history);
        if ($statusInput === null)
        {
            return null;
        }

        $taskId = $this->resolveTaskId($message, $history, $teamId);
        if ($taskId === null)
        {
            return null;
        }

        $this->assistantTools->setRequestContext($user->id, $teamId, null);
        $toolResult = $this->assistantTools->execute('update_task_status', [
            'task_id' => $taskId,
            'status' => $statusInput,
        ]);

        $update = AssistantTaskStatusUpdate::extractFromToolResults([$toolResult]);
        if ($update === null)
        {
            return null;
        }

        if (
            str_contains($toolResult, 'not found')
            || str_contains($toolResult, 'Unknown status')
            || str_contains($toolResult, 'permission')
            || str_contains($toolResult, 'required')
        ) {
            return null;
        }

        return [
            'tool_result' => $toolResult,
            'update' => $update,
        ];
    }

    /**
     * @param  array<int, array{direction: string, body: string}>  $history
     */
    private function resolveStatusInput(string $message, array $history): ?string
    {
        $fromMessage = $this->detectStatusPhrase($message);
        if ($fromMessage !== null)
        {
            return $fromMessage;
        }

        if (! preg_match(self::AFFIRMATIVE_PATTERN, trim($message)))
        {
            return null;
        }

        $lastOutbound = $this->lastMessageBody($history, 'outbound');
        if ($lastOutbound === null)
        {
            return null;
        }

        return $this->detectStatusPhrase($lastOutbound);
    }

    private function detectStatusPhrase(string $text): ?string
    {
        $lower = mb_strtolower($text);

        if (preg_match('/\b(en\s+)?revisi[oó]n\b/u', $lower) || preg_match('/\breview\b/i', $text))
        {
            return 'REVIEW';
        }
        if (preg_match('/\b(completad[ao]|finalizad[ao]|terminad[ao]|hech[ao]|done)\b/u', $lower))
        {
            return 'DONE';
        }
        if (preg_match('/\b(en\s+)?progreso\b/u', $lower) || preg_match('/\bin\s*progress\b/i', $text))
        {
            return 'IN_PROGRESS';
        }
        if (preg_match('/\b(por\s+hacer|pendiente|to\s*do)\b/u', $lower))
        {
            return 'TO_DO';
        }

        if (preg_match('/\bTO_DO\b|\bIN_PROGRESS\b|\bREVIEW\b|\bDONE\b/', $text, $m))
        {
            return $m[0];
        }

        return null;
    }

    /**
     * @param  array<int, array{direction: string, body: string}>  $history
     */
    private function resolveTaskId(string $message, array $history, int $teamId): ?int
    {
        if (preg_match('/\b(?:id|#)\s*(\d+)\b/i', $message, $m))
        {
            return $this->taskIdForTeam((int) $m[1], $teamId);
        }

        if (preg_match('/\(\s*id:\s*(\d+)\s*\)/i', $message, $m))
        {
            return $this->taskIdForTeam((int) $m[1], $teamId);
        }

        $corpus = $message;
        foreach (array_reverse($history) as $item)
        {
            $corpus .= "\n".($item['body'] ?? '');
        }

        if (preg_match('/\(\s*id:\s*(\d+)\s*\)/i', $corpus, $m))
        {
            return $this->taskIdForTeam((int) $m[1], $teamId);
        }

        if (preg_match('/\bID:\s*(\d+)\b/i', $corpus, $m))
        {
            return $this->taskIdForTeam((int) $m[1], $teamId);
        }

        $title = $this->extractQuotedTaskTitle($message);
        if ($title === null)
        {
            $title = $this->extractQuotedTaskTitle($corpus);
        }

        if ($title !== null)
        {
            return $this->taskIdByTitleSearch($title, $teamId);
        }

        if (preg_match('/\b(?:pasar(?:la)?|mover(?:la)?|marcar(?:la)?)\b/iu', $message))
        {
            $recent = $this->mostRecentTaskIdFromHistory($history, $teamId);

            return $recent;
        }

        return null;
    }

    private function taskIdForTeam(int $taskId, int $teamId): ?int
    {
        if ($taskId < 1)
        {
            return null;
        }

        $exists = Task::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereKey($taskId)
            ->exists();

        return $exists ? $taskId : null;
    }

    private function taskIdByTitleSearch(string $title, int $teamId): ?int
    {
        $title = trim($title);
        if ($title === '')
        {
            return null;
        }

        $task = Task::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('title', 'like', '%'.$title.'%')
            ->orderByDesc('updated_at')
            ->first();

        return $task !== null ? (int) $task->id : null;
    }

    /**
     * @param  array<int, array{direction: string, body: string}>  $history
     */
    private function mostRecentTaskIdFromHistory(array $history, int $teamId): ?int
    {
        foreach (array_reverse($history) as $item)
        {
            $body = (string) ($item['body'] ?? '');
            if (preg_match('/\(\s*id:\s*(\d+)\s*\)/i', $body, $m))
            {
                $id = $this->taskIdForTeam((int) $m[1], $teamId);
                if ($id !== null)
                {
                    return $id;
                }
            }
            if (preg_match('/\bID:\s*(\d+)\b/i', $body, $m))
            {
                $id = $this->taskIdForTeam((int) $m[1], $teamId);
                if ($id !== null)
                {
                    return $id;
                }
            }
        }

        $latest = Task::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->orderByDesc('updated_at')
            ->first();

        return $latest !== null ? (int) $latest->id : null;
    }

    private function extractQuotedTaskTitle(string $text): ?string
    {
        if (preg_match('/[«"\']([^»"\']+)[»"\']/u', $text, $m))
        {
            $title = trim($m[1]);
            if ($title !== '' && mb_strlen($title) <= 120)
            {
                return $title;
            }
        }

        if (preg_match('/\b(?:tarea|task)\s+([A-Za-z0-9ÁÉÍÓÚáéíóúñÑ][\w\sÁÉÍÓÚáéíóúñÑ!.?-]{1,80})/iu', $text, $m))
        {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * @param  array<int, array{direction: string, body: string}>  $history
     */
    private function lastMessageBody(array $history, string $direction): ?string
    {
        foreach (array_reverse($history) as $item)
        {
            if (($item['direction'] ?? '') === $direction)
            {
                $body = trim((string) ($item['body'] ?? ''));

                return $body !== '' ? $body : null;
            }
        }

        return null;
    }
}
