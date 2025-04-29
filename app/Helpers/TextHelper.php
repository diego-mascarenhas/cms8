<?php

namespace App\Helpers;

class TextHelper
{
    public static function sanitizeAndLink($text)
    {
        // Remove leading @ from URLs
        $text = preg_replace('/@(?=https?:\/\/)/', '', $text);
        // Escape HTML
        $text = e($text);
        // Auto-link URLs
        $pattern = '/(https?:\/\/[^\s]+)/i';
        $replace = '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>';
        return preg_replace($pattern, $replace, $text);
    }
}
