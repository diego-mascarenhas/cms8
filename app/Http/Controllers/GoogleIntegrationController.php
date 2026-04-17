<?php

namespace App\Http\Controllers;

use App\Enums\ExternalProvider;
use App\Services\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleIntegrationController extends Controller
{
    public function __construct(private readonly GoogleOAuthService $googleOAuthService)
    {
    }

    public function connect(Request $request): RedirectResponse
    {
        $cid = (string) config('services.google.client_id');

        if ($cid === '')
        {
            $team = $request->user()->currentTeam;

            return $team !== null
                ? redirect()->route('team-settings.index', $team)->with('warning', 'Google OAuth is not configured: set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET on the server, then run php artisan config:clear.')
                : redirect()->route('error-without-team')->with('warning', 'Google OAuth is not configured: set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET on the server, then run php artisan config:clear.');
        }

        $url = $this->googleOAuthService->buildAuthorizationUrl($request->user());

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $this->googleOAuthService->exchangeCode($request->user(), $validated['code']);

        $team = $request->user()->currentTeam;

        return $team !== null
            ? redirect()->route('team-settings.index', $team)->with('success', 'Google account connected successfully.')
            : redirect()->route('error-without-team')->with('warning', 'Google account connected, but no current team was found.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $account = $request->user()
            ->externalAccounts()
            ->where('provider', ExternalProvider::Google)
            ->where('team_id', $request->user()->currentTeam?->id)
            ->first();

        if ($account !== null)
        {
            $account->delete();
        }

        return back()->with('success', 'Google account disconnected successfully.');
    }
}
