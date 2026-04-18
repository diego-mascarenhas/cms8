<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventSyncMapping;
use App\Models\ExternalAccount;
use App\Support\GoogleIntegrationScopes;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;

class HumanoToGoogleCalendarPusher
{
    private const CALENDAR_ID = 'primary';

    public function __construct(
        private readonly GoogleOAuthService $googleOAuthService,
    ) {}

    /**
     * Push local state to Google Calendar (insert, update, or delete).
     */
    public function sync(CalendarEvent $event, ExternalAccount $account): void
    {
        $client = $this->googleOAuthService->buildApiClient($account, GoogleIntegrationScopes::calendarForApiClient());
        $calendar = new Calendar($client);

        if ($event->trashed())
        {
            $this->deleteRemoteIfMapped($event, $account, $calendar);

            return;
        }

        $body = $this->toGoogleEvent($event);

        if ($event->google_event_id !== null && $event->google_event_id !== '')
        {
            $calendar->events->update(self::CALENDAR_ID, (string) $event->google_event_id, $body);
            $this->touchMapping($account, $event);

            return;
        }

        $created = $calendar->events->insert(self::CALENDAR_ID, $body);
        $remoteId = (string) $created->getId();

        $event->forceFill(['google_event_id' => $remoteId])->saveQuietly();

        CalendarEventSyncMapping::query()->updateOrCreate(
            [
                'external_account_id' => $account->id,
                'calendar_event_id' => $event->id,
            ],
            [
                'external_id' => $remoteId,
                'last_synced_at' => now(),
            ],
        );
    }

    private function deleteRemoteIfMapped(CalendarEvent $event, ExternalAccount $account, Calendar $calendar): void
    {
        if ($event->google_event_id === null || $event->google_event_id === '')
        {
            return;
        }

        try
        {
            $calendar->events->delete(self::CALENDAR_ID, (string) $event->google_event_id);
        } catch (\Throwable $e)
        {
            Log::warning('HumanoToGoogleCalendarPusher: failed to delete remote event.', [
                'calendar_event_id' => $event->id,
                'google_event_id' => $event->google_event_id,
                'message' => $e->getMessage(),
            ]);
        }

        CalendarEventSyncMapping::query()
            ->where('external_account_id', $account->id)
            ->where('calendar_event_id', $event->id)
            ->delete();
    }

    private function touchMapping(ExternalAccount $account, CalendarEvent $event): void
    {
        CalendarEventSyncMapping::query()->updateOrCreate(
            [
                'external_account_id' => $account->id,
                'calendar_event_id' => $event->id,
            ],
            [
                'external_id' => (string) $event->google_event_id,
                'last_synced_at' => now(),
            ],
        );
    }

    private function toGoogleEvent(CalendarEvent $event): Event
    {
        $g = new Event;
        $g->setSummary((string) $event->title);

        if ($event->notes !== null && $event->notes !== '')
        {
            $g->setDescription((string) $event->notes);
        }

        if ($event->location !== null && $event->location !== '')
        {
            $g->setLocation((string) $event->location);
        }

        if ($event->all_day)
        {
            $tz = (string) config('app.timezone');
            $startCarbon = $event->start instanceof CarbonInterface
                ? $event->start->copy()->timezone($tz)->startOfDay()
                : Carbon::parse((string) $event->start)->timezone($tz)->startOfDay();
            $startDay = $startCarbon->format('Y-m-d');
            $endLocal = $event->end instanceof CarbonInterface
                ? $event->end->copy()->timezone($tz)->startOfDay()
                : $startCarbon->copy();
            $endExclusive = $endLocal->copy()->addDay()->format('Y-m-d');

            $g->setStart(new EventDateTime(['date' => $startDay]));
            $g->setEnd(new EventDateTime(['date' => $endExclusive]));
        } else
        {
            $tz = (string) config('app.timezone');
            $start = $event->start instanceof CarbonInterface ? $event->start->copy()->timezone($tz) : $event->start;
            $end = $event->end instanceof CarbonInterface ? $event->end->copy()->timezone($tz) : $event->end;

            $g->setStart(new EventDateTime([
                'dateTime' => $start->toRfc3339String(),
                'timeZone' => $tz,
            ]));
            $g->setEnd(new EventDateTime([
                'dateTime' => $end->toRfc3339String(),
                'timeZone' => $tz,
            ]));
        }

        return $g;
    }
}
