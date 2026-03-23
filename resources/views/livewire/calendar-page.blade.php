@php
    $displayEvents = $this->displayEvents;
    $labelColors = [
        'Business' => 'primary',
        'Personal' => 'danger',
        'Family' => 'warning',
        'Holiday' => 'success',
        'ETC' => 'info',
    ];
@endphp
<div>
<div class="card app-calendar-wrapper position-relative">
    <div class="row g-0">
        {{-- Sidebar (Vuexy layout) --}}
        <div class="col-12 col-lg-4 col-xl-3 app-calendar-sidebar border-end p-4">
            <button type="button" class="btn btn-primary w-100 mb-4" data-bs-toggle="offcanvas" data-bs-target="#addEventSidebar" wire:click="startCreate('{{ now()->format('Y-m-d') }}')">
                <i class="ti ti-plus me-1"></i>
                {{ __('Add Event') }}
            </button>

            {{-- Mini calendar (weeks built in PHP to avoid @while / $loop issues) --}}
            @php
                $month = \Carbon\Carbon::parse($currentMonth);
                $miniStart = $month->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
                $miniEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
                $miniWeeks = [];
                $miniCursor = $miniStart->copy();
                while ($miniCursor <= $miniEnd) {
                    $row = [];
                    for ($d = 0; $d < 7; $d++) {
                        $row[] = [
                            'day' => $miniCursor->day,
                            'isCurrentMonth' => $miniCursor->month === $month->month,
                            'isToday' => $miniCursor->isToday(),
                        ];
                        $miniCursor->addDay();
                    }
                    $miniWeeks[] = $row;
                }
            @endphp
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-semibold">{{ $month->translatedFormat('F Y') }}</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" wire:click="setMonth('prev')"><i class="ti ti-chevron-left"></i></button>
                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" wire:click="setMonth('next')"><i class="ti ti-chevron-right"></i></button>
                    </div>
                </div>
                <div class="calendar-mini table-responsive">
                    <table class="table table-bordered table-sm mb-0 w-100">
                        <thead>
                            <tr>
                                <th class="text-center p-1"><small>{{ __('Mo') }}</small></th>
                                <th class="text-center p-1"><small>{{ __('Tu') }}</small></th>
                                <th class="text-center p-1"><small>{{ __('We') }}</small></th>
                                <th class="text-center p-1"><small>{{ __('Th') }}</small></th>
                                <th class="text-center p-1"><small>{{ __('Fr') }}</small></th>
                                <th class="text-center p-1"><small>{{ __('Sa') }}</small></th>
                                <th class="text-center p-1"><small>{{ __('Su') }}</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($miniWeeks as $miniRow)
                                <tr>
                                    @foreach ($miniRow as $cell)
                                        <td class="text-center p-1 {{ ! $cell['isCurrentMonth'] ? 'text-muted' : '' }} {{ $cell['isToday'] ? 'bg-primary bg-opacity-10 rounded' : '' }}">
                                            <small>{{ $cell['day'] }}</small>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Filter --}}
            <h6 class="small text-uppercase text-muted mb-2">{{ __('Filter') }}</h6>
            <div class="d-flex flex-column gap-2">
                <div class="form-check">
                    <input class="form-check-input input-filter" type="checkbox" id="filter-all" wire:click="$set('filterLabels', [])" {{ count($filterLabels) === 0 ? 'checked' : '' }}>
                    <label class="form-check-label" for="filter-all">{{ __('View All') }}</label>
                </div>
                @foreach (['Personal' => 'danger', 'Business' => 'primary', 'Family' => 'warning', 'Holiday' => 'success'] as $labelValue => $color)
                    @php $checked = in_array($labelValue, $filterLabels, true) || count($filterLabels) === 0; @endphp
                    <div class="form-check">
                        <input class="form-check-input input-filter" type="checkbox" id="filter-{{ $labelValue }}" data-value="{{ $labelValue }}" wire:click="toggleFilter('{{ $labelValue }}')" {{ $checked ? 'checked' : '' }}>
                        <label class="form-check-label d-flex align-items-center gap-1" for="filter-{{ $labelValue }}">
                            <span class="badge badge-dot bg-{{ $color }}"></span>
                            {{ __($labelValue) }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Main content --}}
        <div class="col-12 col-lg-8 col-xl-9 app-calendar-content">
            <div class="card-body">
                {{-- Toolbar: title + prev/next + view --}}
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h5 class="mb-0">{{ $month->translatedFormat('F Y') }}</h5>
                        <small class="text-muted">{{ __('Calendar') }}</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-primary" wire:click="setMonth('prev')"><i class="ti ti-chevron-left"></i></button>
                            <button type="button" class="btn btn-sm btn-primary" wire:click="setMonth('next')"><i class="ti ti-chevron-right"></i></button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-primary">{{ __('Month') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary disabled">{{ __('Week') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary disabled">{{ __('Day') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary disabled">{{ __('List') }}</button>
                        </div>
                    </div>
                </div>

                @php
                    $startOfMonth = \Carbon\Carbon::parse($currentMonth)->startOfMonth();
                    $endOfMonth = (clone $startOfMonth)->endOfMonth();
                    $startPadding = $startOfMonth->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                    $endPadding = $endOfMonth->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                    $cursor = $startPadding->copy();
                    $eventsByDate = collect($displayEvents)->groupBy(function ($event) {
                        return \Carbon\Carbon::parse($event['start'])->toDateString();
                    });
                @endphp

                {{-- Weekday headers (7 equal columns) --}}
                <div class="row row-cols-7 g-0 mb-2 text-center fw-semibold text-muted small">
                    <div class="col">{{ __('Mo') }}</div>
                    <div class="col">{{ __('Tu') }}</div>
                    <div class="col">{{ __('We') }}</div>
                    <div class="col">{{ __('Th') }}</div>
                    <div class="col">{{ __('Fr') }}</div>
                    <div class="col">{{ __('Sa') }}</div>
                    <div class="col">{{ __('Su') }}</div>
                </div>

                {{-- Month grid: 6 rows x 7 days (built in PHP so no @while / $loop) --}}
                @php
                    $weeks = [];
                    $weekCursor = $startPadding->copy();
                    while ($weekCursor <= $endPadding) {
                        $week = [];
                        for ($d = 0; $d < 7; $d++) {
                            $week[] = [
                                'date' => $weekCursor->copy(),
                                'dateStr' => $weekCursor->toDateString(),
                                'isCurrentMonth' => $weekCursor->month === $startOfMonth->month,
                                'events' => $eventsByDate->get($weekCursor->toDateString(), collect()),
                            ];
                            $weekCursor->addDay();
                        }
                        $weeks[] = $week;
                    }
                @endphp
                @foreach ($weeks as $weekIndex => $week)
                    <div class="row row-cols-7 g-0 calendar-week" wire:key="week-{{ $weekIndex }}">
                        @foreach ($week as $day)
                            <div class="col border-start border-bottom p-2 calendar-day-cell min-h-8 {{ $day['isCurrentMonth'] ? '' : 'bg-light' }}" wire:key="day-{{ $day['dateStr'] }}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <button type="button" class="btn btn-sm btn-icon btn-text-secondary p-0" data-bs-toggle="offcanvas" data-bs-target="#addEventSidebar" wire:click="startCreate('{{ $day['dateStr'] }}')">
                                        <i class="ti ti-plus ti-xs"></i>
                                    </button>
                                    <span class="fw-semibold small {{ $day['date']->isToday() ? 'text-primary' : '' }}">{{ $day['date']->day }}</span>
                                </div>
                                <div class="calendar-day-events">
                                    @foreach ($day['events'] as $event)
                                        @php
                                            $color = $labelColors[$event['label'] ?? 'Business'] ?? 'primary';
                                        @endphp
                                        <div class="calendar-event-block bg-{{ $color }} bg-opacity-10 border-start border-{{ $color }} border-3 rounded px-1 py-0 small mb-1 cursor-pointer text-truncate"
                                             wire:key="event-{{ $event['id'] }}"
                                             wire:click="startEdit({{ $event['id'] }})"
                                             data-bs-toggle="offcanvas"
                                             data-bs-target="#addEventSidebar">
                                            <span class="text-muted">{{ \Carbon\Carbon::parse($event['start'])->format('g:ia') }}</span>
                                            {{ $event['title'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Offcanvas: Add/Edit Event (Vuexy style) --}}
<div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="addEventSidebar" aria-labelledby="addEventSidebarLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="addEventSidebarLabel">{{ $editingId ? __('Edit Event') : __('Add Event') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form wire:submit.prevent="save">
            <div class="mb-3">
                <label class="form-label" for="title">{{ __('Title') }}</label>
                <input type="text" id="title" class="form-control" wire:model.defer="title" placeholder="{{ __('Event title') }}">
                @error('title') <small class="text-danger">{{ $message }}</small> @enderror
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label" for="start">{{ __('Start') }}</label>
                    <input type="datetime-local" id="start" class="form-control" wire:model.defer="start">
                    @error('start') <small class="text-danger d-block">{{ $message }}</small> @enderror
                </div>
                <div class="col-6">
                    <label class="form-label" for="end">{{ __('End') }}</label>
                    <input type="datetime-local" id="end" class="form-control" wire:model.defer="end">
                    @error('end') <small class="text-danger d-block">{{ $message }}</small> @enderror
                </div>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="allDay" wire:model="allDay">
                <label class="form-check-label" for="allDay">{{ __('All day') }}</label>
            </div>
            <div class="mb-3">
                <label class="form-label" for="label">{{ __('Label') }}</label>
                <select id="label" class="form-select" wire:model.defer="label">
                    <option value="Business">{{ __('Business') }}</option>
                    <option value="Personal">{{ __('Personal') }}</option>
                    <option value="Family">{{ __('Family') }}</option>
                    <option value="Holiday">{{ __('Holiday') }}</option>
                    <option value="ETC">{{ __('ETC') }}</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="url">{{ __('URL') }}</label>
                <input type="url" id="url" class="form-control" wire:model.defer="url" placeholder="https://">
            </div>
            <div class="mb-3">
                <label class="form-label" for="notes">{{ __('Notes') }}</label>
                <textarea id="notes" class="form-control" rows="3" wire:model.defer="notes"></textarea>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                    {{ $editingId ? __('Update') : __('Add') }}
                </button>
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="offcanvas" wire:click="resetForm">
                    {{ __('Cancel') }}
                </button>
                @if ($editingId)
                    <button type="button" class="btn btn-label-danger ms-auto" wire:click="delete({{ $editingId }})" data-bs-dismiss="offcanvas">
                        {{ __('Delete') }}
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
</div>
