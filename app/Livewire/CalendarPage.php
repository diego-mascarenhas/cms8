<?php

namespace App\Livewire;

use App\Jobs\PushCalendarEventToGoogleJob;
use App\Models\CalendarEvent;
use Carbon\Carbon;
use Livewire\Component;

class CalendarPage extends Component
{
    public string $currentMonth;

    public array $events = [];

    public ?int $editingId = null;

    public string $title = '';

    public string $start = '';

    public string $end = '';

    public bool $allDay = false;

    public string $notes = '';

    public string $description = '';

    public string $location = '';

    public string $url = '';

    public string $label = 'Business';

    /** @var array<string> Filter by label (empty = view all). */
    public array $filterLabels = [];

    public function mount(): void
    {
        $this->currentMonth = now()->startOfMonth()->toDateString();
        $this->loadEvents();
    }

    public function loadEvents(): void
    {
        $start = Carbon::parse($this->currentMonth)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $this->events = CalendarEvent::query()
            ->whereBetween('start', [$start, $end])
            ->orderBy('start')
            ->get()
            ->map(function (CalendarEvent $event)
            {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start' => $event->start?->toDateTimeString(),
                    'end' => $event->end?->toDateTimeString(),
                    'all_day' => $event->all_day,
                    'notes' => $event->notes,
                    'url' => $event->url,
                    'label' => $event->label ?? 'Business',
                ];
            })
            ->toArray();
    }

    public function setMonth(string $direction): void
    {
        $month = Carbon::parse($this->currentMonth);

        if ($direction === 'prev')
        {
            $month->subMonth();
        } elseif ($direction === 'next')
        {
            $month->addMonth();
        }

        $this->currentMonth = $month->startOfMonth()->toDateString();
        $this->loadEvents();
    }

    public function startCreate(string $date): void
    {
        $this->resetForm();
        $this->start = $date.' 09:00:00';
        $this->end = $date.' 10:00:00';
    }

    public function startEdit(int $id): void
    {
        $event = CalendarEvent::findOrFail($id);

        $this->editingId = $event->id;
        $this->title = $event->title;
        $this->start = $event->start?->toDateTimeString() ?? '';
        $this->end = $event->end?->toDateTimeString() ?? '';
        $this->allDay = $event->all_day;
        $this->description = $event->description ?? '';
        $this->location = $event->location ?? '';
        $this->notes = $event->notes ?? '';
        $this->url = $event->url ?? '';
        $this->label = $event->label ?? 'Business';
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $start = Carbon::parse($this->start);
        $end = Carbon::parse($this->end);

        if ($this->editingId)
        {
            $event = CalendarEvent::findOrFail($this->editingId);
        } else
        {
            $event = new CalendarEvent;
            $event->team_id = auth()->user()->currentTeam->id;
        }

        $event->title = $this->title;
        $event->start = $start;
        $event->end = $end;
        $event->all_day = $this->allDay;
        $event->notes = $this->notes !== '' ? $this->notes : null;
        $event->url = $this->url !== '' ? $this->url : null;
        $event->label = $this->label !== '' ? $this->label : null;
        $event->save();

        PushCalendarEventToGoogleJob::dispatch($event->id, $this->editingId ? 'updated' : 'created');

        $this->resetForm();
        $this->loadEvents();
    }

    public function delete(int $id): void
    {
        $event = CalendarEvent::findOrFail($id);
        $eventId = $event->id;
        $event->delete();
        PushCalendarEventToGoogleJob::dispatch($eventId, 'deleted');

        $this->resetForm();
        $this->loadEvents();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->start = '';
        $this->end = '';
        $this->allDay = false;
        $this->notes = '';
        $this->url = '';
        $this->label = 'Business';
    }

    public function toggleFilter(string $label): void
    {
        $key = array_search($label, $this->filterLabels, true);
        if ($key !== false)
        {
            unset($this->filterLabels[$key]);
            $this->filterLabels = array_values($this->filterLabels);
        } else
        {
            $this->filterLabels[] = $label;
        }
    }

    public function getDisplayEventsProperty(): array
    {
        if ($this->filterLabels === [])
        {
            return $this->events;
        }

        return array_values(array_filter($this->events, function ($event)
        {
            return in_array($event['label'] ?? 'Business', $this->filterLabels, true);
        }));
    }

    public function render()
    {
        return view('livewire.calendar-page');
    }
}
