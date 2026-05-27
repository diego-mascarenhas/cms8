<?php

namespace App\Support;

/**
 * Detects contact creation applied via {@see \App\Services\AssistantToolsService::createContact}.
 */
final class AssistantContactCreationResult
{
    /**
     * @return array{contact_id: int, created: bool, already_exists: bool}|null
     */
    public static function extractFromToolResults(array $toolResults): ?array
    {
        foreach ($toolResults as $item)
        {
            $parsed = self::parseToolResultText(self::toolResultItemToString($item));
            if ($parsed !== null)
            {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @return array{contact_id: int, created: bool, already_exists: bool}|null
     */
    public static function parseToolResultText(?string $text): ?array
    {
        if ($text === null || trim($text) === '')
        {
            return null;
        }

        if (! preg_match('/Contact (?:created|already exists):.*? \(id:\s*(\d+)\)/i', $text, $matches))
        {
            return null;
        }

        $contactId = (int) $matches[1];
        if ($contactId < 1)
        {
            return null;
        }

        $alreadyExists = stripos($text, 'already exists') !== false;

        return [
            'contact_id' => $contactId,
            'created' => ! $alreadyExists,
            'already_exists' => $alreadyExists,
        ];
    }

    public static function toolResultItemToString(mixed $item): ?string
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
                    $value = $item->{$prop};
                    if (is_string($value))
                    {
                        return $value;
                    }
                    if ($value instanceof \Stringable)
                    {
                        return (string) $value;
                    }
                }
            }
        }

        return null;
    }
}
