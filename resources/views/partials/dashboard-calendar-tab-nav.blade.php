<div class="dashboard-calendar-tabs d-flex flex-wrap gap-2" id="dashboardCalendarTabs" role="tablist">
    <button type="button"
        class="btn btn-sm btn-label-primary active"
        id="dashboard-cal-tab-today"
        data-bs-toggle="tab"
        data-bs-target="#dashboard-cal-pane-today"
        role="tab"
        aria-controls="dashboard-cal-pane-today"
        aria-selected="true">
        <i class="ti ti-calendar me-1"></i>{{ __('app.dashboard_calendar_tab_today') }}
    </button>
    <button type="button"
        class="btn btn-sm btn-label-primary"
        id="dashboard-cal-tab-upcoming"
        data-bs-toggle="tab"
        data-bs-target="#dashboard-cal-pane-upcoming"
        role="tab"
        aria-controls="dashboard-cal-pane-upcoming"
        aria-selected="false">
        <i class="ti ti-calendar-time me-1"></i>{{ __('app.dashboard_calendar_tab_upcoming') }}
    </button>
</div>
