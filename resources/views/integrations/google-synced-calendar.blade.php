@extends('layouts/layoutMaster')

@section('title', __('app.Google synced events'))

@section('content')
@if (session('status'))
  <div class="alert alert-success alert-dismissible mb-4" role="alert">
    {{ session('status') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
@if (session('warning'))
  <div class="alert alert-warning alert-dismissible mb-4" role="alert">
    {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
  <div class="d-flex flex-column justify-content-center">
    <h4 class="mb-1 mt-3">{{ __('app.Google synced events') }}</h4>
    <p class="text-muted mb-0">{{ __('app.Google synced calendar heading subtitle') }}</p>
  </div>
  <div class="mt-3 mt-md-0">
    <div class="d-flex flex-wrap gap-2">
      <form method="post" action="{{ route('integrations.google.sync-calendar') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary waves-effect waves-light" aria-label="{{ __('app.Sync Google calendar now') }}">{{ __('app.Google synced header sync') }}</button>
      </form>
      <a href="{{ route('app-calendar') }}" class="btn btn-label-secondary waves-effect waves-light"><i class="ti ti-arrow-left me-1"></i>{{ __('app.Google synced header back') }}</a>
    </div>
  </div>
</div>

@include('integrations.partials.google-accounts-scopes', ['googleAccounts' => $googleAccounts])

<div class="card mb-4">
  <div class="card-header border-bottom">
    <div>
      <h5 class="card-title mb-0">Calendario de Google en uso</h5>
      <small class="text-muted">Este es el calendario que se utiliza actualmente para los trabajos de sincronización.</small>
    </div>
  </div>
  <div class="card-body">
    <div class="mt-3 mb-3">
      <code>{{ $selectedCalendarId ?: 'primary' }}</code>
    </div>

    @if (! empty($calendarListError))
      <div class="alert alert-warning mb-0" role="alert">
        {{ __('Could not list available calendars from Google right now:') }} {{ \Illuminate\Support\Str::limit($calendarListError, 180) }}
      </div>
    @elseif (empty($availableCalendars))
      <p class="text-muted mb-0">Google no devolvió calendarios para la cuenta conectada.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>{{ __('Calendar name') }}</th>
              <th>{{ __('Calendar ID') }}</th>
              <th>{{ __('Status') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($availableCalendars as $calendar)
              @php
                $isSelected = ($selectedCalendarId ?: 'primary') === $calendar['id'] || (($selectedCalendarId ?: 'primary') === 'primary' && $calendar['primary']);
              @endphp
              <tr>
                <td>
                  {{ $calendar['summary'] }}
                  @if ($calendar['primary'])
                    <span class="badge bg-label-info ms-1">{{ __('Primary') }}</span>
                  @endif
                </td>
                <td><code class="small">{{ $calendar['id'] }}</code></td>
                <td>
                  @if ($isSelected)
                    <span class="badge bg-label-success">{{ __('In use for sync') }}</span>
                  @else
                    <span class="badge bg-label-secondary">{{ __('Available') }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="d-block text-muted small">{{ __('app.Google stats calendar kpi_mappings_short') }}</span>
            <div class="d-flex align-items-center my-2">
              <h3 class="mb-0">{{ (int) ($stats->mapped_total ?? 0) }}</h3>
            </div>
            <p class="mb-0 text-muted small">{{ __('app.CRM calendar event mappings count') }}</p>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-calendar-event ti-sm"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="d-block text-muted small">{{ __('app.Visible in app calendar') }}</span>
            <div class="d-flex align-items-center my-2">
              <h3 class="mb-0 text-success">{{ (int) ($stats->local_visible ?? 0) }}</h3>
            </div>
            <p class="mb-0 text-muted small">{{ __('app.Google stats calendar kpi_visible_caption') }}</p>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success"><i class="ti ti-calendar-check ti-sm"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="d-block text-muted small">{{ __('app.Google stats kpi_deleted_short') }}</span>
            <div class="d-flex align-items-center my-2">
              <h3 class="mb-0 text-secondary">{{ (int) ($stats->local_soft_deleted ?? 0) }}</h3>
            </div>
            <p class="mb-0 text-muted small">{{ __('app.Google stats calendar kpi_hidden_caption') }}</p>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-secondary"><i class="ti ti-eye-off ti-sm"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="d-block text-muted small">{{ __('app.Mapping without local row') }}</span>
            <div class="d-flex align-items-center my-2">
              <h3 class="mb-0 text-danger">{{ (int) ($stats->missing_local_row ?? 0) }}</h3>
            </div>
            <p class="mb-0 text-muted small">{{ __('app.Google stats calendar kpi_missing_caption') }}</p>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-danger"><i class="ti ti-alert-triangle ti-sm"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('app.Calendar events synced from Google') }}</h5>
    <small class="text-muted">{{ __('app.Each row is a Google event id linked to a local copy. Table shows up to 500 rows.') }}</small>
  </div>
  <div class="card-body">
    @if ($rows->isEmpty())
      <div class="text-center py-4 py-md-5 px-3">
        <div class="avatar avatar-xl mx-auto mb-3">
          <span class="avatar-initial rounded-circle bg-label-primary">
            <i class="ti ti-calendar-event ti-md"></i>
          </span>
        </div>
        <h5 class="mb-2">{{ __('app.Google synced calendar empty title') }}</h5>
        <p class="text-muted mb-4 mx-auto" style="max-width: 28rem;">{{ __('app.No synced calendar rows yet. Connect Google in team settings and run sync (or wait for the scheduled job).') }}</p>
        <a href="{{ route('integrations.google.connect') }}" class="btn btn-primary">
          {{ $googleAccounts->isNotEmpty() ? __('app.Google integration reconnect') : __('app.Google integration connect') }}
        </a>
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>{{ __('app.Local status') }}</th>
              <th>{{ __('app.Title (local copy)') }}</th>
              <th>{{ __('app.Start') }}</th>
              <th>{{ __('app.End') }}</th>
              <th>{{ __('app.All day') }}</th>
              <th>{{ __('app.Google event id') }}</th>
              <th>{{ __('app.Local event id') }}</th>
              <th>{{ __('app.Row last synced') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $row)
              @php
                $localDeleted = ! empty($row->local_deleted_at);
                $hasLocal = ! empty($row->calendar_event_id);
              @endphp
              <tr>
                <td>
                  @if (! $hasLocal)
                    <span class="badge bg-label-danger">{{ __('app.Missing local') }}</span>
                  @elseif ($localDeleted)
                    <span class="badge bg-label-secondary">{{ __('app.Hidden locally') }}</span>
                  @else
                    <span class="badge bg-label-success">{{ __('app.In sync') }}</span>
                  @endif
                </td>
                <td>{{ $row->title ?? '—' }}</td>
                <td>
                  @if (! empty($row->start))
                    {{ \Carbon\Carbon::parse($row->start)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if (! empty($row->end))
                    {{ \Carbon\Carbon::parse($row->end)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                  @else
                    —
                  @endif
                </td>
                <td>{{ ! empty($row->all_day) && (bool) $row->all_day ? __('app.Yes') : __('app.No') }}</td>
                <td><code class="small">{{ \Illuminate\Support\Str::limit($row->external_id, 40) }}</code></td>
                <td>
                  @if (! empty($row->calendar_event_id))
                    <code class="small">{{ $row->calendar_event_id }}</code>
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if (! empty($row->last_synced_at))
                    {{ \Carbon\Carbon::parse($row->last_synced_at)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                  @else
                    —
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection
