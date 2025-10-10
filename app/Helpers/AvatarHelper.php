<?php

namespace App\Helpers;

class AvatarHelper
{
    /**
     * Generate an SVG avatar with initials
     *
     * @return string Base64 encoded SVG data URI
     */
    public static function generate(string $name, int $size = 100, ?string $backgroundColor = null, ?string $textColor = null): string
    {
        // Get initials from name
        $initials = self::getInitials($name);

        // Generate colors if not provided
        if ($backgroundColor === null)
        {
            $backgroundColor = self::generateColorFromName($name);
        }

        if ($textColor === null)
        {
            $textColor = self::getContrastColor($backgroundColor);
        }

        // Calculate font size (approximately 40% of avatar size)
        $fontSize = round($size * 0.4);

        // Generate SVG
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
	<rect width="{$size}" height="{$size}" fill="{$backgroundColor}" rx="4"/>
	<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$fontSize}" font-weight="600" fill="{$textColor}">{$initials}</text>
</svg>
SVG;

        // Return as data URI
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Get initials from a name
     */
    protected static function getInitials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        $initials = '';

        if (count($words) >= 2)
        {
            // First and last name
            $initials = mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1);
        } elseif (count($words) === 1)
        {
            // Only one word, take first two characters
            $initials = mb_substr($words[0], 0, min(2, mb_strlen($words[0])));
        }

        return mb_strtoupper($initials);
    }

    /**
     * Generate a consistent color based on the name
     *
     * @return string Hex color code
     */
    protected static function generateColorFromName(string $name): string
    {
        // Predefined color palette (similar to ui-avatars.com)
        $colors = [
            '#1abc9c', '#2ecc71', '#3498db', '#9b59b6', '#34495e',
            '#16a085', '#27ae60', '#2980b9', '#8e44ad', '#2c3e50',
            '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6', '#f39c12',
            '#d35400', '#c0392b', '#7f8c8d',
        ];

        // Generate a hash from the name
        $hash = 0;
        for ($i = 0; $i < strlen($name); $i++)
        {
            $hash = ord($name[$i]) + (($hash << 5) - $hash);
        }

        // Select color based on hash
        $index = abs($hash) % count($colors);

        return $colors[$index];
    }

    /**
     * Get contrast color (black or white) based on background color
     */
    protected static function getContrastColor(string $hexColor): string
    {
        // Remove # if present
        $hexColor = ltrim($hexColor, '#');

        // Convert to RGB
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // Return black or white based on luminance
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
}
