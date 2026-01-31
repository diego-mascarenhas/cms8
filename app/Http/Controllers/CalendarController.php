<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use App\Services\GoogleCredentialsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Show calendar view
     */
    public function index()
    {
        $team = auth()->user()->currentTeam;

        // Check if Google credentials are configured
        if (! GoogleCredentialsService::hasCredentials($team))
        {
            return redirect()->route('team-settings.edit', ['team' => $team, 'group' => 'analytics'])
                ->with('warning', 'Please configure Google credentials first to use the calendar integration.');
        }

        return view('calendar.index', compact('team'));
    }

    /**
     * Get events for calendar (API endpoint)
     */
    public function getEvents(Request $request)
    {
        $team = auth()->user()->currentTeam;

        try
        {
            $calendarService = new GoogleCalendarService($team);

            $start = $request->input('start')
                ? Carbon::parse($request->input('start'))
                : Carbon::now()->startOfMonth();

            $end = $request->input('end')
                ? Carbon::parse($request->input('end'))
                : Carbon::now()->endOfMonth();

            $events = $calendarService->listEvents($start, $end);

            // Transform events for FullCalendar format
            $formattedEvents = collect($events)->map(function ($event)
            {
                $start = $event->getStart()->getDateTime() ?? $event->getStart()->getDate();
                $end = $event->getEnd()->getDateTime() ?? $event->getEnd()->getDate();

                return [
                    'id' => $event->getId(),
                    'title' => $event->getSummary(),
                    'start' => $start,
                    'end' => $end,
                    'description' => $event->getDescription(),
                    'location' => $event->getLocation(),
                    'allDay' => ! $event->getStart()->getDateTime(),
                ];
            })->toArray();

            return response()->json($formattedEvents);
        } catch (\Exception $e)
        {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new event
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
            'attendees' => 'nullable|array',
            'attendees.*' => 'email',
        ]);

        $team = auth()->user()->currentTeam;

        try
        {
            $calendarService = new GoogleCalendarService($team);

            $options = [
                'description' => $request->input('description'),
                'location' => $request->input('location'),
                'attendees' => $request->input('attendees', []),
            ];

            $event = $calendarService->createEvent(
                $request->input('title'),
                Carbon::parse($request->input('start')),
                Carbon::parse($request->input('end')),
                $options,
            );

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'event' => [
                    'id' => $event->getId(),
                    'title' => $event->getSummary(),
                    'start' => $event->getStart()->getDateTime(),
                    'end' => $event->getEnd()->getDateTime(),
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an event
     */
    public function update(Request $request, string $eventId)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'start' => 'sometimes|date',
            'end' => 'sometimes|date|after:start',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        $team = auth()->user()->currentTeam;

        try
        {
            $calendarService = new GoogleCalendarService($team);

            $updates = [];

            if ($request->has('title'))
            {
                $updates['summary'] = $request->input('title');
            }

            if ($request->has('start'))
            {
                $updates['start'] = Carbon::parse($request->input('start'));
            }

            if ($request->has('end'))
            {
                $updates['end'] = Carbon::parse($request->input('end'));
            }

            if ($request->has('description'))
            {
                $updates['description'] = $request->input('description');
            }

            if ($request->has('location'))
            {
                $updates['location'] = $request->input('location');
            }

            $event = $calendarService->updateEvent($eventId, $updates);

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'event' => [
                    'id' => $event->getId(),
                    'title' => $event->getSummary(),
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an event
     */
    public function destroy(string $eventId)
    {
        $team = auth()->user()->currentTeam;

        try
        {
            $calendarService = new GoogleCalendarService($team);
            $calendarService->deleteEvent($eventId);

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully',
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Quick add event from text
     */
    public function quickAdd(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
        ]);

        $team = auth()->user()->currentTeam;

        try
        {
            $calendarService = new GoogleCalendarService($team);
            $event = $calendarService->quickAdd($request->input('text'));

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'event' => [
                    'id' => $event->getId(),
                    'title' => $event->getSummary(),
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
