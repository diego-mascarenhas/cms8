<?php

namespace App\Sync\Providers;

use App\Enums\SyncResource;
use App\Models\CalendarEvent;
use App\Models\CalendarEventSyncMapping;
use App\Models\ExternalAccount;
use App\Models\SyncCursor;
use App\Services\GoogleOAuthService;
use App\Support\GoogleIntegrationScopes;
use App\Sync\Contracts\CalendarSyncProviderInterface;
use Carbon\CarbonImmutable;
use Google\Service\Calendar;
use Google\Service\Exception as GoogleServiceException;

class GoogleCalendarSyncProvider implements CalendarSyncProviderInterface
{
    public function __construct(private readonly GoogleOAuthService $googleOAuthService) {}

    public function sync(ExternalAccount $account): array
    {
        $cursor = SyncCursor::query()->firstOrCreate(
            [
                'external_account_id' => $account->id,
                'resource' => SyncResource::CalendarEvents,
            ],
        );

        $service = new Calendar($this->googleOAuthService->buildApiClient($account, GoogleIntegrationScopes::calendarForApiClient()));

        $pulled = 0;
        $upserted = 0;
        $deleted = 0;
        $nextSyncToken = null;
        $nextPageToken = null;
        $isIncremental = $cursor->cursor !== null;

        try
        {
            do
            {
                $params = [
                    'maxResults' => 2500,
                    'showDeleted' => true,
                    'singleEvents' => true,
                ];

                if ($isIncremental)
                {
                    $params['syncToken'] = $cursor->cursor;
                } else
                {
                    $params['timeMin'] = now()->subMonths(6)->toRfc3339String();
                }

                if ($nextPageToken !== null)
                {
                    $params['pageToken'] = $nextPageToken;
                }

                $response = $service->events->listEvents('primary', $params);
                $events = $response->getItems() ?? [];

                foreach ($events as $event)
                {
                    $pulled++;
                    $externalId = (string) $event->getId();
                    $mapping = CalendarEventSyncMapping::query()
                        ->where('external_account_id', $account->id)
                        ->where('external_id', $externalId)
                        ->first();

                    if ($event->getStatus() === 'cancelled')
                    {
                        if ($mapping !== null)
                        {
                            $mapping->calendarEvent?->delete();
                            $deleted++;
                        }

                        continue;
                    }

                    $startDateTime = $event->getStart()?->getDateTime();
                    $endDateTime = $event->getEnd()?->getDateTime();
                    $startDate = $event->getStart()?->getDate();
                    $endDate = $event->getEnd()?->getDate();
                    $allDay = $startDateTime === null && $startDate !== null;

                    $startsAt = $startDateTime !== null
                        ? CarbonImmutable::parse($startDateTime)
                        : CarbonImmutable::parse($startDate.' 00:00:00');

                    $endsAt = $endDateTime !== null
                        ? CarbonImmutable::parse($endDateTime)
                        : CarbonImmutable::parse($endDate.' 00:00:00');

                    $calendarEvent = $mapping?->calendarEvent;

                    if ($calendarEvent === null)
                    {
                        $calendarEvent = new CalendarEvent;
                        $calendarEvent->team_id = $account->team_id;
                    }

                    $calendarEvent->title = (string) ($event->getSummary() ?: 'Google Event');
                    $calendarEvent->start = $startsAt;
                    $calendarEvent->end = $endsAt;
                    $calendarEvent->all_day = $allDay;
                    $calendarEvent->notes = $event->getDescription();
                    $calendarEvent->location = $event->getLocation();
                    $calendarEvent->google_event_id = $externalId;
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

                $nextPageToken = $response->getNextPageToken();
                $nextSyncToken = $response->getNextSyncToken() ?: $nextSyncToken;
            } while ($nextPageToken !== null);
        } catch (GoogleServiceException $exception)
        {
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
