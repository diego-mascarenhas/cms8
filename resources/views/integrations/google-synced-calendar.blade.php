@extends('layouts/layoutMaster')

@section('title', __('Google synced events'))

@section('content')
<div class="card">
  <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
      <h5 class="card-title mb-0">{{ __('Calendar events synced from Google') }}</h5>
      <small class="text-muted">{{ __('Local events linked to Google (latest by start, max 500).') }}</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('app-calendar') }}" class="btn btn-label-secondary btn-sm">{{ __('Back to calendar') }}</a>
      <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-primary btn-sm">{{ __('Team settings') }}</a>
    </div>
  </div>
  <div class="card-body">
    @if ($rows->isEmpty())
      <p class="text-muted mb-0">{{ __('No synced calendar rows yet. Connect Google in team settings and run sync (or wait for the scheduled job).') }}</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>{{ __('Title') }}</th>
              <th>{{ __('Start') }}</th>
              <th>{{ __('End') }}</th>
              <th>{{ __('All day') }}</th>
              <th>{{ __('Google event id') }}</th>
              <th>{{ __('Last synced') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $row)
              <tr>
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
                <td>{{ ! empty($row->all_day) && (bool) $row->all_day ? __('Yes') : __('No') }}</td>
                <td><code class="small">{{ \Illuminate\Support\Str::limit($row->external_id, 40) }}</code></td>
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
