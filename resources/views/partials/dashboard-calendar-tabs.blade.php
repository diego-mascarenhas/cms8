@php
    $todayEvents = $today ?? [];
    $upcomingEvents = $upcoming ?? [];
@endphp

<ul class="nav nav-pills nav-justified dashboard-calendar-tabs mb-2" id="dashboardCalendarTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="dashboard-cal-tab-today" data-bs-toggle="tab" data-bs-target="#dashboard-cal-pane-today" type="button" role="tab" aria-controls="dashboard-cal-pane-today" aria-selected="true">
            <i class="ti ti-calendar-event ti-xs me-1"></i><span class="dashboard-calendar-tab-label">{{ __('app.dashboard_calendar_tab_today') }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="dashboard-cal-tab-upcoming" data-bs-toggle="tab" data-bs-target="#dashboard-cal-pane-upcoming" type="button" role="tab" aria-controls="dashboard-cal-pane-upcoming" aria-selected="false">
            <i class="ti ti-clock ti-xs me-1"></i><span class="dashboard-calendar-tab-label">{{ __('app.dashboard_calendar_tab_upcoming') }}</span>
        </button>
    </li>
</ul>

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
