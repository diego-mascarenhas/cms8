@php
    $visibleLimit = $limit ?? 4;
    $visibleEvents = array_slice($events, 0, $visibleLimit);
@endphp

@if (count($visibleEvents) > 0)
    <div class="table-responsive">
        <table class="table table-borderless table-sm mb-0 dashboard-calendar-events-table">
            <tbody>
                @foreach ($visibleEvents as $event)
                    @include('partials.dashboard-calendar-event-row', ['event' => $event, 'showDate' => $showDate ?? false])
                @endforeach
            </tbody>
        </table>
    </div>
@else
    @include('partials.dashboard-calendar-events-empty', [
        'emptyIcon' => $emptyIcon,
        'emptyTitle' => $emptyTitle,
        'emptyMessage' => $emptyMessage,
    ])
@endif
