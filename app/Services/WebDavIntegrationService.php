<?php

namespace App\Services;

use App\Enums\ExternalProvider;
use App\Models\ExternalAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class WebDavIntegrationService
{
    public function __construct(private readonly WebDavApiClient $webDavApiClient) {}

    public function webDavAccountForTeam(Team $team): ?ExternalAccount
    {
        return $team->externalAccounts()
            ->where('provider', ExternalProvider::WebDav)
            ->latest('id')
            ->first();
    }

    /**
     * @return array{account: ExternalAccount, payload: array<string, mixed>}
     */
    public function createAccount(User $user, Team $team, string $email, string $name, ?string $davUsername = null, ?string $password = null): array
    {
        if ($this->webDavAccountForTeam($team) !== null)
        {
            throw new \RuntimeException('This team already has a WebDAV account linked.');
        }

        $payload = $this->webDavApiClient->createUser($email, $name, $davUsername, $password);

        return [
            'account' => $this->storeAccount($user, $team, $payload, $password ?? ($payload['password'] ?? null), true),
            'payload' => $payload,
        ];
    }

    /**
     * @return array{account: ExternalAccount, payload: array<string, mixed>}
     */
    public function linkAccount(User $user, Team $team, string $email, string $password): array
    {
        if ($this->webDavAccountForTeam($team) !== null)
        {
            throw new \RuntimeException('This team already has a WebDAV account linked.');
        }

        $payload = $this->webDavApiClient->linkUser($email, $password);

        return [
            'account' => $this->storeAccount($user, $team, $payload, $password, false),
            'payload' => $payload,
        ];
    }

    public function disconnect(Team $team): void
    {
        $team->externalAccounts()
            ->where('provider', ExternalProvider::WebDav)
            ->delete();
    }

    public function davEmail(ExternalAccount $account): string
    {
        return (string) ($account->provider_user_id ?: data_get($account->provider_metadata, 'email'));
    }

    public function davPassword(ExternalAccount $account): string
    {
        if ($account->access_token === null || $account->access_token === '')
        {
            throw new \RuntimeException('WebDAV credentials are missing for this account.');
        }

        return Crypt::decryptString($account->access_token);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeAccount(User $user, Team $team, array $payload, ?string $plainPassword, bool $created): ExternalAccount
    {
        if ($plainPassword === null || $plainPassword === '')
        {
            throw new \RuntimeException('WebDAV password is required to store the account.');
        }

        return DB::transaction(function () use ($user, $team, $payload, $plainPassword, $created)
        {
            return ExternalAccount::query()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'provider' => ExternalProvider::WebDav,
                ],
                [
                    'user_id' => $user->id,
                    'provider_user_id' => (string) $payload['email'],
                    'access_token' => Crypt::encryptString($plainPassword),
                    'refresh_token' => null,
                    'access_token_expires_at' => null,
                    'scopes' => null,
                    'provider_metadata' => [
                        'email' => $payload['email'],
                        'dav_username' => $payload['dav_username'] ?? null,
                        'principal' => $payload['principal'] ?? null,
                        'dav_url' => $payload['dav_url'] ?? null,
                        'created_by_humano' => $created,
                        'linked' => ! $created,
                    ],
                ],
            );
        });
    }
}
