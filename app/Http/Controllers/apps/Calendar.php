<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;

class Calendar extends Controller
{
    public function index()
    {
        $eventsUrl = route('app-calendar-events');

        return view('content.apps.app-calendar', [
            'calendarEventsApiUrl' => $eventsUrl,
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
            return [
                'id' => $event->id,
                'title' => $event->title,
                'start' => $event->start->toIso8601String(),
                'end' => $event->end->toIso8601String(),
                'allDay' => (bool) $event->all_day,
                'url' => $event->url,
                'extendedProps' => [
                    'calendar' => $event->label ?? 'Business',
                    'location' => $event->url ?? '',
                    'description' => $event->notes ?? '',
                ],
            ];
        });

        return response()->json($items->all());
    }
}
