<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactSyncMapping;
use App\Models\ExternalAccount;
use App\Support\GoogleIntegrationScopes;
use Google\Service\PeopleService;
use Google\Service\PeopleService\EmailAddress;
use Google\Service\PeopleService\Name;
use Google\Service\PeopleService\Person;
use Google\Service\PeopleService\PhoneNumber;
use Illuminate\Support\Facades\Log;

class HumanoToGoogleContactPusher
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuthService,
        private readonly GoogleTeamExternalAccountResolver $accountResolver,
    ) {}

    public function sync(Contact $contact): void
    {
        if ($contact->trashed())
        {
            $this->deleteAllRemoteCopies($contact);

            return;
        }

        $mappings = ContactSyncMapping::query()->where('contact_id', $contact->id)->get();

        if ($mappings->isEmpty())
        {
            $account = $this->accountResolver->firstGoogleAccountForTeam((int) $contact->team_id);
            if ($account === null)
            {
                return;
            }

            $this->createRemoteContact($contact, $account);

            return;
        }

        foreach ($mappings as $mapping)
        {
            $account = ExternalAccount::query()->find($mapping->external_account_id);
            if ($account === null)
            {
                continue;
            }

            $service = new PeopleService($this->googleOAuthService->buildApiClient($account, GoogleIntegrationScopes::contactsForApiClient()));

            try
            {
                $this->updateRemoteContact($contact, $service, (string) $mapping->external_id);
                $mapping->forceFill(['last_synced_at' => now()])->save();
            } catch (\Throwable $e)
            {
                Log::warning('HumanoToGoogleContactPusher: update failed.', [
                    'contact_id' => $contact->id,
                    'external_id' => $mapping->external_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function createRemoteContact(Contact $contact, ExternalAccount $account): void
    {
        $service = new PeopleService($this->googleOAuthService->buildApiClient($account, GoogleIntegrationScopes::contactsForApiClient()));

        $person = new Person;
        $person->setNames([
            new Name([
                'givenName' => (string) ($contact->name ?: 'Contact'),
                'familyName' => $contact->surname !== null && $contact->surname !== '' ? (string) $contact->surname : null,
            ]),
        ]);

        if ($contact->email !== null && $contact->email !== '')
        {
            $person->setEmailAddresses([
                new EmailAddress(['value' => (string) $contact->email]),
            ]);
        }

        if ($contact->phone !== null && (string) $contact->phone !== '')
        {
            $digits = preg_replace('/\D+/', '', (string) $contact->phone) ?: (string) $contact->phone;
            $person->setPhoneNumbers([
                new PhoneNumber(['value' => $digits]),
            ]);
        }

        $created = $service->people->createContact($person, [
            'personFields' => 'names,emailAddresses,phoneNumbers,metadata',
        ]);

        $resourceName = (string) $created->getResourceName();

        ContactSyncMapping::query()->updateOrCreate(
            [
                'external_account_id' => $account->id,
                'contact_id' => $contact->id,
            ],
            [
                'external_id' => $resourceName,
                'last_synced_at' => now(),
            ],
        );
    }

    private function updateRemoteContact(Contact $contact, PeopleService $service, string $resourceName): void
    {
        $existing = $service->people->get($resourceName, [
            'personFields' => 'names,emailAddresses,phoneNumbers,metadata,etag',
        ]);

        $person = new Person;
        $person->setEtag((string) $existing->getEtag());
        $person->setNames([
            new Name([
                'givenName' => (string) ($contact->name ?: 'Contact'),
                'familyName' => $contact->surname !== null && $contact->surname !== '' ? (string) $contact->surname : null,
            ]),
        ]);

        $updateFields = ['names'];

        if ($contact->email !== null && $contact->email !== '')
        {
            $person->setEmailAddresses([
                new EmailAddress(['value' => (string) $contact->email]),
            ]);
            $updateFields[] = 'emailAddresses';
        }

        if ($contact->phone !== null && (string) $contact->phone !== '')
        {
            $digits = preg_replace('/\D+/', '', (string) $contact->phone) ?: (string) $contact->phone;
            $person->setPhoneNumbers([
                new PhoneNumber(['value' => $digits]),
            ]);
            $updateFields[] = 'phoneNumbers';
        }

        $service->people->updateContact($resourceName, $person, [
            'updatePersonFields' => implode(',', $updateFields),
        ]);
    }

    private function deleteAllRemoteCopies(Contact $contact): void
    {
        $mappings = ContactSyncMapping::query()->where('contact_id', $contact->id)->get();

        foreach ($mappings as $mapping)
        {
            $account = ExternalAccount::query()->find($mapping->external_account_id);
            if ($account === null)
            {
                $mapping->delete();

                continue;
            }

            $service = new PeopleService($this->googleOAuthService->buildApiClient($account, GoogleIntegrationScopes::contactsForApiClient()));

            try
            {
                $service->people->deleteContact((string) $mapping->external_id);
            } catch (\Throwable $e)
            {
                Log::warning('HumanoToGoogleContactPusher: failed to delete remote contact.', [
                    'contact_id' => $contact->id,
                    'external_id' => $mapping->external_id,
                    'message' => $e->getMessage(),
                ]);
            }

            $mapping->delete();
        }
    }
}
