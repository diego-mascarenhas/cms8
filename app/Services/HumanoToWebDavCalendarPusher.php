<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventSyncMapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HumanoToWebDavCalendarPusher
{
    public function __construct(
        private readonly WebDavApiClient $webDavApiClient,
        private readonly WebDavIntegrationService $webDavIntegrationService,
        private readonly WebDavTeamExternalAccountResolver $accountResolver,
    ) {}

    public function sync(CalendarEvent $event, string $action = 'updated'): void
    {
        $account = $this->accountResolver->firstWebDavAccountForTeam((int) $event->team_id);

        if ($account === null)
        {
            return;
        }

        $email = $this->webDavIntegrationService->davEmail($account);
        $mapping = CalendarEventSyncMapping::query()
            ->where('external_account_id', $account->id)
            ->where('calendar_event_id', $event->id)
            ->first();

        if ($action === 'deleted')
        {
            if ($mapping === null)
            {
                return;
            }

            try
            {
                $this->webDavApiClient->deleteEvent($email, (string) $mapping->external_id);
                $mapping->delete();
            } catch (\Throwable $exception)
            {
                Log::warning('HumanoToWebDavCalendarPusher delete failed.', [
                    'calendar_event_id' => $event->id,
                    'message' => $exception->getMessage(),
                ]);
            }

            return;
        }

        $payload = [
            'summary' => (string) ($event->title ?: 'Event'),
            'description' => $event->notes,
            'location' => $event->location,
            'starts_at' => $event->start?->toIso8601String(),
            'ends_at' => $event->end?->toIso8601String(),
            'all_day' => (bool) $event->all_day,
        ];

        try
        {
            if ($mapping === null)
            {
                $uid = (string) Str::uuid();
                $result = $this->webDavApiClient->upsertEvent($email, array_merge($payload, ['uid' => $uid]));
                $externalId = (string) ($result['uid'] ?? $uid);

                CalendarEventSyncMapping::query()->create([
                    'external_account_id' => $account->id,
                    'calendar_event_id' => $event->id,
                    'external_id' => $externalId,
                    'last_synced_at' => now(),
                ]);

                return;
            }

            $this->webDavApiClient->upsertEvent($email, $payload, (string) $mapping->external_id);
            $mapping->forceFill(['last_synced_at' => now()])->save();
        } catch (\Throwable $exception)
        {
            Log::warning('HumanoToWebDavCalendarPusher failed.', [
                'calendar_event_id' => $event->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
