@if (count($events) > 0)
    <div class="table-responsive">
        <table class="table table-borderless table-sm mb-0 dashboard-calendar-events-table">
            <thead>
                <tr>
                    <th>{{ __('app.dashboard_calendar_col_event') }}</th>
                    @if ($showDate ?? false)
                        <th>{{ __('Date') }}</th>
                    @endif
                    <th class="text-center">{{ __('app.dashboard_calendar_col_time') }}</th>
                    <th class="text-center">{{ __('app.dashboard_calendar_col_type') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($events as $event)
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
