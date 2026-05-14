<?php

namespace App\Support;

use App\Models\Message;
use App\Models\User;

/**
 * Detects a campaign message created via the assistant {@see \App\Services\AssistantToolsService::createMessage}
 * tool result and resolves a safe URL to the message editor for the authenticated user.
 */
final class AssistantCreatedMessageRedirect
{
    public const SENTINEL_LINE_PREFIX = 'humano_created_message_id:';

    /**
     * @param  array<int, mixed>  $toolResults
     */
    public static function extractCreatedMessageIdFromToolResults(array $toolResults): ?int
    {
        foreach ($toolResults as $item)
        {
            $text = self::toolResultItemToString($item);
            if ($text === null || $text === '')
            {
                continue;
            }
            if (preg_match('/'.preg_quote(self::SENTINEL_LINE_PREFIX, '/').'(\d+)/', $text, $m))
            {
                $id = (int) $m[1];

                return $id > 0 ? $id : null;
            }
        }

        return null;
    }

    public static function resolveMessageEditUrlForUser(?User $user, int $messageId): ?string
    {
        if ($user === null || $messageId < 1)
        {
            return null;
        }

        if (! $user->can('message.edit'))
        {
            return null;
        }

        $teamId = $user->current_team_id;
        if ($teamId === null)
        {
            return null;
        }

        $message = Message::withoutGlobalScopes()->find($messageId);
        if ($message === null || (int) $message->team_id !== (int) $teamId)
        {
            return null;
        }

        return route('message.edit', $messageId);
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
