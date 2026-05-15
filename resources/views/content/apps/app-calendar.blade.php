@extends('layouts/layoutMaster')

@section('title', __('Calendar'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/fullcalendar/fullcalendar.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-calendar.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/fullcalendar/fullcalendar.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
@endsection

@section('page-script')
@if(isset($calendarEventsApiUrl))
<script>window.calendarEventsApiUrl = @json($calendarEventsApiUrl);</script>
@endif
@if(isset($calendarInitialView))
<script>window.calendarInitialView = @json($calendarInitialView);</script>
@endif
<script>
  window.calendarLocale = @json(app()->getLocale());
  window.calendarStrings = {
    addEvent: @json(__('Add Event')),
    updateEvent: @json(__('Update Event')),
    add: @json(__('Add')),
    update: @json(__('Update')),
    sidebar: @json(__('Sidebar')),
    selectValue: @json(__('Select value')),
    pleaseEnterEventTitle: @json(__('Please enter event title ')),
    pleaseEnterStartDate: @json(__('Please enter start date ')),
    pleaseEnterEndDate: @json(__('Please enter end date ')),
    close: @json(__('Close')),
    fcMonth: @json(__('Month')),
    fcWeek: @json(__('Week')),
    fcDay: @json(__('Day')),
    fcList: @json(__('List')),
    fcMonthTitle: @json(__('month view')),
    fcWeekTitle: @json(__('week view')),
    fcDayTitle: @json(__('day view')),
    fcListTitle: @json(__('list view')),
    fcToday: @json(__('Today')),
    fcAllDay: @json(__('all-day'))
  };
</script>
<script src="{{ asset('assets/js/app-calendar-events.js') }}"></script>
<script src="{{ asset('assets/js/app-calendar.js') }}"></script>
@endsection

@section('content')
<div class="card app-calendar-wrapper">
  <div class="row g-0">
    <!-- Calendar Sidebar -->
    <div class="col app-calendar-sidebar" id="app-calendar-sidebar">
      <div class="border-bottom p-4 my-sm-0 mb-3">
        <div class="d-grid gap-2">
          <button class="btn btn-primary btn-toggle-sidebar" data-bs-toggle="offcanvas" data-bs-target="#addEventSidebar" aria-controls="addEventSidebar">
            <i class="ti ti-plus me-1"></i>
            <span class="align-middle">{{ __('Add Event') }}</span>
          </button>
        </div>
      </div>
      <div class="p-3">
        <!-- inline calendar (flatpicker) -->
        <div class="inline-calendar"></div>

        <hr class="container-m-nx mb-4 mt-3">

        <!-- Filter -->
        <div class="mb-3 ms-3">
          <small class="text-small text-muted text-uppercase align-middle">{{ __('Filter') }}</small>
        </div>

        <div class="form-check mb-2 ms-3">
          <input class="form-check-input select-all" type="checkbox" id="selectAll" data-value="all" checked>
          <label class="form-check-label" for="selectAll">{{ __('View All') }}</label>
        </div>

        <div class="app-calendar-events-filter ms-3">
          <div class="form-check form-check-danger mb-2">
            <input class="form-check-input input-filter" type="checkbox" id="select-personal" data-value="personal" checked>
            <label class="form-check-label" for="select-personal">{{ __('Personal') }}</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input input-filter" type="checkbox" id="select-business" data-value="business" checked>
            <label class="form-check-label" for="select-business">{{ __('Business') }}</label>
          </div>
          <div class="form-check form-check-warning mb-2">
            <input class="form-check-input input-filter" type="checkbox" id="select-family" data-value="family" checked>
            <label class="form-check-label" for="select-family">{{ __('Family') }}</label>
          </div>
          <div class="form-check form-check-success mb-2">
            <input class="form-check-input input-filter" type="checkbox" id="select-holiday" data-value="holiday" checked>
            <label class="form-check-label" for="select-holiday">{{ __('Holiday') }}</label>
          </div>
          <div class="form-check form-check-info">
            <input class="form-check-input input-filter" type="checkbox" id="select-etc" data-value="etc" checked>
            <label class="form-check-label" for="select-etc">{{ __('ETC') }}</label>
          </div>
        </div>
      </div>
    </div>
    <!-- /Calendar Sidebar -->

    <!-- Calendar & Modal -->
    <div class="col app-calendar-content">
      <div class="card shadow-none border-0">
        <div class="card-body pb-0">
          <!-- FullCalendar -->
          <div id="calendar"></div>
        </div>
      </div>
      <div class="app-overlay"></div>
      <!-- FullCalendar Offcanvas -->
      <div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="addEventSidebar" aria-labelledby="addEventSidebarLabel">
        <div class="offcanvas-header my-1">
          <h5 class="offcanvas-title" id="addEventSidebarLabel">{{ __('Add Event') }}</h5>
          <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="offcanvas-body pt-0">
          <form class="event-form pt-0" id="eventForm" onsubmit="return false">
            <div class="mb-3">
              <label class="form-label" for="eventTitle">{{ __('Title') }}</label>
              <input type="text" class="form-control" id="eventTitle" name="eventTitle" placeholder="{{ __('Event Title') }}" />
            </div>
            <div class="mb-3">
              <label class="form-label" for="eventLabel">{{ __('Label') }}</label>
              <select class="select2 select-event-label form-select" id="eventLabel" name="eventLabel">
                <option data-label="primary" value="Business" selected>{{ __('Business') }}</option>
                <option data-label="danger" value="Personal">{{ __('Personal') }}</option>
                <option data-label="warning" value="Family">{{ __('Family') }}</option>
                <option data-label="success" value="Holiday">{{ __('Holiday') }}</option>
                <option data-label="info" value="ETC">{{ __('ETC') }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="eventStartDate">{{ __('Start Date') }}</label>
              <input type="text" class="form-control" id="eventStartDate" name="eventStartDate" placeholder="{{ __('Start Date') }}" />
            </div>
            <div class="mb-3">
              <label class="form-label" for="eventEndDate">{{ __('End Date') }}</label>
              <input type="text" class="form-control" id="eventEndDate" name="eventEndDate" placeholder="{{ __('End Date') }}" />
            </div>
            <div class="mb-3">
              <label class="switch">
                <input type="checkbox" class="switch-input allDay-switch" />
                <span class="switch-toggle-slider">
                  <span class="switch-on"></span>
                  <span class="switch-off"></span>
                </span>
                <span class="switch-label">{{ __('All Day') }}</span>
              </label>
            </div>
            <div class="mb-3">
              <label class="form-label" for="eventURL">{{ __('Event URL') }}</label>
              <input type="url" class="form-control" id="eventURL" name="eventURL" placeholder="https://www.google.com" />
            </div>
            <div class="mb-3 select2-primary">
              <label class="form-label" for="eventGuests">{{ __('Add Guests') }}</label>
              <select class="select2 select-event-guests form-select" id="eventGuests" name="eventGuests" multiple>
                @foreach($calendarContacts ?? [] as $contact)
                  @php
                    $contactName = trim(($contact->name ?? '') . ' ' . ($contact->surname ?? ''));
                    $avatarUrl = $contactName !== '' ? \App\Helpers\AvatarHelper::generate($contactName, 32) : '';
                  @endphp
                  <option data-avatar-url="{{ $avatarUrl }}" value="{{ $contact->id }}">{{ $contactName }}{{ $contact->email ? ' (' . $contact->email . ')' : '' }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="eventLocation">{{ __('Location') }}</label>
              <input type="text" class="form-control" id="eventLocation" name="eventLocation" placeholder="{{ __('Enter Location') }}" />
            </div>
            <div class="mb-3">
              <label class="form-label" for="eventDescription">{{ __('Notes') }}</label>
              <textarea class="form-control" name="eventDescription" id="eventDescription"></textarea>
            </div>
            <div class="mb-3 d-flex justify-content-sm-between justify-content-start my-4">
              <div>
                <button type="submit" class="btn btn-primary btn-add-event me-sm-3 me-1">{{ __('Add') }}</button>
                <button type="reset" class="btn btn-label-secondary btn-cancel me-sm-0 me-1" data-bs-dismiss="offcanvas">{{ __('Cancel') }}</button>
              </div>
              <div><button class="btn btn-label-danger btn-delete-event d-none">{{ __('Delete') }}</button></div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- /Calendar & Modal -->
  </div>
</div>
@endsection
