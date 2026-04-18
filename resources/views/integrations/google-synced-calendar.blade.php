@extends('layouts/layoutMaster')

@section('title', __('app.Google synced events'))

@section('content')
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="alert alert-info mb-0" role="alert">
      <h6 class="alert-heading mb-2">{{ __('app.How Google calendar sync works') }}</h6>
      <p class="mb-2 small">{{ __('app.Google calendar sync help body') }}</p>
      <ul class="small mb-0 ps-3">
        <li>{{ __('app.Google sync schedule bullet') }}</li>
        <li>{{ __('app.Google sync queue bullet') }}</li>
        <li>{{ __('app.Google calendar cancelled bullet') }}</li>
      </ul>
    </div>
  </div>
</div>

@if ($googleAccounts->isNotEmpty())
<div class="card mb-4">
  <div class="card-header">
    <h6 class="mb-0">{{ __('app.Connected Google accounts (this team)') }}</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>{{ __('app.User') }}</th>
            <th>{{ __('app.Last account sync') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($googleAccounts as $acct)
            <tr>
              <td>{{ $acct->user_name ?? '—' }} <span class="text-muted small">({{ $acct->user_email ?? '—' }})</span></td>
              <td>
                @if (! empty($acct->last_synced_at))
                  {{ \Carbon\Carbon::parse($acct->last_synced_at)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif

<div class="card mb-4">
  <div class="card-body py-3">
    <div class="row g-3 text-center text-md-start">
      <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('app.Mapped events (Google → local)') }}</div>
        <div class="fs-5 fw-semibold">{{ (int) ($stats->mapped_total ?? 0) }}</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('app.Visible in app calendar') }}</div>
        <div class="fs-5 fw-semibold text-success">{{ (int) ($stats->local_visible ?? 0) }}</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('app.Hidden locally (soft-deleted)') }}</div>
        <div class="fs-5 fw-semibold text-secondary">{{ (int) ($stats->local_soft_deleted ?? 0) }}</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('app.Mapping without local row') }}</div>
        <div class="fs-5 fw-semibold text-danger">{{ (int) ($stats->missing_local_row ?? 0) }}</div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
      <h5 class="card-title mb-0">{{ __('app.Calendar events synced from Google') }}</h5>
      <small class="text-muted">{{ __('app.Each row is a Google event id linked to a local copy. Table shows up to 500 rows.') }}</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('app-calendar') }}" class="btn btn-label-secondary btn-sm">{{ __('app.Back to calendar') }}</a>
      <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-primary btn-sm">{{ __('app.Team settings') }}</a>
    </div>
  </div>
  <div class="card-body">
    @if ($rows->isEmpty())
      <p class="text-muted mb-0">{{ __('app.No synced calendar rows yet. Connect Google in team settings and run sync (or wait for the scheduled job).') }}</p>
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
