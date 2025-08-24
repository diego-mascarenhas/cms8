<?php

namespace App\Helpers;

use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;

class EmailTrackingHelper
{
    /**
     * Rewrite all URLs in HTML content to make them trackable
     */
    public static function rewriteUrlsForTracking(string $html, MessageDelivery $delivery): string
    {
        // Only rewrite URLs if we have a valid delivery with tracking token and click tracking is enabled
        if (!$delivery || !$delivery->getTrackingToken()) {
            return $html;
        }

        // Check if click tracking is enabled for this message
        // Only rewrite URLs if explicitly enabled to avoid SPAM issues
        if (!$delivery->message || !$delivery->message->enable_click_tracking) {
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

            // Save the original link to database
            self::saveTrackedLink($delivery, $originalUrl);

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
     * Save tracked link to database
     */
    private static function saveTrackedLink(MessageDelivery $delivery, string $originalUrl): void
    {
        try {
            // Check if this link is already saved for this delivery to avoid duplicates
            $existingLink = MessageDeliveryLink::where('message_delivery_id', $delivery->id)
                ->where('link', $originalUrl)
                ->first();

            if (!$existingLink) {
                MessageDeliveryLink::create([
                    'message_delivery_id' => $delivery->id,
                    'link' => $originalUrl,
                    'created_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't break email sending
            \Log::error('Failed to save tracked link', [
                'delivery_id' => $delivery->id,
                'url' => $originalUrl,
                'error' => $e->getMessage()
            ]);
        }
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

        // Check if open tracking is enabled for this message
        // Only add tracking pixel if explicitly enabled to avoid SPAM issues
        if (!$delivery->message || !$delivery->message->enable_open_tracking) {
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

    /**
     * Add unsubscribe link to HTML content
     */
    public static function addUnsubscribeLink(string $html, MessageDelivery $delivery): string
    {
        // Check if delivery, contact, email exist and if unsubscribe is enabled for this message
        if (!$delivery || !$delivery->contact || !$delivery->contact->email) {
            return $html;
        }

        // Check if unsubscribe is enabled for this message
        // Only add unsubscribe link if explicitly enabled
        if (!$delivery->message || !$delivery->message->show_unsubscribe) {
            return $html;
        }

        $unsubscribeUrl = url("/unsubscribe/" . urlencode($delivery->contact->email));

        $unsubscribeHtml = '
        <div style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-top: 1px solid #e9ecef; text-align: center; font-family: Arial, sans-serif;">
            <p style="margin: 0; color: #6c757d; font-size: 12px;">
                ' . __('If you no longer wish to receive emails like this') . ',
                <a href="' . $unsubscribeUrl . '" style="color: #dc3545; text-decoration: none; font-weight: bold;">' . __('click here to unsubscribe') . '</a>
            </p>
        </div>';

        // Insert unsubscribe link before tracking pixel or </body>
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $unsubscribeHtml . '</body>', $html);
        } else {
            return $html . $unsubscribeHtml;
        }
    }
}
