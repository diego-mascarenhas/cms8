<?php

namespace App\Http\Controllers;

use App\Jobs\RecordGoLinkHitJob;
use App\Services\Go\GoLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class GoRedirectController extends Controller
{
    public function __construct(private GoLinkService $goLinks) {}

    public function show(Request $request, string $code): RedirectResponse|Response
    {
        $link = $this->goLinks->findActiveByCode($code);
        if (! $link)
        {
            return response('Link not found', 404);
        }

        $ip = (string) $request->ip();

        RecordGoLinkHitJob::dispatch($link->id, [
            'ip_hash' => $ip !== '' ? hash('sha256', $ip) : null,
            'user_agent' => $this->nullableLimit($request->userAgent(), 512),
            'referer' => $this->nullableLimit($request->headers->get('referer'), 2048),
        ])->afterResponse();

        return redirect()->away($link->target_url, 302);
    }

    private function nullableLimit(?string $value, int $limit): ?string
    {
        $value = trim((string) $value);
        if ($value === '')
        {
            return null;
        }

        return Str::limit($value, $limit, '');
    }
}
