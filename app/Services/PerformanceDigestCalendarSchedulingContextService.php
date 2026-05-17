<?php

namespace App\Services;

use App\Jobs\PushCalendarEventToGoogleJob;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Team;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PerformanceDigestCalendarSchedulingContextService
{
    private const SLOT_MINUTES = 30;

    private const WORKDAY_START_HOUR = 9;

    private const WORKDAY_END_HOUR = 18;

    /**
     * @return array{
     *     requested_date_label: string,
     *     slots_text: string,
     *     booked_start: string,
     *     booked_end: string,
     *     booked_label: string,
     *     event_id: int,
     *     calendar_url: string|null,
     *     has_free_slots: bool
     * }|null
     */
    public function forMessage(Team $team, ?Contact $contact, string $body): ?array
    {
        if (! $team->hasModule('calendar') || ! $this->isSchedulingRequest($body))
        {
            return null;
        }

        $targetDay = $this->parseRequestedDay($body);
        if ($targetDay === null)
        {
            $targetDay = now()->addDay()->startOfDay();
        }

        if ($targetDay->isWeekend())
        {
            $targetDay = $targetDay->nextWeekday()->startOfDay();
        }

        $slots = $this->findFreeSlots($team, $targetDay, 3);
        if ($slots === [])
        {
            return [
                'requested_date_label' => $this->formatDayLabel($targetDay),
                'slots_text' => '',
                'booked_start' => '',
                'booked_end' => '',
                'booked_label' => '',
                'event_id' => 0,
                'calendar_url' => $this->calendarUrl(),
                'has_free_slots' => false,
            ];
        }

        $existing = $this->findExistingCallWithContact($team, $contact, $targetDay);
        $booked = $existing ?? $this->reserveFirstSlot($team, $contact, $slots[0]);

        $slotsText = collect($slots)
            ->map(fn (array $slot): string => $slot['label'])
            ->implode(', ');

        return [
            'requested_date_label' => $this->formatDayLabel($targetDay),
            'slots_text' => $slotsText,
            'booked_start' => $booked?->start?->format('H:i') ?? '',
            'booked_end' => $booked?->end?->format('H:i') ?? '',
            'booked_label' => $booked !== null
                ? $booked->start->format('d/m/Y H:i').'–'.$booked->end->format('H:i')
                : '',
            'event_id' => (int) ($booked?->id ?? 0),
            'calendar_url' => $this->calendarUrl(),
            'has_free_slots' => true,
        ];
    }

    private function isSchedulingRequest(string $text): bool
    {
        $normalized = mb_strtolower($text);

        return (bool) preg_match(
            '/\b(llamada|llamar|reuni[oó]n|agendar|agenda|cita|videollamada|meet|zoom|teams|disponibilidad|hueco|franja|horario)\b/u',
            $normalized,
        );
    }

    private function parseRequestedDay(string $text): ?CarbonInterface
    {
        $normalized = mb_strtolower($text);

        if (preg_match('/\bpasado\s+mañana|pasado\s+manana\b/u', $normalized))
        {
            return now()->addDays(2)->startOfDay();
        }

        if (preg_match('/\bmañana|manana\b/u', $normalized))
        {
            return now()->addDay()->startOfDay();
        }

        if (preg_match('/\bhoy\b/u', $normalized))
        {
            return now()->startOfDay();
        }

        $weekdays = [
            'lunes' => Carbon::MONDAY,
            'martes' => Carbon::TUESDAY,
            'miércoles' => Carbon::WEDNESDAY,
            'miercoles' => Carbon::WEDNESDAY,
            'jueves' => Carbon::THURSDAY,
            'viernes' => Carbon::FRIDAY,
        ];

        foreach ($weekdays as $word => $dayOfWeek)
        {
            if (preg_match('/\b'.$word.'\b/u', $normalized))
            {
                $candidate = now()->startOfDay();
                while ($candidate->dayOfWeek !== $dayOfWeek || $candidate->isPast())
                {
                    $candidate = $candidate->addDay();
                }

                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<array{start: CarbonInterface, end: CarbonInterface, label: string}>
     */
    private function findFreeSlots(Team $team, CarbonInterface $day, int $limit): array
    {
        $dayStart = $day->copy()->setTime(self::WORKDAY_START_HOUR, 0);
        $dayEnd = $day->copy()->setTime(self::WORKDAY_END_HOUR, 0);
        $now = now();

        $busy = CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNull('deleted_at')
            ->where('end', '>', $dayStart)
            ->where('start', '<', $dayEnd)
            ->orderBy('start')
            ->get();

        $slots = [];
        $cursor = $dayStart->copy();

        while ($cursor->lt($dayEnd) && count($slots) < $limit)
        {
            $slotEnd = $cursor->copy()->addMinutes(self::SLOT_MINUTES);
            if ($slotEnd->gt($dayEnd))
            {
                break;
            }

            if ($cursor->greaterThan($now) && ! $this->slotOverlapsBusy($cursor, $slotEnd, $busy))
            {
                $slots[] = [
                    'start' => $cursor->copy(),
                    'end' => $slotEnd->copy(),
                    'label' => $cursor->format('H:i'),
                ];
            }

            $cursor->addMinutes(self::SLOT_MINUTES);
        }

        return $slots;
    }

    /**
     * @param  Collection<int, CalendarEvent>  $busy
     */
    private function slotOverlapsBusy(CarbonInterface $start, CarbonInterface $end, Collection $busy): bool
    {
        foreach ($busy as $event)
        {
            if ($event->start === null || $event->end === null)
            {
                continue;
            }

            if ($start->lt($event->end) && $end->gt($event->start))
            {
                return true;
            }
        }

        return false;
    }

    private function findExistingCallWithContact(Team $team, ?Contact $contact, CarbonInterface $day): ?CalendarEvent
    {
        if ($contact === null)
        {
            return null;
        }

        return CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNull('deleted_at')
            ->whereDate('start', $day->toDateString())
            ->whereHas('guests', function ($query) use ($contact): void
            {
                $query->where('contacts.id', $contact->id);
            })
            ->orderBy('start')
            ->first();
    }

    /**
     * @param  array{start: CarbonInterface, end: CarbonInterface, label: string}  $slot
     */
    private function reserveFirstSlot(Team $team, ?Contact $contact, array $slot): ?CalendarEvent
    {
        $start = Carbon::parse($slot['start']);
        $end = Carbon::parse($slot['end']);

        if ($this->slotOverlapsBusy($start, $end, CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNull('deleted_at')
            ->where('end', '>', $start)
            ->where('start', '<', $end)
            ->get()))
        {
            return null;
        }

        $firstName = $contact ? trim((string) $contact->name) : '';
        $title = $firstName !== ''
            ? (string) __('app.performance_digest_calendar_event_title_call', ['name' => mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8')])
            : (string) __('app.performance_digest_calendar_event_title_call_generic');

        $event = CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'title' => $title,
            'start' => $start,
            'end' => $end,
            'all_day' => false,
            'label' => 'Business',
        ]);

        if ($contact !== null)
        {
            $event->guests()->sync([$contact->id]);
        }

        PushCalendarEventToGoogleJob::dispatch($event->id, 'created');

        return $event->fresh(['guests']);
    }

    private function formatDayLabel(CarbonInterface $day): string
    {
        if ($day->isToday())
        {
            return (string) __('app.performance_digest_calendar_day_today');
        }

        if ($day->isTomorrow())
        {
            return (string) __('app.performance_digest_calendar_day_tomorrow');
        }

        return $day->locale(app()->getLocale())->isoFormat('dddd D/M');
    }

    private function calendarUrl(): ?string
    {
        return \Illuminate\Support\Facades\Route::has('app-calendar')
            ? route('app-calendar', ['view' => 'timeGridDay'])
            : null;
    }
}
