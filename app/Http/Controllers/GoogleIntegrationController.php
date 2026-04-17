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

        return redirect()->route('team-settings.edit', ['team' => $request->user()->currentTeam, 'group' => 'analytics'])
            ->with('success', 'Google account connected successfully.');
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
