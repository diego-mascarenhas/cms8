<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationTrackingController extends Controller
{
    /**
     * Handle the tracking pixel request
     */
    public function track(Request $request, $token)
    {
        try {
            // Find the notification by tracking token
            $notification = Notification::findByTrackingToken($token);
            
            if (!$notification) {
                Log::warning('Tracking attempt with invalid token', ['token' => $token]);
                return $this->generateTrackingPixel();
            }

            // Create the tracking event
            NotificationTracking::createEvent(
                $notification->id,
                'opened'
            );

            // Mark notification as read if it's not already
            if (!$notification->is_read) {
                $notification->markAsRead();
            }

            Log::info('Notification tracking event recorded', [
                'notification_id' => $notification->id,
                'token' => $token,
                'contact_id' => $notification->contact_id,
                'ip' => $request->ip(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error recording tracking event', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->generateTrackingPixel();
    }

    /**
     * Handle link click tracking
     */
    public function trackClick(Request $request, $token)
    {
        try {
            // Find the notification by tracking token
            $notification = Notification::findByTrackingToken($token);
            
            if (!$notification) {
                Log::warning('Click tracking attempt with invalid token', ['token' => $token]);
                return response()->json(['error' => 'Invalid token'], 404);
            }

            // Get the redirect URL from the request
            $redirectUrl = $request->query('url');
            
            if (!$redirectUrl) {
                return response()->json(['error' => 'No redirect URL provided'], 400);
            }

            // Create the tracking event
            NotificationTracking::createEvent(
                $notification->id,
                'clicked',
                ['clicked_url' => $redirectUrl]
            );

            // Mark notification as read if it's not already
            if (!$notification->is_read) {
                $notification->markAsRead();
            }

            Log::info('Notification click tracking event recorded', [
                'notification_id' => $notification->id,
                'token' => $token,
                'contact_id' => $notification->contact_id,
                'clicked_url' => $redirectUrl,
                'ip' => $request->ip(),
            ]);

            // Redirect to the original URL
            return redirect($redirectUrl);

        } catch (\Exception $e) {
            Log::error('Error recording click tracking event', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            
            // If there's an error, still try to redirect if URL is provided
            $redirectUrl = $request->query('url');
            if ($redirectUrl) {
                return redirect($redirectUrl);
            }
            
            return response()->json(['error' => 'Tracking failed'], 500);
        }
    }

    /**
     * Generate a 1x1 transparent tracking pixel
     */
    private function generateTrackingPixel()
    {
        // Create a 1x1 transparent PNG image
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        
        return response($pixel, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => strlen($pixel),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Get tracking statistics for a notification
     */
    public function getStats(Notification $notification)
    {
        $stats = [
            'notification_id' => $notification->id,
            'subject' => $notification->subject,
            'sent_at' => $notification->sent_at,
            'is_read' => $notification->is_read,
            'read_at' => $notification->read_at,
            'tracking_events' => []
        ];

        // Get all tracking events for this notification
        $trackingEvents = NotificationTracking::where('notification_id', $notification->id)
            ->orderBy('tracked_at', 'desc')
            ->get()
            ->map(function ($event) {
                return [
                    'event_type' => $event->event_type,
                    'tracked_at' => $event->tracked_at,
                    'ip_address' => $event->ip_address,
                    'user_agent' => $event->user_agent,
                    'metadata' => $event->metadata,
                ];
            });

        $stats['tracking_events'] = $trackingEvents;
        $stats['total_opens'] = $trackingEvents->where('event_type', 'opened')->count();
        $stats['total_clicks'] = $trackingEvents->where('event_type', 'clicked')->count();

        return response()->json($stats);
    }
} 