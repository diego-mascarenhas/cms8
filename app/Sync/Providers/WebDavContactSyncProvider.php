<?php

namespace App\Sync\Providers;

use App\Enums\SyncResource;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\ContactSyncMapping;
use App\Models\ExternalAccount;
use App\Services\WebDavIntegrationService;
use App\Sync\Contracts\ContactSyncProviderInterface;

class WebDavContactSyncProvider implements ContactSyncProviderInterface
{
    public function __construct(
        private readonly WebDavIntegrationService $webDavIntegrationService,
        private readonly \App\Services\WebDavApiClient $webDavApiClient,
    ) {}

    public function sync(ExternalAccount $account): array
    {
        $email = $this->webDavIntegrationService->davEmail($account);
        $items = $this->webDavApiClient->listContacts($email);

        $pulled = 0;
        $upserted = 0;
        $defaultStatusId = ContactStatus::query()->where('name', 'Lead')->value('id');

        foreach ($items as $item)
        {
            $pulled++;
            $externalId = (string) ($item['uid'] ?? '');

            if ($externalId === '')
            {
                continue;
            }

            $mapping = ContactSyncMapping::query()
                ->where('external_account_id', $account->id)
                ->where('external_id', $externalId)
                ->first();

            $contact = $mapping?->contact;

            if ($contact === null && ! empty($item['email']))
            {
                $contact = Contact::query()
                    ->where('team_id', $account->team_id)
                    ->where('email', (string) $item['email'])
                    ->first();
            }

            if ($contact === null)
            {
                $contact = new Contact;
                $contact->team_id = $account->team_id;
                $contact->creator_id = $account->user_id;
                $contact->responsible_id = $account->user_id;
                $contact->status_id = $defaultStatusId;
            }

            $contact->name = (string) ($item['name'] ?? $item['full_name'] ?? 'WebDAV Contact');
            $contact->surname = $item['surname'] ?? null;
            $contact->email = $item['email'] ?? null;
            $contact->phone = isset($item['phone']) ? preg_replace('/[^0-9+]/', '', (string) $item['phone']) : null;

            $data = (array) ($contact->data ?? []);
            $data['webdav_uid'] = $externalId;
            $contact->data = (object) $data;

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

        return [
            'pulled_count' => $pulled,
            'upserted_count' => $upserted,
            'deleted_count' => 0,
            'resource' => SyncResource::Contacts->value,
        ];
    }
}
