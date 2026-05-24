<?php

namespace App\Support;

/**
 * Detects a task status change applied via {@see \App\Services\AssistantToolsService::updateTaskStatus}.
 */
final class AssistantTaskStatusUpdate
{
    public const SENTINEL_LINE_PREFIX = 'humano_task_status_updated:';

    /**
     * @return array{task_id: int, status_id: int, status_name: string}|null
     */
    public static function extractFromToolResults(array $toolResults): ?array
    {
        foreach ($toolResults as $item)
        {
            $text = self::toolResultItemToString($item);
            if ($text === null || $text === '')
            {
                continue;
            }

            if (! preg_match('/'.preg_quote(self::SENTINEL_LINE_PREFIX, '/').'(\{.*\})/s', $text, $m))
            {
                continue;
            }

            $payload = json_decode($m[1], true);
            if (! is_array($payload))
            {
                continue;
            }

            $taskId = (int) ($payload['task_id'] ?? 0);
            $statusId = (int) ($payload['status_id'] ?? 0);
            $statusName = trim((string) ($payload['status_name'] ?? ''));

            if ($taskId < 1 || $statusId < 1)
            {
                continue;
            }

            return [
                'task_id' => $taskId,
                'status_id' => $statusId,
                'status_name' => $statusName,
            ];
        }

        return null;
    }

    /**
     * @return array{task_id: int, status_id: int, status_name: string}
     */
    public static function payload(int $taskId, int $statusId, string $statusName): array
    {
        return [
            'task_id' => $taskId,
            'status_id' => $statusId,
            'status_name' => $statusName,
        ];
    }

    public static function formatSentinelLine(int $taskId, int $statusId, string $statusName): string
    {
        return self::SENTINEL_LINE_PREFIX.json_encode(
            self::payload($taskId, $statusId, $statusName),
            JSON_UNESCAPED_UNICODE,
        );
    }

    private static function toolResultItemToString(mixed $item): ?string
    {
        if (is_string($item))
        {
            return $item;
        }
        if ($item instanceof \Stringable)
        {
            return (string) $item;
        }
        if (is_array($item))
        {
            foreach (['result', 'content', 'output', 'text', 'message'] as $key)
            {
                if (isset($item[$key]) && is_string($item[$key]))
                {
                    return $item[$key];
                }
            }
        }
        if (is_object($item))
        {
            foreach (['result', 'content', 'output', 'text', 'message'] as $prop)
            {
                if (property_exists($item, $prop))
                {
                    $v = $item->{$prop};
                    if (is_string($v))
                    {
                        return $v;
                    }
                    if ($v instanceof \Stringable)
                    {
                        return (string) $v;
                    }
                }
            }
        }

        return null;
    }
}
