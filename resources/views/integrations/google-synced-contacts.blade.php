@extends('layouts/layoutMaster')

@section('title', __('app.Google synced contacts'))

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
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="alert alert-info mb-0" role="alert">
      <h6 class="alert-heading mb-2">{{ __('app.How Google contacts sync works') }}</h6>
      <p class="mb-2 small">{{ __('app.Google contacts sync help body') }}</p>
      <ul class="small mb-0 ps-3">
        <li>{{ __('app.Google sync schedule bullet') }}</li>
        <li>{{ __('app.Google sync queue bullet') }}</li>
        <li>{{ __('app.Google contacts remote id bullet') }}</li>
      </ul>
    </div>
  </div>
</div>

@include('integrations.partials.google-accounts-scopes', ['googleAccounts' => $googleAccounts])

<div class="card mb-4">
  <div class="card-body py-3">
    <div class="row g-3 text-center text-md-start">
      <div class="col-12 col-md-3">
        <div class="text-muted small">{{ __('app.CRM contact mappings count') }}</div>
        <div class="fs-5 fw-semibold">{{ (int) ($stats->mapped_total ?? 0) }}</div>
        <div class="text-muted small mt-2">{{ __('app.People read last successful sync') }}</div>
        <div class="fs-6 fw-semibold">{{ (int) ($contactsLastSyncPulledTotal ?? 0) }}</div>
        <div class="small text-muted mt-1">{{ __('app.Last sync pulled hint contacts') }}</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('app.Active in CRM') }}</div>
        <div class="fs-5 fw-semibold text-success">{{ (int) ($stats->local_active ?? 0) }}</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('app.Soft-deleted in CRM') }}</div>
        <div class="fs-5 fw-semibold text-secondary">{{ (int) ($stats->local_soft_deleted ?? 0) }}</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="text-muted small">{{ __('app.Mapping without contact row') }}</div>
        <div class="fs-5 fw-semibold text-danger">{{ (int) ($stats->missing_local_row ?? 0) }}</div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
      <h5 class="card-title mb-0">{{ __('app.Contacts from Google (remote identifiers)') }}</h5>
      <small class="text-muted">{{ __('app.Each row is a Google People resource id mapped to a CRM contact. Up to 500 rows.') }}</small>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <form method="post" action="{{ route('integrations.google.sync-contacts') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm">{{ __('app.Sync Google contacts now') }}</button>
      </form>
      <a href="{{ route('contact-list') }}" class="btn btn-label-secondary btn-sm">{{ __('app.Back to contacts') }}</a>
      <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-primary btn-sm">{{ __('app.Team settings') }}</a>
    </div>
  </div>
  <div class="card-body">
    @if ($rows->isEmpty())
      <p class="text-muted mb-0">{{ __('app.No synced contact rows yet. Connect Google in team settings and run sync (or wait for the scheduled job).') }}</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>{{ __('app.Local status') }}</th>
              <th>{{ __('app.Google resource id') }}</th>
              <th>{{ __('app.Name (CRM copy)') }}</th>
              <th>{{ __('app.Email') }}</th>
              <th>{{ __('app.Phone') }}</th>
              <th>{{ __('app.Row last synced') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $row)
              @php
                $contactDeleted = ! empty($row->contact_deleted_at);
                $hasContact = ! empty($row->contact_id);
              @endphp
              <tr>
                <td>
                  @if (! $hasContact)
                    <span class="badge bg-label-danger">{{ __('app.Missing local') }}</span>
                  @elseif ($contactDeleted)
                    <span class="badge bg-label-secondary">{{ __('app.Hidden in CRM') }}</span>
                  @else
                    <span class="badge bg-label-success">{{ __('app.In sync') }}</span>
                  @endif
                </td>
                <td><code class="small">{{ \Illuminate\Support\Str::limit($row->external_id, 48) }}</code></td>
                <td>{{ trim(($row->name ?? '').' '.($row->surname ?? '')) ?: '—' }}</td>
                <td>{{ $row->email ?? '—' }}</td>
                <td>{{ $row->phone ?? '—' }}</td>
                <td>
                  @if (! empty($row->last_synced_at))
                    {{ \Carbon\Carbon::parse($row->last_synced_at)->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if (! empty($row->contact_id) && empty($row->contact_deleted_at))
                    <a href="{{ route('contact.show', $row->contact_id) }}" class="btn btn-sm btn-label-secondary">{{ __('app.Open') }}</a>
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
