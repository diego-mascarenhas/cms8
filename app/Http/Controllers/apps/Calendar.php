<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;
use App\Jobs\PushCalendarEventToGoogleJob;
use App\Models\CalendarEvent;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class Calendar extends Controller
{
    public function index(Request $request)
    {
        $eventsUrl = route('app-calendar-events');
        $contacts = Contact::select('id', 'name', 'surname', 'email')
            ->orderBy('name')
            ->get();

        $initialView = $request->query('view');
        $allowedViews = ['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listMonth'];
        if (! in_array($initialView, $allowedViews, true))
        {
            $initialView = 'dayGridMonth';
        }

        return view('content.apps.app-calendar', [
            'calendarEventsApiUrl' => $eventsUrl,
            'calendarContacts' => $contacts,
            'calendarInitialView' => $initialView,
        ]);
    }

    /**
     * Return calendar events as JSON for FullCalendar (date range from query).
     */
    public function events(Request $request)
    {
        $start = $request->input('start');
        $end = $request->input('end');

        if (! $start || ! $end)
        {
            return response()->json([]);
        }

        $events = CalendarEvent::query()
            ->with('guests:id,name,surname,email')
            ->where('end', '>', $start)
            ->where('start', '<', $end)
            ->orderBy('start')
            ->get();

        $items = $events->map(function (CalendarEvent $event)
        {
            return $this->eventToFullCalendar($event);
        });

        return response()->json($items->all());
    }

    /**
     * Store a new calendar event (from the calendar form).
     */
    public function store(Request $request)
    {
        $teamId = auth()->user()?->currentTeam?->id;
        if (! $teamId)
        {
            return response()->json(['error' => 'No team selected'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => ['required', 'date', $request->boolean('all_day') ? 'after_or_equal:start' : 'after:start'],
            'all_day' => 'boolean',
            'url' => 'nullable|string|max:2048',
            'label' => 'nullable|string|max:64',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'guests' => 'nullable|array',
            'guests.*' => 'integer|exists:contacts,id',
        ]);

        $allDay = $request->has('all_day') ? $request->boolean('all_day') : false;
        [$eventStart, $eventEnd] = $this->resolveEventDateRange($validated['start'], $validated['end'], $allDay);

        $event = CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'title' => $validated['title'],
            'start' => $eventStart,
            'end' => $eventEnd,
            'all_day' => $allDay,
            'url' => $validated['url'] ?? null,
            'label' => $validated['label'] ?? 'Business',
            'location' => $validated['location'] ?? null,
            'notes' => $validated['description'] ?? null,
        ]);

        $this->syncEventGuests($event, $teamId, $validated['guests'] ?? []);

        PushCalendarEventToGoogleJob::dispatch($event->id, 'created');

        return response()->json($this->eventToFullCalendar($event->load('guests')), 201);
    }

    /**
     * Update an existing calendar event.
     */
    public function update(Request $request, CalendarEvent $event)
    {
        $teamId = auth()->user()?->currentTeam?->id;
        if (! $teamId || $event->team_id != $teamId)
        {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $allDayForValidation = $request->has('all_day') ? $request->boolean('all_day') : $event->all_day;

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'start' => 'sometimes|required|date',
            'end' => ['sometimes', 'required', 'date', $allDayForValidation ? 'after_or_equal:start' : 'after:start'],
            'all_day' => 'boolean',
            'url' => 'nullable|string|max:2048',
            'label' => 'nullable|string|max:64',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'guests' => 'nullable|array',
            'guests.*' => 'integer|exists:contacts,id',
        ]);

        $allDay = $request->has('all_day') ? $request->boolean('all_day') : $event->all_day;

        $eventStart = $event->start;
        $eventEnd = $event->end;
        if (isset($validated['start'], $validated['end']))
        {
            [$eventStart, $eventEnd] = $this->resolveEventDateRange($validated['start'], $validated['end'], $allDay);
        }

        $event->fill([
            'title' => $validated['title'] ?? $event->title,
            'start' => $eventStart,
            'end' => $eventEnd,
            'all_day' => $allDay,
            'url' => array_key_exists('url', $validated) ? ($validated['url'] ?: null) : $event->url,
            'label' => $validated['label'] ?? $event->label,
            'location' => array_key_exists('location', $validated) ? ($validated['location'] ?: null) : $event->location,
            'notes' => array_key_exists('description', $validated) ? ($validated['description'] ?: null) : $event->notes,
        ]);
        $event->save();

        if (array_key_exists('guests', $validated))
        {
            $this->syncEventGuests($event, $teamId, $validated['guests']);
        }

        PushCalendarEventToGoogleJob::dispatch($event->id, 'updated');

        return response()->json($this->eventToFullCalendar($event->fresh()->load('guests')));
    }

    /**
     * Delete a calendar event.
     */
    public function destroy(CalendarEvent $event)
    {
        $teamId = auth()->user()?->currentTeam?->id;
        if (! $teamId || $event->team_id != $teamId)
        {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $eventId = $event->id;
        $event->delete();
        PushCalendarEventToGoogleJob::dispatch($eventId, 'deleted');

        return response()->json(['ok' => true], 200);
    }

    private function eventToFullCalendar(CalendarEvent $event): array
    {
        $guests = $event->relationLoaded('guests')
            ? $event->guests->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $event->guests()->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->all_day ? $event->start->utc()->toDateString() : $event->start->toIso8601String(),
            'end' => $event->all_day ? $event->end->utc()->toDateString() : $event->end->toIso8601String(),
            'allDay' => (bool) $event->all_day,
            'url' => $event->url,
            'extendedProps' => [
                'calendar' => $event->label ?? 'Business',
                'location' => $event->location ?? '',
                'description' => $event->notes ?? '',
                'guests' => $guests,
            ],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveEventDateRange(string $startValue, string $endValue, bool $allDay): array
    {
        if ($allDay)
        {
            return $this->normalizeAllDayEventRange($startValue, $endValue);
        }

        $start = $this->parseEventDateTime($startValue);
        $end = $this->parseEventDateTime($endValue);

        if ($end->lte($start))
        {
            $end = $start->copy()->addHour();
        }

        return [$start, $end];
    }

    /**
     * FullCalendar all-day end dates are exclusive; form dates are inclusive.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function normalizeAllDayEventRange(string $startValue, string $endValue): array
    {
        $start = Carbon::parse($startValue)->startOfDay()->utc();
        $endInclusive = Carbon::parse($endValue)->startOfDay()->utc();

        if ($endInclusive->lt($start))
        {
            $endInclusive = $start->copy();
        }

        return [$start, $endInclusive->copy()->addDay()];
    }

    private function parseEventDateTime(string $value): Carbon
    {
        return Carbon::parse($value)->utc();
    }

    private function syncEventGuests(CalendarEvent $event, int $teamId, array $contactIds): void
    {
        $contactIds = array_values(array_unique(array_map('intval', $contactIds)));
        $validIds = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereIn('id', $contactIds)
            ->pluck('id')
            ->all();
        $event->guests()->sync($validIds);
    }
}
