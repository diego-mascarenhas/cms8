<?php

namespace App\Services;

use App\Enums\ExternalProvider;
use App\Models\ExternalAccount;
use App\Models\User;
use Carbon\CarbonInterface;
use Google\Client;
use Google\Service\Oauth2;
use Illuminate\Support\Arr;
use RuntimeException;

class GoogleOAuthService
{
    public function buildAuthorizationUrl(User $user): string
    {
        $client = $this->makeBaseClient();
        $state = $this->buildState($user);

        $client->setScopes($this->scopes());
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->setPrompt('consent');
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function exchangeCode(User $user, string $authCode): ExternalAccount
    {
        $client = $this->makeBaseClient();
        $token = $client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($token['error']))
        {
            throw new RuntimeException('Google OAuth token exchange failed: '.json_encode($token));
        }

        $client->setAccessToken($token);
        $oauthService = new Oauth2($client);
        $userInfo = $oauthService->userinfo->get();
        $providerUserId = (string) $userInfo->getId();

        $account = ExternalAccount::query()->updateOrCreate(
            [
                'provider' => ExternalProvider::Google,
                'provider_user_id' => $providerUserId,
            ],
            [
                'team_id' => $user->currentTeam?->id,
                'user_id' => $user->id,
                'access_token' => Arr::get($token, 'access_token'),
                'refresh_token' => Arr::get($token, 'refresh_token'),
                'access_token_expires_at' => $this->calculateExpiry($token),
                'scopes' => Arr::get($token, 'scope') ? explode(' ', (string) Arr::get($token, 'scope')) : $this->scopes(),
                'provider_metadata' => [
                    'email' => $userInfo->getEmail(),
                    'name' => $userInfo->getName(),
                ],
            ],
        );

        return $account;
    }

    public function buildApiClient(ExternalAccount $account, array $scopes = []): Client
    {
        $client = $this->makeBaseClient();
        $client->setScopes($scopes === [] ? $this->scopes() : $scopes);
        $client->setAccessToken([
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'expires_in' => $account->access_token_expires_at?->diffInSeconds(now()) ?? 0,
            'created' => now()->timestamp,
        ]);

        if ($client->isAccessTokenExpired())
        {
            $this->refreshAccessToken($account);

            $client->setAccessToken([
                'access_token' => $account->access_token,
                'refresh_token' => $account->refresh_token,
                'expires_in' => $account->access_token_expires_at?->diffInSeconds(now()) ?? 0,
                'created' => now()->timestamp,
            ]);
        }

        return $client;
    }

    public function refreshAccessToken(ExternalAccount $account): void
    {
        if ($account->refresh_token === null || $account->refresh_token === '')
        {
            throw new RuntimeException('Google refresh token is missing.');
        }

        $client = $this->makeBaseClient();
        $newToken = $client->fetchAccessTokenWithRefreshToken($account->refresh_token);

        if (isset($newToken['error']))
        {
            throw new RuntimeException('Google access token refresh failed: '.json_encode($newToken));
        }

        $account->forceFill([
            'access_token' => Arr::get($newToken, 'access_token', $account->access_token),
            'refresh_token' => Arr::get($newToken, 'refresh_token', $account->refresh_token),
            'access_token_expires_at' => $this->calculateExpiry($newToken),
        ])->save();
    }

    public function scopes(): array
    {
        $configured = config('services.google.oauth_scopes', []);

        return is_array($configured) && $configured !== []
            ? $configured
            : [
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/contacts.readonly',
                'https://www.googleapis.com/auth/calendar.readonly',
            ];
    }

    protected function buildState(User $user): string
    {
        return encrypt(json_encode([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam?->id,
            'nonce' => (string) str()->uuid(),
        ]));
    }

    protected function makeBaseClient(): Client
    {
        $cid = (string) config('services.google.client_id');

        $client = new Client;
        $client->setClientId($cid);
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));

        return $client;
    }

    protected function calculateExpiry(array $token): ?CarbonInterface
    {
        $expiresIn = Arr::get($token, 'expires_in');

        if (! is_numeric($expiresIn))
        {
            return null;
        }

        return now()->addSeconds((int) $expiresIn);
    }
}
