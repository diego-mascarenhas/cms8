<?php

namespace App\Jobs;

use App\Models\CalendarEvent;
use App\Services\GoogleTeamExternalAccountResolver;
use App\Services\HumanoToGoogleCalendarPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushCalendarEventToGoogleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $calendarEventId,
        public readonly string $action,
    ) {}

    public function handle(
        HumanoToGoogleCalendarPusher $pusher,
        GoogleTeamExternalAccountResolver $accountResolver,
    ): void {
        if ((string) config('services.google.client_id') === '')
        {
            return;
        }

        if (! in_array($this->action, ['created', 'updated', 'deleted'], true))
        {
            return;
        }

        $event = CalendarEvent::withTrashed()->find($this->calendarEventId);

        if ($event === null)
        {
            return;
        }

        $team = \App\Models\Team::query()->find($event->team_id);

        if ($team === null || ! $team->googleCalendarOutboundSyncEnabled())
        {
            return;
        }

        $account = $accountResolver->firstGoogleAccountForTeam((int) $event->team_id);

        if ($account === null)
        {
            return;
        }

        try
        {
            $pusher->sync($event, $account);
        } catch (\Throwable $e)
        {
            Log::warning('PushCalendarEventToGoogleJob failed (non-fatal).', [
                'calendar_event_id' => $this->calendarEventId,
                'action' => $this->action,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
