<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWebDavAccountRequest;
use App\Http\Requests\LinkWebDavAccountRequest;
use App\Services\WebDavApiClient;
use App\Services\WebDavIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebDavIntegrationController extends Controller
{
    public function __construct(
        private readonly WebDavApiClient $webDavApiClient,
        private readonly WebDavIntegrationService $webDavIntegrationService,
    ) {}

    public function createForm(Request $request): View|RedirectResponse
    {
        $team = $request->user()->currentTeam;

        if ($team === null)
        {
            return redirect()->route('error-without-team');
        }

        $this->authorize('update', $team);

        if ($this->webDavIntegrationService->webDavAccountForTeam($team) !== null)
        {
            return redirect()
                ->route('team-settings.index', $team)
                ->with('warning', __('app.webdav_account_already_linked'));
        }

        return view('integrations.webdav.create', [
            'team' => $team,
            'apiConfigured' => $this->webDavApiClient->isConfigured(),
        ]);
    }

    public function create(CreateWebDavAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if ($team === null)
        {
            return redirect()->route('error-without-team');
        }

        $this->authorize('update', $team);

        try
        {
            $result = $this->webDavIntegrationService->createAccount(
                user: $user,
                team: $team,
                email: $request->validated('email'),
                name: $request->validated('name'),
                davUsername: $request->validated('dav_username'),
                password: $request->validated('password'),
            );
        } catch (\Throwable $exception)
        {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('team-settings.index', $team)
            ->with('success', __('app.webdav_account_created'))
            ->with('webdav_credentials', $result['payload']);
    }

    public function linkForm(Request $request): View|RedirectResponse
    {
        $team = $request->user()->currentTeam;

        if ($team === null)
        {
            return redirect()->route('error-without-team');
        }

        $this->authorize('update', $team);

        if ($this->webDavIntegrationService->webDavAccountForTeam($team) !== null)
        {
            return redirect()
                ->route('team-settings.index', $team)
                ->with('warning', __('app.webdav_account_already_linked'));
        }

        return view('integrations.webdav.link', [
            'team' => $team,
            'apiConfigured' => $this->webDavApiClient->isConfigured(),
        ]);
    }

    public function link(LinkWebDavAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if ($team === null)
        {
            return redirect()->route('error-without-team');
        }

        $this->authorize('update', $team);

        try
        {
            $result = $this->webDavIntegrationService->linkAccount(
                user: $user,
                team: $team,
                email: $request->validated('email'),
                password: $request->validated('password'),
            );
        } catch (\Throwable $exception)
        {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('team-settings.index', $team)
            ->with('success', __('app.webdav_account_linked'))
            ->with('webdav_credentials', $result['payload']);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        if ($team === null)
        {
            return redirect()->route('error-without-team');
        }

        $this->authorize('update', $team);

        $this->webDavIntegrationService->disconnect($team);

        return redirect()
            ->route('team-settings.index', $team)
            ->with('success', __('app.webdav_account_disconnected'));
    }
}
