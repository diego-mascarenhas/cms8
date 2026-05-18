@php
    $todayEvents = $today ?? [];
    $upcomingEvents = $upcoming ?? [];
@endphp

<div class="tab-content" id="dashboardCalendarTabContent">
    <div class="tab-pane fade show active" id="dashboard-cal-pane-today" role="tabpanel" aria-labelledby="dashboard-cal-tab-today" tabindex="0">
        <div class="dashboard-calendar-tab-inner">
            @include('partials.dashboard-calendar-events-list', [
                'events' => $todayEvents,
                'emptyIcon' => 'ti-calendar-off',
                'emptyTitle' => __('app.dashboard_calendar_empty_today'),
                'emptyMessage' => __('app.dashboard_calendar_empty_today_hint'),
                'showDate' => false,
            ])
        </div>
    </div>

    <div class="tab-pane fade" id="dashboard-cal-pane-upcoming" role="tabpanel" aria-labelledby="dashboard-cal-tab-upcoming" tabindex="0">
        <div class="dashboard-calendar-tab-inner">
            @include('partials.dashboard-calendar-events-list', [
                'events' => $upcomingEvents,
                'emptyIcon' => 'ti-calendar-plus',
                'emptyTitle' => __('app.dashboard_calendar_empty_upcoming'),
                'emptyMessage' => __('app.dashboard_calendar_empty_upcoming_hint'),
                'showDate' => true,
            ])
        </div>
    </div>
</div>
