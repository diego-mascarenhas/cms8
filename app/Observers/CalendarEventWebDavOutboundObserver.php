<?php

namespace App\Observers;

use App\Jobs\PushCalendarEventToWebDavJob;
use App\Models\CalendarEvent;
use App\Models\Team;

class CalendarEventWebDavOutboundObserver
{
    public function saved(CalendarEvent $event): void
    {
        $this->dispatchWhenEnabled($event, 'updated');
    }

    public function deleted(CalendarEvent $event): void
    {
        $this->dispatchWhenEnabled($event, 'deleted');
    }

    private function dispatchWhenEnabled(CalendarEvent $event, string $action): void
    {
        $team = $event->relationLoaded('team')
            ? $event->team
            : Team::query()->find($event->team_id);

        if ($team === null || ! $team->webdavCalendarOutboundSyncEnabled())
        {
            return;
        }

        PushCalendarEventToWebDavJob::dispatch($event->id, $action);
    }
}
