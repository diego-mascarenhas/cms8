<?php

namespace App\Http\Controllers;

use App\Helpers\EmailTrackingHelper;
use App\Jobs\RecordTrackEventJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessageTrackingController extends Controller
{
    // Tracking de apertura
    public function track(Request $request, $token)
    {
        RecordTrackEventJob::dispatch('mailer.open', $this->trackPayload($request, [
            'token' => (string) $token,
        ]));

        $img = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

        return response($img)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    // Tracking de click
    public function trackClick(Request $request, $token)
    {
        $token = (string) $token;
        $url = (string) $request->query('url', '/');

        RecordTrackEventJob::dispatch('mailer.click', $this->trackPayload($request, [
            'token' => $token,
            'url' => $url,
        ]));

        if (preg_match('/^[a-f0-9]{64}$/', $token) === 1)
        {
            $url = EmailTrackingHelper::appendNewsRef($url, $token);
        }

        return redirect($url);
    }

    /**
     * The landing page calls this after the form is completed or the section is reached.
     * Pass the news variable from the destination URL.
     */
    public function trackConversion(Request $request, ?string $token = null): Response
    {
        $token = $token ?: (string) $request->input('news', '');
        RecordTrackEventJob::dispatch('mailer.conversion', [
            'token' => $token,
        ]);

        if ($request->isMethod('post'))
        {
            return response('', 204);
        }

        $img = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

        return response($img)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function trackPayload(Request $request, array $payload): array
    {
        return $payload + [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
