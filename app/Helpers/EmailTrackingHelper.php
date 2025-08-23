<?php

namespace App\Helpers;

use App\Models\MessageDelivery;

class EmailTrackingHelper
{
    /**
     * Rewrite all URLs in HTML content to make them trackable
     */
    public static function rewriteUrlsForTracking(string $html, MessageDelivery $delivery): string
    {
        // Only rewrite URLs if we have a valid delivery with tracking token
        if (!$delivery || !$delivery->getTrackingToken()) {
            return $html;
        }

        // Pattern to match href attributes in anchor tags
        $pattern = '/(<a[^>]*href=["\'])([^"\']+)(["\'][^>]*>)/i';

        return preg_replace_callback($pattern, function ($matches) use ($delivery) {
            $beforeUrl = $matches[1]; // <a href="
            $originalUrl = $matches[2]; // the actual URL
            $afterUrl = $matches[3];   // ">

            // Skip if it's already a tracking URL, mailto, tel, or anchor links
            if (self::shouldSkipUrl($originalUrl)) {
                return $matches[0]; // Return original match
            }

            // Create tracking URL
            $trackingUrl = self::createTrackingUrl($delivery->getTrackingToken(), $originalUrl);

            return $beforeUrl . $trackingUrl . $afterUrl;
        }, $html);
    }

    /**
     * Check if URL should be skipped from tracking
     */
    private static function shouldSkipUrl(string $url): bool
    {
        // Skip if URL is empty or just whitespace
        if (trim($url) === '') {
            return true;
        }

        // Skip anchor links
        if (strpos($url, '#') === 0) {
            return true;
        }

        // Skip mailto and tel links
        if (preg_match('/^(mailto|tel):/i', $url)) {
            return true;
        }

        // Skip if it's already a tracking URL
        if (strpos($url, '/track/click/') !== false) {
            return true;
        }

        // Skip javascript: links
        if (preg_match('/^javascript:/i', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Create a tracking URL for the given original URL
     */
    private static function createTrackingUrl(string $token, string $originalUrl): string
    {
        // Make sure the original URL is properly encoded
        $encodedUrl = urlencode($originalUrl);

        // Create the tracking URL using the correct route
        return url("/message/track/click/{$token}?url={$encodedUrl}");
    }

    /**
     * Add tracking pixel to HTML content
     */
    public static function addTrackingPixel(string $html, MessageDelivery $delivery): string
    {
        if (!$delivery || !$delivery->getTrackingToken()) {
            return $html;
        }

        $trackingImg = '<img src="' . $delivery->getTrackingUrl() . '" width="1" height="1" style="display:none;" alt="" />';

        // Insert tracking pixel before </body> or at the end
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $trackingImg . '</body>', $html);
        } else {
            return $html . $trackingImg;
        }
    }
}
