<?php

namespace App\Helpers;

class TextHelper
{
    public static function sanitizeAndLink($text)
    {
        // Remove leading @ from URLs
        $text = preg_replace('/@(?=https?:\\/\\/)/', '', $text);

        // Replace problematic characters in URLs (optional)
        $pattern = '/(https?:\\/\\/[^\\s]+)/i';
        $text = preg_replace_callback($pattern, function ($matches) {
            $url = $matches[1];
            $safeUrl = str_replace([' ', '"', "'"], ['%20', '%22', '%27'], $url);
            $display = strlen($url) > 80 ? substr($url, 0, 80) . '...' : $url;

            return '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer" class="chat-link">' . $display . '</a>';
        }, $text);

        // Escape everything except <a> tags
        $text = preg_replace_callback('/(<a.*?>.*?<\\/a>)|([^<]+)/is', function ($matches) {
            if (! empty($matches[1])) {
                return $matches[1];
            } // keep <a> tags

            return e($matches[2]);
        }, $text);

        return $text;
    }
}
