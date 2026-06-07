<?php

namespace App\Jobs;

use App\Models\CalendarEvent;
use App\Models\Team;
use App\Services\HumanoToWebDavCalendarPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushCalendarEventToWebDavJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $calendarEventId,
        private readonly string $action = 'updated',
    ) {}

    public function handle(HumanoToWebDavCalendarPusher $pusher): void
    {
        $event = CalendarEvent::query()->find($this->calendarEventId);

        if ($event === null && $this->action !== 'deleted')
        {
            return;
        }

        $teamId = $event?->team_id;

        if ($teamId === null && $this->action === 'deleted')
        {
            return;
        }

        $team = Team::query()->find($teamId);

        if ($team === null || ! $team->webdavCalendarOutboundSyncEnabled())
        {
            return;
        }

        if ($event === null)
        {
            return;
        }

        try
        {
            $pusher->sync($event, $this->action);
        } catch (\Throwable $exception)
        {
            Log::warning('PushCalendarEventToWebDavJob failed (non-fatal).', [
                'calendar_event_id' => $this->calendarEventId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
