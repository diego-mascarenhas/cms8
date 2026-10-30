<?php

namespace App\Services;

use App\Jobs\PushCalendarEventToGoogleJob;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Team;
use App\Support\CalendarEventDateTimeParser;
use App\Support\MeetRoomLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class MeetBookingService
{
    private const SLOT_MINUTES = 45;

    private const SLOT_STEP_MINUTES = 60;

    private const LEAD_MINUTES = 60;

    private const WORKDAY_START_HOUR = 9;

    private const WORKDAY_END_HOUR = 18;

    /**
     * @return list<array{start: string, end: string, label: string}>
     */
    public function slots(int $teamId, CarbonInterface $day): array
    {
        $timezone = CalendarEventDateTimeParser::wallClockTimezone();
        $day = Carbon::parse($day->toDateString(), $timezone)->startOfDay();

        if (! $this->dayIsOpen($teamId, $day))
        {
            return [];
        }

        $dayStart = $day->copy()->setTime(self::WORKDAY_START_HOUR, 0);
        $dayEnd = $day->copy()->setTime(self::WORKDAY_END_HOUR, 0);
        $earliest = Carbon::now($timezone)->addMinutes(self::LEAD_MINUTES);
        $busy = $this->busyEvents($teamId, $dayStart, $dayEnd);
        $slots = [];
        $cursor = $dayStart->copy();

        while ($cursor->lt($dayEnd))
        {
            $slotEnd = $cursor->copy()->addMinutes(self::SLOT_MINUTES);
            if ($slotEnd->gt($dayEnd))
            {
                break;
            }

            if ($cursor->greaterThanOrEqualTo($earliest) && ! $this->overlaps($cursor, $slotEnd, $busy))
            {
                $slots[] = [
                    'start' => $cursor->format('Y-m-d H:i'),
                    'end' => $slotEnd->format('Y-m-d H:i'),
                    'label' => $cursor->format('H:i'),
                ];
            }

            $cursor->addMinutes(self::SLOT_STEP_MINUTES);
        }

        return $slots;
    }

    /**
     * @return list<string>
     */
    public function openDates(int $teamId, CarbonInterface $month): array
    {
        $timezone = CalendarEventDateTimeParser::wallClockTimezone();
        $cursor = Carbon::parse($month->format('Y-m-01'), $timezone)->startOfMonth();
        $last = $cursor->copy()->endOfMonth()->startOfDay();
        $dates = [];

        while ($cursor->lte($last))
        {
            if ($this->slots($teamId, $cursor) !== [])
            {
                $dates[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @return array{event_id: int, url: string, start: string, end: string}
     */
    public function book(int $teamId, string $name, string $email, string $startRaw): array
    {
        $start = CalendarEventDateTimeParser::parseForStorage($startRaw);
        $end = (clone $start)->addMinutes(self::SLOT_MINUTES);
        $wallStart = Carbon::parse(CalendarEventDateTimeParser::formatWallClock($start), CalendarEventDateTimeParser::wallClockTimezone());

        $offered = collect($this->slots($teamId, $wallStart))
            ->contains(fn (array $slot): bool => $slot['start'] === $wallStart->format('Y-m-d H:i'));

        if (! $offered)
        {
            throw ValidationException::withMessages([
                'start' => 'Ese horario ya no está libre.',
            ]);
        }

        $contact = $this->guestContact($teamId, $name, $email);
        $url = MeetRoomLink::forNewRoom();
        $event = CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'title' => 'Videollamada '.$contact->name,
            'start' => $start,
            'end' => $end,
            'all_day' => false,
            'label' => 'Business',
            'url' => $url,
            'location' => 'REVISION ALPHA',
        ]);
        $event->guests()->sync([$contact->id]);
        PushCalendarEventToGoogleJob::dispatch($event->id, 'created');
        app(CalendarEventInvitationService::class)->notify($event->load('guests'), 'created');

        return [
            'event_id' => (int) $event->id,
            'url' => $url,
            'start' => CalendarEventDateTimeParser::formatWallClock($event->start),
            'end' => CalendarEventDateTimeParser::formatWallClock($event->end),
        ];
    }

    /**
     * @return array{event_id: int, contact_id: int, url: string}
     */
    public function scheduleWalkIn(int $teamId, string $name, string $email, string $url): array
    {
        $contact = $this->guestContact($teamId, $name, strtolower(trim($email)));
        $start = CalendarEventDateTimeParser::parseForStorage(
            Carbon::now(CalendarEventDateTimeParser::wallClockTimezone())->format('Y-m-d H:i'),
        );
        $end = (clone $start)->addMinutes(self::SLOT_MINUTES);
        $event = CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'title' => 'Reunión ahora '.$contact->name,
            'start' => $start,
            'end' => $end,
            'all_day' => false,
            'label' => 'Business',
            'url' => $url,
            'location' => 'REVISION ALPHA',
        ]);
        $event->guests()->sync([$contact->id]);
        PushCalendarEventToGoogleJob::dispatch($event->id, 'created');
        app(CalendarEventInvitationService::class)->notify($event->load('guests'), 'created');

        return [
            'event_id' => (int) $event->id,
            'contact_id' => (int) $contact->id,
            'url' => $url,
        ];
    }

    private function dayIsOpen(int $teamId, CarbonInterface $day): bool
    {
        $hosts = $this->hosts($teamId);
        if ($hosts->isEmpty())
        {
            return ! $day->isWeekend();
        }

        $column = strtolower($day->format('l'));

        foreach ($hosts as $host)
        {
            $weekly = $host->weeklyAvailability;
            $weeklyOpen = $weekly === null
                ? ! $day->isWeekend()
                : (bool) $weekly->{$column};
            $absent = $host->absences->contains(function ($absence) use ($day)
            {
                return $absence->absence_date->toDateString() === $day->toDateString();
            });

            if ($weeklyOpen && ! $absent)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Contact>
     */
    private function hosts(int $teamId)
    {
        return Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->whereHas('user.roles', function ($query)
            {
                $query->whereIn('name', ['admin', 'collaborator']);
            })
            ->with(['weeklyAvailability', 'absences'])
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, CalendarEvent>
     */
    private function busyEvents(int $teamId, CarbonInterface $start, CarbonInterface $end)
    {
        return CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->where('end', '>', $start->copy()->utc())
            ->where('start', '<', $end->copy()->utc())
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CalendarEvent>  $busy
     */
    private function overlaps(CarbonInterface $start, CarbonInterface $end, $busy): bool
    {
        $startUtc = $start->copy()->utc();
        $endUtc = $end->copy()->utc();

        foreach ($busy as $event)
        {
            if ($event->start === null || $event->end === null)
            {
                continue;
            }

            if ($startUtc->lt($event->end) && $endUtc->gt($event->start))
            {
                return true;
            }
        }

        return false;
    }

    private function guestContact(int $teamId, string $name, string $email): Contact
    {
        $existing = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('email', $email)
            ->first();

        if ($existing)
        {
            return $existing;
        }

        $parts = preg_split('/\s+/', trim($name), 2) ?: [];
        $ownerId = Team::query()->whereKey($teamId)->value('user_id');

        return Contact::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'creator_id' => $ownerId,
            'responsible_id' => $ownerId,
            'name' => $parts[0] ?? $name,
            'surname' => $parts[1] ?? null,
            'email' => $email,
            'status_id' => ContactStatus::query()->orderBy('id')->value('id'),
        ]);
    }
}
