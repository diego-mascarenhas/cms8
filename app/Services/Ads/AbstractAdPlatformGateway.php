<?php

namespace App\Services\Ads;

use App\Contracts\AdPlatformGateway;
use App\Enums\AdConnectionStatus;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdAudience;
use App\Models\PaidAdCampaignPlatform;
use App\Models\Team;
use App\Models\User;
use App\Support\AdPlatformCredentials;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class AbstractAdPlatformGateway implements AdPlatformGateway
{
    /**
     * Team whose credentials should be used. Defaults to the authenticated team.
     */
    protected ?Team $team = null;

    /**
     * Config prefix under config('services.*'), used for the OAuth redirect URI.
     */
    abstract protected function configKey(): string;

    /**
     * Set the team context whose credentials should be used (e.g. from a queued job).
     */
    public function forTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    protected function team(): ?Team
    {
        return $this->team ?? auth()->user()?->currentTeam;
    }

    /**
     * OAuth authorization endpoint.
     */
    abstract protected function authorizeEndpoint(): string;

    /**
     * OAuth token endpoint.
     */
    abstract protected function tokenEndpoint(): string;

    /**
     * OAuth scopes requested during authorization.
     *
     * @return array<int, string>
     */
    abstract protected function scopes(): array;

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function buildAuthorizationUrl(User $user): string
    {
        $params = array_merge([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes()),
            'state' => $this->buildState($user),
        ], $this->extraAuthorizationParams());

        return $this->authorizeEndpoint().'?'.http_build_query($params);
    }

    public function exchangeCode(User $user, string $authCode): AdPlatformConnection
    {
        $response = Http::asForm()->post($this->tokenEndpoint(), array_merge([
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ], $this->extraTokenParams()));

        if ($response->failed())
        {
            throw new RuntimeException($this->platform()->value.' token exchange failed: '.$response->body());
        }

        $token = $response->json();

        return AdPlatformConnection::query()->updateOrCreate(
            [
                'team_id' => $user->currentTeam?->id,
                'platform' => $this->platform(),
                'ad_account_id' => null,
            ],
            [
                'user_id' => $user->id,
                'access_token' => Arr::get($token, 'access_token'),
                'refresh_token' => Arr::get($token, 'refresh_token'),
                'access_token_expires_at' => $this->expiryFromToken($token),
                'scopes' => $this->scopes(),
                'status' => AdConnectionStatus::PendingAccount,
                'metadata' => $this->metadataFromToken($token),
            ],
        );
    }

    public function refreshToken(AdPlatformConnection $connection): void
    {
        $this->forTeam($connection->team);

        if (($connection->refresh_token ?? '') === '')
        {
            throw new RuntimeException($this->platform()->value.' refresh token is missing.');
        }

        $response = Http::asForm()->post($this->tokenEndpoint(), [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed())
        {
            $connection->forceFill(['status' => AdConnectionStatus::Expired])->save();

            throw new RuntimeException($this->platform()->value.' token refresh failed: '.$response->body());
        }

        $token = $response->json();

        $connection->forceFill([
            'access_token' => Arr::get($token, 'access_token', $connection->access_token),
            'refresh_token' => Arr::get($token, 'refresh_token', $connection->refresh_token),
            'access_token_expires_at' => $this->expiryFromToken($token),
        ])->save();
    }

    public function syncAudience(AdPlatformConnection $connection, PaidAdAudience $audience): string
    {
        // Default: platforms without a dedicated audience API return a local reference.
        return 'local:'.$audience->id;
    }

    public function pause(PaidAdCampaignPlatform $campaignPlatform): void
    {
        // Optional per-platform override.
    }

    public function resume(PaidAdCampaignPlatform $campaignPlatform): void
    {
        // Optional per-platform override.
    }

    /**
     * @return array<int, \App\Services\Ads\DTO\AdMetricsDTO>
     */
    public function getMetrics(PaidAdCampaignPlatform $campaignPlatform, CarbonInterface $from, CarbonInterface $to): array
    {
        return [];
    }

    /**
     * Authenticated HTTP client with a valid (refreshed) bearer token.
     */
    protected function authorizedClient(AdPlatformConnection $connection): PendingRequest
    {
        $this->forTeam($connection->team);

        if ($connection->isTokenExpired())
        {
            $this->refreshToken($connection);
            $connection->refresh();
        }

        return Http::withToken((string) $connection->access_token)->acceptJson();
    }

    protected function clientId(): string
    {
        return (string) (AdPlatformCredentials::get($this->platform(), 'client_id', $this->team()) ?? '');
    }

    protected function clientSecret(): string
    {
        return (string) (AdPlatformCredentials::get($this->platform(), 'client_secret', $this->team()) ?? '');
    }

    protected function credential(string $field): ?string
    {
        return AdPlatformCredentials::get($this->platform(), $field, $this->team());
    }

    protected function redirectUri(): string
    {
        return (string) config('services.'.$this->configKey().'.redirect');
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraAuthorizationParams(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraTokenParams(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    protected function metadataFromToken(array $token): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $token
     */
    protected function expiryFromToken(array $token): ?CarbonInterface
    {
        $expiresIn = Arr::get($token, 'expires_in');

        if (! is_numeric($expiresIn))
        {
            return null;
        }

        return now()->addSeconds((int) $expiresIn);
    }

    protected function buildState(User $user): string
    {
        return encrypt(json_encode([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam?->id,
            'platform' => $this->platform()->value,
            'nonce' => (string) str()->uuid(),
        ]));
    }
}
