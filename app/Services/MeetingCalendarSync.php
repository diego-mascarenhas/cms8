<?php

namespace App\Services;

use App\Jobs\PushCalendarEventToGoogleJob;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\MeetingTranscript;
use Illuminate\Support\Carbon;

class MeetingCalendarSync
{
    public function syncTeam(int $teamId): void
    {
        MeetingTranscript::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->get()
            ->each(function (MeetingTranscript $meeting): void
            {
                $this->sync($meeting);
            });
    }

    public function sync(MeetingTranscript $meeting): void
    {
        [$start, $end] = $this->window($meeting);
        $payload = [
            'team_id' => $meeting->team_id,
            'title' => $meeting->title,
            'start' => $start,
            'end' => $end,
            'all_day' => false,
            'url' => route('meeting.show', $meeting),
            'label' => 'Business',
            'notes' => 'Reunión',
        ];

        $event = $meeting->calendar_event_id
            ? CalendarEvent::withoutGlobalScopes()->find($meeting->calendar_event_id)
            : null;

        $created = false;
        if (! $event instanceof CalendarEvent)
        {
            $event = CalendarEvent::withoutGlobalScopes()->create($payload);
            $meeting->forceFill(['calendar_event_id' => $event->id])->save();
            $created = true;
        } elseif (! $this->sameInstant($event->start, $start) || ! $this->sameInstant($event->end, $end) || $event->title !== $meeting->title)
        {
            $event->forceFill($payload)->save();
            PushCalendarEventToGoogleJob::dispatch($event->id, 'updated');
        }

        $this->syncVoiceGuests($event, $meeting);

        if ($created)
        {
            PushCalendarEventToGoogleJob::dispatch($event->id, 'created');
        }
    }

    public function forget(MeetingTranscript $meeting): void
    {
        if (! $meeting->calendar_event_id)
        {
            return;
        }

        $event = CalendarEvent::withoutGlobalScopes()->find($meeting->calendar_event_id);
        if (! $event instanceof CalendarEvent)
        {
            return;
        }

        $event->delete();
        PushCalendarEventToGoogleJob::dispatch($event->id, 'deleted');
    }

    private function syncVoiceGuests(CalendarEvent $event, MeetingTranscript $meeting): void
    {
        $contactIds = collect($meeting->turns ?? [])
            ->pluck('contact_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $voiceIds = Contact::withoutGlobalScopes()
            ->where('team_id', $meeting->team_id)
            ->whereIn('id', $contactIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $current = $event->guests()
            ->withoutGlobalScopes()
            ->pluck('contacts.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($current === $voiceIds)
        {
            return;
        }

        $event->guests()->sync($voiceIds);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(MeetingTranscript $meeting): array
    {
        $start = $meeting->created_at?->copy() ?? now();
        $seconds = 0;

        foreach ($meeting->turns ?? [] as $turn)
        {
            $seconds = max($seconds, (int) ceil((float) ($turn['end_seconds'] ?? 0)));
        }

        $seconds = max($seconds, 60 * 60);

        return [$start, $start->copy()->addSeconds($seconds)];
    }

    private function sameInstant(mixed $left, Carbon $right): bool
    {
        if (! $left instanceof Carbon)
        {
            return false;
        }

        return $left->getTimestamp() === $right->getTimestamp();
    }
}
