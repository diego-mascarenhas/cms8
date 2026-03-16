<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class Calendar extends Controller
{
    public function index()
    {
        $eventsUrl = route('app-calendar-events');
        $contacts = Contact::select('id', 'name', 'surname', 'email')
            ->orderBy('name')
            ->get();

        return view('content.apps.app-calendar', [
            'calendarEventsApiUrl' => $eventsUrl,
            'calendarContacts' => $contacts,
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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'all_day' => 'boolean',
            'url' => 'nullable|string|max:2048',
            'label' => 'nullable|string|max:64',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $teamId = auth()->user()?->currentTeam?->id;
        if (! $teamId)
        {
            return response()->json(['error' => 'No team selected'], 403);
        }

        $event = CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'title' => $validated['title'],
            'start' => Carbon::parse($validated['start']),
            'end' => Carbon::parse($validated['end']),
            'all_day' => (bool) ($validated['all_day'] ?? false),
            'url' => $validated['url'] ?? null,
            'label' => $validated['label'] ?? 'Business',
            'location' => $validated['location'] ?? null,
            'notes' => $validated['description'] ?? null,
        ]);

        return response()->json($this->eventToFullCalendar($event), 201);
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

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'start' => 'sometimes|required|date',
            'end' => 'sometimes|required|date|after:start',
            'all_day' => 'boolean',
            'url' => 'nullable|string|max:2048',
            'label' => 'nullable|string|max:64',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $event->fill([
            'title' => $validated['title'] ?? $event->title,
            'start' => isset($validated['start']) ? Carbon::parse($validated['start']) : $event->start,
            'end' => isset($validated['end']) ? Carbon::parse($validated['end']) : $event->end,
            'all_day' => array_key_exists('all_day', $validated) ? (bool) $validated['all_day'] : $event->all_day,
            'url' => array_key_exists('url', $validated) ? ($validated['url'] ?: null) : $event->url,
            'label' => $validated['label'] ?? $event->label,
            'location' => array_key_exists('location', $validated) ? ($validated['location'] ?: null) : $event->location,
            'notes' => array_key_exists('description', $validated) ? ($validated['description'] ?: null) : $event->notes,
        ]);
        $event->save();

        return response()->json($this->eventToFullCalendar($event->fresh()));
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

        $event->delete();

        return response()->json(['ok' => true], 200);
    }

    private function eventToFullCalendar(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->start->toIso8601String(),
            'end' => $event->end->toIso8601String(),
            'allDay' => (bool) $event->all_day,
            'url' => $event->url,
            'extendedProps' => [
                'calendar' => $event->label ?? 'Business',
                'location' => $event->location ?? '',
                'description' => $event->notes ?? '',
            ],
        ];
    }
}
