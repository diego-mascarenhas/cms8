<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactSyncMapping;
use App\Models\ExternalAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HumanoToWebDavContactPusher
{
    public function __construct(
        private readonly WebDavApiClient $webDavApiClient,
        private readonly WebDavIntegrationService $webDavIntegrationService,
        private readonly WebDavTeamExternalAccountResolver $accountResolver,
    ) {}

    public function sync(Contact $contact): void
    {
        if ($contact->trashed())
        {
            $this->deleteRemoteCopies($contact);

            return;
        }

        $account = $this->accountResolver->firstWebDavAccountForTeam((int) $contact->team_id);

        if ($account === null)
        {
            return;
        }

        $email = $this->webDavIntegrationService->davEmail($account);
        $mapping = ContactSyncMapping::query()
            ->where('external_account_id', $account->id)
            ->where('contact_id', $contact->id)
            ->first();

        $payload = [
            'name' => (string) ($contact->name ?: 'Contact'),
            'surname' => $contact->surname,
            'email' => $contact->email,
            'phone' => $contact->phone,
        ];

        try
        {
            if ($mapping === null)
            {
                $uid = (string) Str::uuid();
                $result = $this->webDavApiClient->upsertContact($email, array_merge($payload, ['uid' => $uid]));
                $externalId = (string) ($result['uid'] ?? $uid);

                ContactSyncMapping::query()->create([
                    'external_account_id' => $account->id,
                    'contact_id' => $contact->id,
                    'external_id' => $externalId,
                    'last_synced_at' => now(),
                ]);

                $data = (array) ($contact->data ?? []);
                $data['webdav_uid'] = $externalId;
                $contact->forceFill(['data' => (object) $data])->saveQuietly();

                return;
            }

            $this->webDavApiClient->upsertContact($email, $payload, (string) $mapping->external_id);
            $mapping->forceFill(['last_synced_at' => now()])->save();
        } catch (\Throwable $exception)
        {
            Log::warning('HumanoToWebDavContactPusher failed.', [
                'contact_id' => $contact->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function deleteRemoteCopies(Contact $contact): void
    {
        $mappings = ContactSyncMapping::query()->where('contact_id', $contact->id)->get();

        foreach ($mappings as $mapping)
        {
            $account = ExternalAccount::query()->find($mapping->external_account_id);

            if ($account === null)
            {
                continue;
            }

            try
            {
                $email = $this->webDavIntegrationService->davEmail($account);
                $this->webDavApiClient->deleteContact($email, (string) $mapping->external_id);
                $mapping->delete();
            } catch (\Throwable $exception)
            {
                Log::warning('HumanoToWebDavContactPusher delete failed.', [
                    'contact_id' => $contact->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
