<?php

namespace App\Sync\Providers;

use App\Enums\SyncResource;
use App\Models\CalendarEvent;
use App\Models\CalendarEventSyncMapping;
use App\Models\ExternalAccount;
use App\Services\WebDavApiClient;
use App\Services\WebDavIntegrationService;
use App\Sync\Contracts\CalendarSyncProviderInterface;
use Carbon\CarbonImmutable;

class WebDavCalendarSyncProvider implements CalendarSyncProviderInterface
{
    public function __construct(
        private readonly WebDavIntegrationService $webDavIntegrationService,
        private readonly WebDavApiClient $webDavApiClient,
    ) {}

    public function sync(ExternalAccount $account): array
    {
        $email = $this->webDavIntegrationService->davEmail($account);
        $items = $this->webDavApiClient->listEvents($email);

        $pulled = 0;
        $upserted = 0;

        foreach ($items as $item)
        {
            $pulled++;
            $externalId = (string) ($item['uid'] ?? '');

            if ($externalId === '' || empty($item['starts_at']))
            {
                continue;
            }

            $mapping = CalendarEventSyncMapping::query()
                ->where('external_account_id', $account->id)
                ->where('external_id', $externalId)
                ->first();

            $calendarEvent = $mapping?->calendarEvent;

            if ($calendarEvent === null)
            {
                $calendarEvent = new CalendarEvent;
                $calendarEvent->team_id = $account->team_id;
            }

            $calendarEvent->title = (string) ($item['summary'] ?? 'WebDAV Event');
            $calendarEvent->start = CarbonImmutable::parse($item['starts_at']);
            $calendarEvent->end = isset($item['ends_at'])
                ? CarbonImmutable::parse($item['ends_at'])
                : $calendarEvent->start->addHour();
            $calendarEvent->all_day = (bool) ($item['all_day'] ?? false);
            $calendarEvent->notes = $item['description'] ?? null;
            $calendarEvent->location = $item['location'] ?? null;
            $calendarEvent->saveQuietly();

            CalendarEventSyncMapping::query()->updateOrCreate(
                [
                    'external_account_id' => $account->id,
                    'external_id' => $externalId,
                ],
                [
                    'calendar_event_id' => $calendarEvent->id,
                    'last_synced_at' => now(),
                ],
            );

            $upserted++;
        }

        return [
            'pulled_count' => $pulled,
            'upserted_count' => $upserted,
            'deleted_count' => 0,
            'resource' => SyncResource::CalendarEvents->value,
        ];
    }
}
