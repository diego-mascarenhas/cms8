@extends('layouts/layoutMaster')

@section('title', __('Google synced contacts'))

@section('content')
<div class="card">
  <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
      <h5 class="card-title mb-0">{{ __('Contacts synced from Google') }}</h5>
      <small class="text-muted">{{ __('Mapped rows for this team (latest first, max 500).') }}</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('contact-list') }}" class="btn btn-label-secondary btn-sm">{{ __('Back to contacts') }}</a>
      <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-primary btn-sm">{{ __('Team settings') }}</a>
    </div>
  </div>
  <div class="card-body">
    @if ($rows->isEmpty())
      <p class="text-muted mb-0">{{ __('No synced contact rows yet. Connect Google in team settings and run sync (or wait for the scheduled job).') }}</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Email') }}</th>
              <th>{{ __('Phone') }}</th>
              <th>{{ __('Google resource') }}</th>
              <th>{{ __('Last synced') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $row)
              <tr>
                <td>{{ trim(($row->name ?? '').' '.($row->surname ?? '')) ?: '—' }}</td>
                <td>{{ $row->email ?? '—' }}</td>
                <td>{{ $row->phone ?? '—' }}</td>
                <td><code class="small">{{ \Illuminate\Support\Str::limit($row->external_id, 48) }}</code></td>
                <td>
                  @if (! empty($row->last_synced_at))
                    {{ \Carbon\Carbon::parse($row->last_synced_at)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if (! empty($row->contact_id))
                    <a href="{{ route('contact.show', $row->contact_id) }}" class="btn btn-sm btn-label-secondary">{{ __('Open') }}</a>
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
