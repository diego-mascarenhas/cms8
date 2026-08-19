<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public demo endpoints for embedded HTML landings (calendar + assistant widgets).
 * Intended for smoke tests and static sites loading {@see public/js/cms8-widgets.js}.
 */
class LandingEmbedDemoController extends Controller
{
    /**
     * Sample slots for the calendar embed demo (no persistence).
     */
    public function calendar(): JsonResponse
    {
        return response()->json([
            'title' => __('Demo calendar'),
            'slots' => [
                ['id' => '1', 'label' => 'Mon — 10:00', 'available' => true],
                ['id' => '2', 'label' => 'Mon — 14:30', 'available' => true],
                ['id' => '3', 'label' => 'Tue — 09:00', 'available' => true],
                ['id' => '4', 'label' => 'Wed — 16:00', 'available' => false],
            ],
        ]);
    }

    /**
     * Echo-style assistant for embed demo (replace with real assistant + auth later).
     */
    public function assistant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $text = $validated['message'];

        return response()->json([
            'reply' => __('Demo assistant says: :text', ['text' => $text]),
            'demo' => true,
        ]);
    }
}
