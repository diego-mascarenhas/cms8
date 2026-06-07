<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWebDavAccountRequest;
use App\Http\Requests\LinkWebDavAccountRequest;
use App\Services\WebDavApiClient;
use App\Services\WebDavFullSyncService;
use App\Services\WebDavIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebDavIntegrationController extends Controller
{
    public function __construct(
        private readonly WebDavApiClient $webDavApiClient,
        private readonly WebDavIntegrationService $webDavIntegrationService,
        private readonly WebDavFullSyncService $webDavFullSyncService,
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

    public function syncAll(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        if ($team === null)
        {
            return redirect()->route('error-without-team');
        }

        $this->authorize('update', $team);

        if ($this->webDavIntegrationService->webDavAccountForTeam($team) === null)
        {
            return redirect()
                ->route('team-settings.index', $team)
                ->with('warning', __('app.webdav_sync_all_no_account'));
        }

        if (! $this->webDavApiClient->isConfigured())
        {
            return redirect()
                ->route('team-settings.index', $team)
                ->with('warning', __('app.webdav_api_not_configured'));
        }

        try
        {
            $result = $this->webDavFullSyncService->queueFullSyncForTeam($team);
        } catch (\Throwable $exception)
        {
            return redirect()
                ->route('team-settings.index', $team)
                ->with('warning', $exception->getMessage());
        }

        $outbound = $result['outbound'];
        $inbound = $result['inbound'];

        return redirect()
            ->route('team-settings.index', $team)
            ->with('success', __('app.webdav_sync_all_queued', [
                'contacts_out' => $outbound['contacts'],
                'calendar_out' => $outbound['calendar'],
                'tasks_out' => $outbound['tasks'],
                'contacts_in' => $inbound['contacts'] ? __('app.webdav_sync_all_yes') : __('app.webdav_sync_all_no'),
                'calendar_in' => $inbound['calendar'] ? __('app.webdav_sync_all_yes') : __('app.webdav_sync_all_no'),
                'tasks_in' => $inbound['tasks'] ? __('app.webdav_sync_all_yes') : __('app.webdav_sync_all_no'),
            ]));
    }
}
