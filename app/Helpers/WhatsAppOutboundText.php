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

        return $text;
    }
}
