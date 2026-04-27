<?php

namespace App\Sync\Providers;

use App\Enums\SyncResource;
use App\Models\Contact;
use App\Models\ContactSyncMapping;
use App\Models\ExternalAccount;
use App\Models\SyncCursor;
use App\Services\GoogleOAuthService;
use App\Support\GoogleIntegrationScopes;
use App\Sync\Contracts\ContactSyncProviderInterface;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\PeopleService;

class GoogleContactSyncProvider implements ContactSyncProviderInterface
{
    public function __construct(private readonly GoogleOAuthService $googleOAuthService) {}

    public function sync(ExternalAccount $account): array
    {
        $cursor = SyncCursor::query()->firstOrCreate(
            [
                'external_account_id' => $account->id,
                'resource' => SyncResource::Contacts,
            ],
        );

        $service = new PeopleService($this->googleOAuthService->buildApiClient($account, GoogleIntegrationScopes::contactsForApiClient()));

        $baseParams = [
            'personFields' => 'names,emailAddresses,phoneNumbers,metadata',
            'pageSize' => 1000,
        ];

        $pulled = 0;
        $upserted = 0;
        $deleted = 0;
        $nextSyncToken = null;
        $pageToken = null;

        $isIncremental = $cursor->cursor !== null;

        try
        {
            do
            {
                $params = $baseParams;

                if ($isIncremental)
                {
                    $params['syncToken'] = $cursor->cursor;
                } else
                {
                    $params['requestSyncToken'] = true;
                }

                if ($pageToken !== null)
                {
                    $params['pageToken'] = $pageToken;
                }

                $response = $service->people_connections->listPeopleConnections('people/me', $params);
                $connections = $response->getConnections() ?? [];

                foreach ($connections as $person)
                {
                    $pulled++;

                    $personData = (array) $person->toSimpleObject();
                    $externalId = (string) $person->getResourceName();
                    $isDeleted = (bool) data_get($personData, 'metadata.deleted', false);
                    $mapping = ContactSyncMapping::query()
                        ->where('external_account_id', $account->id)
                        ->where('external_id', $externalId)
                        ->first();

                    if ($isDeleted)
                    {
                        if ($mapping !== null)
                        {
                            $mapping->contact?->deleteQuietly();
                            $deleted++;
                        }

                        continue;
                    }

                    $namePayload = (array) data_get($personData, 'names.0', []);
                    $emailPayload = (array) data_get($personData, 'emailAddresses.0', []);
                    $phonePayload = (array) data_get($personData, 'phoneNumbers.0', []);

                    $contact = $mapping?->contact;

                    if ($contact === null && ! empty($emailPayload['value']))
                    {
                        $contact = Contact::query()
                            ->where('team_id', $account->team_id)
                            ->where('email', (string) $emailPayload['value'])
                            ->first();
                    }

                    if ($contact === null)
                    {
                        $contact = new Contact;
                        $contact->team_id = $account->team_id;
                        $contact->creator_id = $account->user_id;
                        $contact->responsible_id = $account->user_id;
                    }

                    $contact->name = (string) ($namePayload['givenName'] ?? $namePayload['displayName'] ?? 'Google Contact');
                    $contact->surname = $namePayload['familyName'] ?? null;
                    $contact->email = $emailPayload['value'] ?? null;
                    $contact->phone = isset($phonePayload['canonicalForm']) ? preg_replace('/[^0-9]/', '', (string) $phonePayload['canonicalForm']) : null;
                    $contact->saveQuietly();

                    ContactSyncMapping::query()->updateOrCreate(
                        [
                            'external_account_id' => $account->id,
                            'external_id' => $externalId,
                        ],
                        [
                            'contact_id' => $contact->id,
                            'last_synced_at' => now(),
                        ],
                    );

                    $upserted++;
                }

                $pageToken = $response->getNextPageToken();
                $nextSyncToken = $response->getNextSyncToken() ?: $nextSyncToken;
            } while ($pageToken !== null);
        } catch (GoogleServiceException $exception)
        {
            // Google sync tokens expire in 7 days. Force full sync when stale.
            if ($isIncremental && $exception->getCode() === 410)
            {
                $cursor->forceFill([
                    'cursor' => null,
                ])->save();

                return $this->sync($account);
            }

            throw $exception;
        }

        $cursor->forceFill([
            'cursor' => $nextSyncToken,
            'full_synced_at' => $isIncremental ? $cursor->full_synced_at : now(),
        ])->save();

        return [
            'pulled_count' => $pulled,
            'upserted_count' => $upserted,
            'deleted_count' => $deleted,
        ];
    }
}
