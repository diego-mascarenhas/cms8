<?php

namespace App\Helpers;

/**
 * Inbox-only confirmation when the assistant tags a contact.
 * Never sent to the WhatsApp customer.
 */
class AssistantCategoryAssignmentNote
{
    /**
     * @param  list<string>  $categoryNames
     */
    public static function inboxBody(array $categoryNames): string
    {
        $names = [];
        foreach ($categoryNames as $name)
        {
            $name = trim((string) $name);
            if ($name !== '' && ! in_array($name, $names, true))
            {
                $names[] = $name;
            }
        }

        if ($names === [])
        {
            return '';
        }

        if (count($names) === 1)
        {
            return 'Contacto asignado a la categoría: '.$names[0];
        }

        return 'Contacto asignado a las categorías: '.implode(', ', $names);
    }

    /**
     * @param  array<int, mixed>  $toolResults
     * @return list<string>
     */
    public static function extractCategoryNamesFromToolResults(array $toolResults): array
    {
        $names = [];
        foreach ($toolResults as $item)
        {
            $names = array_merge($names, self::extractCategoryNames(self::toolResultToString($item)));
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    public static function extractCategoryNames(string $text): array
    {
        $names = [];
        if (preg_match_all('/assigned to category:\s*([^\n.]+)/iu', $text, $matches))
        {
            foreach ($matches[1] as $raw)
            {
                $name = trim((string) $raw);
                $name = trim((string) (preg_replace('/\s*Do not mention.*$/iu', '', $name) ?? $name));
                if ($name !== '')
                {
                    $names[] = $name;
                }
            }
        }

        if (preg_match_all('/contacto asignado a las categor[ií]as:\s*(.+)$/imu', $text, $matches))
        {
            foreach ($matches[1] as $list)
            {
                foreach (preg_split('/\s*,\s*/u', trim((string) $list)) ?: [] as $name)
                {
                    $name = trim((string) $name);
                    if ($name !== '')
                    {
                        $names[] = $name;
                    }
                }
            }
        }

        if (preg_match_all('/contacto asignado a la categor[ií]a:\s*(.+)$/imu', $text, $matches))
        {
            foreach ($matches[1] as $raw)
            {
                $name = trim((string) $raw);
                if ($name !== '')
                {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    public static function looksLikeAssignmentEcho(string $text): bool
    {
        $text = trim($text);
        if ($text === '')
        {
            return false;
        }

        if (strcasecmp($text, 'No response text') === 0)
        {
            return false;
        }

        return (bool) preg_match('/assigned to category\s*:/iu', $text)
            || (bool) preg_match('/contacto asignado a la categor/iu', $text)
            || str_contains(mb_strtolower($text), 'do not mention the tag');
    }

    public static function stripFromCustomerText(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $kept = [];
        foreach ($lines as $line)
        {
            if (self::looksLikeAssignmentEcho($line))
            {
                continue;
            }
            $kept[] = $line;
        }

        $out = trim(implode("\n", $kept));
        if ($out === '' || strcasecmp($out, 'No response text') === 0 || self::looksLikeAssignmentEcho($out))
        {
            return '';
        }

        return $out;
    }

    private static function toolResultToString(mixed $item): string
    {
        if (is_string($item))
        {
            return $item;
        }

        if (is_array($item))
        {
            foreach (['content', 'text', 'output', 'result'] as $key)
            {
                if (isset($item[$key]) && is_string($item[$key]))
                {
                    return $item[$key];
                }
            }

            return '';
        }

        if (is_object($item) && method_exists($item, '__toString'))
        {
            return (string) $item;
        }

        return '';
    }
}
