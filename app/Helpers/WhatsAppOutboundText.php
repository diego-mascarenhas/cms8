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

        return self::normalizeBrokenEmphasis($text);
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
}
