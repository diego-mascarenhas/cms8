<?php

namespace App\Http\Controllers;

use App\Jobs\RecordTrackEventJob;
use App\Models\Communication;
use App\Services\Communications\CommunicationLinkTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommunicationTrackingController extends Controller
{
    public function open(string $token): Response
    {
        RecordTrackEventJob::dispatch('communication.open', [
            'token' => $token,
        ]);

        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($pixel),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function click(Request $request, string $token, CommunicationLinkTracker $tracker): RedirectResponse
    {
        $url = (string) $request->query('url');
        $signature = (string) $request->query('sig');
        $communication = Communication::findByTrackingToken($token);

        if (
            ! $communication
            || ! $tracker->isSafeHttpUrl($url)
            || ! hash_equals($communication->clickSignature($url), $signature)
        ) {
            abort(404);
        }

        RecordTrackEventJob::dispatch('communication.click', [
            'communication_id' => $communication->id,
            'url' => $url,
        ]);

        return redirect()->away($url);
    }
}
