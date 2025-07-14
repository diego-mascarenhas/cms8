<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MessageDelivery;

class MessageTrackingController extends Controller
{
    // Tracking de apertura
    public function track($token)
    {
        \Log::info('Tracking: token recibido', ['token' => $token]);
        $delivery = \App\Models\MessageDelivery::all()->first(function ($d) use ($token) {
            return hash_equals($d->getTrackingToken(), $token);
        });
        if ($delivery) {
            \Log::info('Tracking: delivery encontrado', ['id' => $delivery->id]);
            if (!$delivery->opened_at) {
                $delivery->markAsOpened();
            }
        } else {
            \Log::info('Tracking: delivery NO encontrado para token', ['token' => $token]);
        }
        // Devolver imagen transparente
        $img = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
        return response($img)->header('Content-Type', 'image/gif');
    }

    // Tracking de click
    public function trackClick(Request $request, $token)
    {
        $delivery = MessageDelivery::all()->first(function ($d) use ($token) {
            return hash_equals($d->getTrackingToken(), $token);
        });
        if ($delivery) {
            $delivery->trackingEvents()->create([
                'event' => 'clicked',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }
        $url = $request->query('url', '/');
        return redirect($url);
    }
}
