<?php

namespace App\Helpers;

/**
 * Normalizes assistant / Markdown-style text for WhatsApp so URLs stay linkified.
 * WhatsApp treats **https://...** as bold text, not a tappable link.
 */
class WhatsAppOutboundText
{
    /**
     * Remove Markdown emphasis around http(s) URLs (bold/italic/underscore).
     */
    public static function sanitize(string $text): string
    {
        if ($text === '')
        {
            return $text;
        }

        $text = self::convertMarkdownTablesToList($text);
        $text = self::normalizeBrokenEmphasis($text);

        $patterns = [
            '/\*\*\s*(https?:\/\/[^*\s]+)\s*\*\*/u',
            '/\*(https?:\/\/[^*\s]+)\*/u',
            '/__(https?:\/\/[^_\s]+)__/u',
            '/_(https?:\/\/[^_\s]+)_/u',
        ];

        $previous = null;
        $maxPasses = 5;
        while ($maxPasses-- > 0 && $text !== $previous)
        {
            $previous = $text;
            foreach ($patterns as $pattern)
            {
                $text = preg_replace($pattern, '$1', $text) ?? $text;
            }
        }

        $text = self::normalizeEmphasisForWhatsApp($text);

        return self::normalizeBrokenEmphasis($text);
    }

    /**
     * Remove internal QA / routing markers (e.g. from demo seeders) so customers never see them on WhatsApp.
     */
    public static function stripInternalQaMarkers(string $text): string
    {
        if ($text === '')
        {
            return $text;
        }

        $text = preg_replace('/\s*\[DEMO_FLOW:[^\]]+\]/iu', '', $text) ?? $text;
        $text = preg_replace('/\s*\[DEMOFLOW:[^\]]+\]/iu', '', $text) ?? $text;

        return trim($text);
    }

    private static function convertMarkdownTablesToList(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $out = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count)
        {
            $line = trim((string) $lines[$i]);
            if (! self::looksLikeMarkdownTableRow($line))
            {
                $out[] = (string) $lines[$i];
                $i++;

                continue;
            }

            $tableLines = [];
            while ($i < $count && self::looksLikeMarkdownTableRow(trim((string) $lines[$i])))
            {
                $tableLines[] = trim((string) $lines[$i]);
                $i++;
            }

            $out = array_merge($out, self::markdownTableLinesToBulletLines($tableLines));
        }

        return implode("\n", $out);
    }

    private static function looksLikeMarkdownTableRow(string $line): bool
    {
        if ($line === '')
        {
            return false;
        }

        return str_starts_with($line, '|') && str_ends_with($line, '|');
    }

    /**
     * @param  array<int, string>  $tableLines
     * @return array<int, string>
     */
    private static function markdownTableLinesToBulletLines(array $tableLines): array
    {
        if ($tableLines === [])
        {
            return [];
        }

        $rows = [];
        foreach ($tableLines as $line)
        {
            $parts = array_map('trim', explode('|', trim($line, '|')));
            if ($parts === [])
            {
                continue;
            }

            $isSeparator = true;
            foreach ($parts as $part)
            {
                if (! preg_match('/^:?-{3,}:?$/', $part))
                {
                    $isSeparator = false;
                    break;
                }
            }
            if ($isSeparator)
            {
                continue;
            }

            $rows[] = $parts;
        }

        if ($rows === [])
        {
            return [];
        }

        $headers = $rows[0];
        $dataRows = array_slice($rows, 1);
        if ($dataRows === [])
        {
            return [$rows[0][0] ?? ''];
        }

        $out = [];
        foreach ($dataRows as $row)
        {
            $pairs = [];
            foreach ($row as $idx => $value)
            {
                $value = trim((string) $value);
                if ($value === '')
                {
                    continue;
                }
                $header = trim((string) ($headers[$idx] ?? ''));
                $pairs[] = $header !== '' ? $header.': '.$value : $value;
            }

            if ($pairs !== [])
            {
                $out[] = '• '.implode(' | ', $pairs);
            }
        }

        return $out;
    }

    /**
     * Fix common malformed markdown emphasis artifacts from LLM outputs:
     * - collapse "***" / "___" runs to "**" / "__"
     * - drop unmatched trailing marker if count is odd.
     */
    private static function normalizeBrokenEmphasis(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace('/\*{3,}/u', '**', $text) ?? $text;
        $text = preg_replace('/_{3,}/u', '__', $text) ?? $text;

        if (substr_count($text, '*') % 2 !== 0)
        {
            $lastStar = strrpos($text, '*');
            if ($lastStar !== false)
            {
                $text = substr($text, 0, $lastStar).substr($text, $lastStar + 1);
            }
        }

        if (substr_count($text, '_') % 2 !== 0)
        {
            $lastUnderscore = strrpos($text, '_');
            if ($lastUnderscore !== false)
            {
                $text = substr($text, 0, $lastUnderscore).substr($text, $lastUnderscore + 1);
            }
        }

        return $text;
    }

    private static function normalizeEmphasisForWhatsApp(string $text): string
    {
        $replacements = [
            '/\*\*([^*\n]+?)([:;,.!?])\*\*/u' => '*$1*$2',
            '/\*\*([^*\n]+)\*\*/u' => '*$1*',
            '/__([^_\n]+?)([:;,.!?])__/u' => '*$1*$2',
            '/__([^_\n]+)__/u' => '*$1*',
            '/(?<!\*)\*([^*\n]+?)([:;,.!?])\*(?!\*)/u' => '*$1*$2',
        ];

        $previous = null;
        $maxPasses = 5;
        while ($maxPasses-- > 0 && $text !== $previous)
        {
            $previous = $text;
            foreach ($replacements as $pattern => $replacement)
            {
                $text = preg_replace($pattern, $replacement, $text) ?? $text;
            }
        }

        return $text;
    }
}
