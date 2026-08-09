<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Models\AdPlatformConnection;
use App\Services\Ads\AdPlatformGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AdPlatformConnectionController extends Controller
{
    use ChecksTeamModule;

    public function __construct(private readonly AdPlatformGatewayFactory $gateways) {}

    public function index(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'paid_ads'))
        {
            return $denied;
        }

        $connections = AdPlatformConnection::query()
            ->where('team_id', $team->id)
            ->with('user:id,name')
            ->orderBy('platform')
            ->get()
            ->keyBy(fn (AdPlatformConnection $connection) => $connection->platform->value);

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
                'platform' => $platform->value,
                'label' => $platform->label(),
                'color' => $platform->color(),
                'enabled' => $platform->isEnabled(),
                'configured' => $gateway->isConfigured(),
                'connection' => $connection ? $this->formatConnection($connection) : null,
                'accounts' => $accounts,
            ];
        }, AdPlatform::cases());

        return response()->json([
            'success' => true,
            'data' => $platforms,
        ]);
    }

    public function authorizeUrl(Request $request, string $platform): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'paid_ads'))
        {
            return $denied;
        }

        $adPlatform = AdPlatform::tryFrom($platform);
        if ($adPlatform === null || ! $adPlatform->isEnabled())
        {
            return response()->json([
                'success' => false,
                'message' => __('This platform is not available.'),
            ], 422);
        }

        $gateway = $this->gateways->make($adPlatform)->forTeam($team);
        if (! $gateway->isConfigured())
        {
            return response()->json([
                'success' => false,
                'message' => __(':platform is not configured. Add the API credentials in the team settings to continue.', [
                    'platform' => $adPlatform->label(),
                ]),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'authorization_url' => $gateway->buildAuthorizationUrl($request->user()),
            ],
        ]);
    }

    public function selectAccount(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'paid_ads'))
        {
            return $denied;
        }

        $connection = AdPlatformConnection::query()
            ->where('team_id', $team->id)
            ->find($id);

        if (! $connection)
        {
            return response()->json([
                'success' => false,
                'message' => __('Connection not found'),
            ], 404);
        }

        $validated = $request->validate([
            'ad_account_id' => ['required', 'string', 'max:191'],
            'ad_account_name' => ['nullable', 'string', 'max:191'],
        ]);

        $connection->forceFill([
            'ad_account_id' => $validated['ad_account_id'],
            'ad_account_name' => $validated['ad_account_name'] ?? $validated['ad_account_id'],
            'status' => AdConnectionStatus::Active,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => __('Ad account selected. The platform is ready to publish.'),
            'data' => $this->formatConnection($connection->fresh('user:id,name')),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'paid_ads'))
        {
            return $denied;
        }

        $connection = AdPlatformConnection::query()
            ->where('team_id', $team->id)
            ->find($id);

        if (! $connection)
        {
            return response()->json([
                'success' => false,
                'message' => __('Connection not found'),
            ], 404);
        }

        $connection->delete();

        return response()->json([
            'success' => true,
            'message' => __('Connection removed.'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accountsFor(AdPlatformConnection $connection): array
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

    /**
     * @return array<string, mixed>
     */
    private function formatConnection(AdPlatformConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'platform' => $connection->platform->value,
            'ad_account_id' => $connection->ad_account_id,
            'ad_account_name' => $connection->ad_account_name,
            'status' => $connection->status->value,
            'last_synced_at' => optional($connection->last_synced_at)?->toIso8601String(),
            'user' => $connection->user
                ? ['id' => $connection->user->id, 'name' => $connection->user->name]
                : null,
        ];
    }
}
