<?php

namespace App\Http\Controllers;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaign;
use App\Models\User;
use App\Services\Ads\AdPlatformGatewayFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class AdPlatformConnectionController extends Controller
{
    public function __construct(private readonly AdPlatformGatewayFactory $gateways) {}

    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', PaidAdCampaign::class);
        $this->ensureModule();

        $connections = AdPlatformConnection::query()
            ->with('user:id,name')
            ->orderBy('platform')
            ->get()
            ->keyBy(fn (AdPlatformConnection $c) => $c->platform->value);

        $team = auth()->user()->currentTeam;

        $platforms = array_map(function (AdPlatform $platform) use ($connections, $team): array
        {
            $gateway = $this->gateways->make($platform)->forTeam($team);
            $connection = $connections->get($platform->value);

            $accounts = [];
            if ($connection !== null && $connection->status === AdConnectionStatus::PendingAccount)
            {
                $accounts = $this->accountsFor($connection);
            }

            return [
                'platform' => $platform,
                'enabled' => $platform->isEnabled(),
                'configured' => $gateway->isConfigured(),
                'connection' => $connection,
                'accounts' => $accounts,
            ];
        }, AdPlatform::cases());

        return view('paid-ads.connections', compact('platforms'));
    }

    public function connect(string $platform): RedirectResponse
    {
        $this->authorize('create', PaidAdCampaign::class);
        $this->ensureModule();

        $adPlatform = AdPlatform::tryFrom($platform);
        if ($adPlatform === null || ! $adPlatform->isEnabled())
        {
            return redirect()->route('paid-ads.connections')->with('warning', __('This platform is not available.'));
        }

        $gateway = $this->gateways->make($adPlatform)->forTeam(auth()->user()->currentTeam);
        if (! $gateway->isConfigured())
        {
            return redirect()->route('paid-ads.connections')
                ->with('warning', __(':platform is not configured. Add the API credentials in the team settings to continue.', ['platform' => $adPlatform->label()]));
        }

        return redirect()->away($gateway->buildAuthorizationUrl(auth()->user()));
    }

    public function callback(string $platform, Request $request): RedirectResponse
    {
        $adPlatform = AdPlatform::tryFrom($platform);
        if ($adPlatform === null)
        {
            return $this->callbackRedirect($adPlatform, null, __('Unknown platform.'), true);
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $user = auth()->user();

        try
        {
            $gateway = $this->gateways->make($adPlatform);

            if ($user === null && ! empty($validated['state']))
            {
                $state = $gateway->parseState($validated['state']);
                $user = User::query()->find($state['user_id']);

                if ($user !== null && $state['team_id'])
                {
                    $user->forceFill(['current_team_id' => $state['team_id']])->save();
                }
            }

            if ($user === null || ! $user->currentTeam?->hasModule('paid_ads'))
            {
                return $this->callbackRedirect($adPlatform, null, __('Could not complete the connection. Sign in and try again.'), true);
            }

            $connection = $gateway->forTeam($user->currentTeam)->exchangeCode($user, $validated['code']);
        } catch (Throwable $e)
        {
            return $this->callbackRedirect(
                $adPlatform,
                null,
                __('Could not connect :platform: :error', ['platform' => $adPlatform->label(), 'error' => $e->getMessage()]),
                true,
            );
        }

        return $this->callbackRedirect(
            $adPlatform,
            $connection,
            __(':platform connected. Select the ad account to finish.', ['platform' => $adPlatform->label()]),
            false,
        );
    }

    private function callbackRedirect(?AdPlatform $platform, ?AdPlatformConnection $connection, string $message, bool $isError): RedirectResponse
    {
        $spaUrl = rtrim((string) config('services.paid_ads.spa_url', ''), '/');
        if ($spaUrl !== '' && $platform !== null)
        {
            $query = http_build_query(array_filter([
                $isError ? 'error' : 'connected' => $platform->value,
                'connection_id' => $connection?->id,
                'message' => $isError ? $message : null,
            ]));

            return redirect()->away($spaUrl.'/connections?'.$query);
        }

        $flashKey = $isError ? 'warning' : 'success';
        $redirect = redirect()->route('paid-ads.connections')->with($flashKey, $message);

        if ($connection !== null && ! $isError)
        {
            $redirect->with('select_account_connection', $connection->id);
        }

        return $redirect;
    }

    public function selectAccount(string $connection, Request $request): RedirectResponse
    {
        $this->authorize('create', PaidAdCampaign::class);
        $this->ensureModule();

        $model = AdPlatformConnection::query()->findOrFail($connection);

        $validated = $request->validate([
            'ad_account_id' => ['required', 'string', 'max:191'],
            'ad_account_name' => ['nullable', 'string', 'max:191'],
        ]);

        $model->forceFill([
            'ad_account_id' => $validated['ad_account_id'],
            'ad_account_name' => $validated['ad_account_name'] ?? $validated['ad_account_id'],
            'status' => AdConnectionStatus::Active,
        ])->save();

        return redirect()->route('paid-ads.connections')
            ->with('success', __('Ad account selected. The platform is ready to publish.'));
    }

    public function disconnect(string $connection): RedirectResponse
    {
        $this->authorize('create', PaidAdCampaign::class);
        $this->ensureModule();

        AdPlatformConnection::query()->findOrFail($connection)->delete();

        return redirect()->route('paid-ads.connections')->with('success', __('Connection removed.'));
    }

    /**
     * Fetch selectable ad accounts for a pending connection (used by the account selector UI).
     *
     * @return array<int, array<string, mixed>>
     */
    public function accountsFor(AdPlatformConnection $connection): array
    {
        try
        {
            $accounts = $this->gateways->make($connection->platform)->listAdAccounts($connection);
        } catch (Throwable)
        {
            return [];
        }

        return array_map(fn ($account) => $account->toArray(), $accounts);
    }

    private function ensureModule(): void
    {
        if (! auth()->user()?->currentTeam?->hasModule('paid_ads'))
        {
            abort(404);
        }
    }
}
