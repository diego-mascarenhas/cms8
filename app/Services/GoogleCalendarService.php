<?php

namespace App\Services;

use App\Models\Team;
use Carbon\Carbon;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class GoogleCalendarService
{
    protected Team $team;

    protected Calendar $service;

    protected string $calendarId;

    public function __construct(Team $team)
    {
        $this->team = $team;
        $this->initialize();
    }

    /**
     * Initialize Google Calendar service
     */
    protected function initialize(): void
    {
        $client = GoogleCredentialsService::getCalendarClient($this->team);
        $this->service = new Calendar($client);
        $this->calendarId = GoogleCredentialsService::getCalendarId($this->team);
    }

    /**
     * List events for a date range
     */
    public function listEvents(Carbon $startDate, Carbon $endDate, int $maxResults = 100): array
    {
        $optParams = [
            'maxResults' => $maxResults,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => $startDate->toRfc3339String(),
            'timeMax' => $endDate->toRfc3339String(),
        ];

        $results = $this->service->events->listEvents($this->calendarId, $optParams);

        return $results->getItems();
    }

    /**
     * Get a single event
     */
    public function getEvent(string $eventId): Event
    {
        return $this->service->events->get($this->calendarId, $eventId);
    }

    /**
     * Create a new event
     */
    public function createEvent(string $summary, Carbon $start, Carbon $end, array $options = []): Event
    {
        $event = new Event([
            'summary' => $summary,
            'start' => [
                'dateTime' => $start->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $end->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
        ]);

        // Add optional properties
        if (isset($options['description']))
        {
            $event->setDescription($options['description']);
        }

        if (isset($options['location']))
        {
            $event->setLocation($options['location']);
        }

        if (isset($options['attendees']))
        {
            $attendees = [];
            foreach ($options['attendees'] as $email)
            {
                $attendees[] = ['email' => $email];
            }
            $event->setAttendees($attendees);
        }

        if (isset($options['reminders']))
        {
            $event->setReminders($options['reminders']);
        }

        return $this->service->events->insert($this->calendarId, $event);
    }

    /**
     * Update an existing event
     */
    public function updateEvent(string $eventId, array $updates): Event
    {
        $event = $this->getEvent($eventId);

        if (isset($updates['summary']))
        {
            $event->setSummary($updates['summary']);
        }

        if (isset($updates['description']))
        {
            $event->setDescription($updates['description']);
        }

        if (isset($updates['location']))
        {
            $event->setLocation($updates['location']);
        }

        if (isset($updates['start']))
        {
            $event->setStart([
                'dateTime' => $updates['start']->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]);
        }

        if (isset($updates['end']))
        {
            $event->setEnd([
                'dateTime' => $updates['end']->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]);
        }

        return $this->service->events->update($this->calendarId, $eventId, $event);
    }

    /**
     * Delete an event
     */
    public function deleteEvent(string $eventId): void
    {
        $this->service->events->delete($this->calendarId, $eventId);
    }

    /**
     * Quick add event from text
     */
    public function quickAdd(string $text): Event
    {
        return $this->service->events->quickAdd($this->calendarId, $text);
    }

    /**
     * Get calendar free/busy information
     */
    public function getFreeBusy(Carbon $startDate, Carbon $endDate): array
    {
        $freeBusyRequest = new \Google\Service\Calendar\FreeBusyRequest([
            'timeMin' => $startDate->toRfc3339String(),
            'timeMax' => $endDate->toRfc3339String(),
            'items' => [
                ['id' => $this->calendarId],
            ],
        ]);

        $freeBusyResponse = $this->service->freebusy->query($freeBusyRequest);

        return $freeBusyResponse->getCalendars()[$this->calendarId]->getBusy();
    }
}
