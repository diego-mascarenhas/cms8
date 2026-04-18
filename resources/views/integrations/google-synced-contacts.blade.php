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

<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="d-block text-muted small">{{ __('app.Google stats kpi_mappings_short') }}</span>
            <div class="d-flex align-items-center my-2">
              <h3 class="mb-0">{{ (int) ($stats->mapped_total ?? 0) }}</h3>
            </div>
            <p class="mb-0 text-muted small">{{ __('app.CRM contact mappings count') }}</p>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-git-compare ti-sm"></i></span>
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
            <span class="d-block text-muted small">{{ __('app.Google stats kpi_last_pull_short') }}</span>
            <div class="d-flex align-items-center my-2">
              <h3 class="mb-0">{{ (int) ($contactsLastSyncPulledTotal ?? 0) }}</h3>
            </div>
            <p class="mb-0 text-muted small">{{ __('app.People read last successful sync') }}</p>
            <p class="mb-0 mt-1 text-muted small">{{ __('app.Last sync pulled hint contacts') }}</p>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-info"><i class="ti ti-users ti-sm"></i></span>
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
            <span class="d-block text-muted small">{{ __('app.Active in CRM') }}</span>
            <div class="d-flex align-items-center my-2">
              <h3 class="mb-0 text-success">{{ (int) ($stats->local_active ?? 0) }}</h3>
            </div>
            <p class="mb-0 text-muted small">{{ __('app.Google stats kpi_active_contacts_caption') }}</p>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success"><i class="ti ti-user-check ti-sm"></i></span>
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
            <span class="d-block text-muted small">{{ __('app.Soft-deleted in CRM') }}</span>
            <div class="d-flex align-items-center my-2">
              <h3 class="mb-0 text-secondary">{{ (int) ($stats->local_soft_deleted ?? 0) }}</h3>
            </div>
            <p class="mb-0 text-muted small">{{ __('app.Google stats kpi_soft_deleted_contacts_caption') }}</p>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-secondary"><i class="ti ti-user-off ti-sm"></i></span>
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
            <span class="d-block text-muted small">{{ __('app.Mapping without contact row') }}</span>
            <div class="d-flex align-items-center my-2">
              <h3 class="mb-0 text-danger">{{ (int) ($stats->missing_local_row ?? 0) }}</h3>
            </div>
            <p class="mb-0 text-muted small">{{ __('app.Google stats kpi_missing_mapping_caption') }}</p>
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
              <th>{{ __('app.Name (CRM copy)') }}</th>
              <th>{{ __('app.Email') }}</th>
              <th>{{ __('app.Phone') }}</th>
              <th>{{ __('app.Google contacts column updated') }}</th>
              <th>{{ __('app.Google resource id') }}</th>
              <th>{{ __('app.Google contacts column status') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $row)
              @php
                $contactDeleted = ! empty($row->contact_deleted_at);
                $hasContact = ! empty($row->contact_id);
                $canOpenContact = $hasContact && ! $contactDeleted;
              @endphp
              <tr>
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
                <td><code class="small user-select-all">{{ \Illuminate\Support\Str::limit($row->external_id, 48) }}</code></td>
                <td>
                  <div class="d-flex align-items-center gap-1 flex-wrap">
                    @if (! $hasContact)
                      <span class="badge bg-label-danger">{{ __('app.Missing local') }}</span>
                    @elseif ($contactDeleted)
                      <span class="badge bg-label-secondary">{{ __('app.Hidden in CRM') }}</span>
                    @else
                      <span class="badge bg-label-success">{{ __('app.In sync') }}</span>
                    @endif
                    @if ($canOpenContact)
                      <a href="{{ route('contact.show', $row->contact_id) }}" class="btn btn-icon btn-sm btn-text-secondary" title="{{ __('app.View contact detail') }}" aria-label="{{ __('app.View contact detail') }}">
                        <i class="ti ti-eye ti-sm"></i>
                      </a>
                    @else
                      <span class="text-muted d-inline-flex align-items-center" title="{{ __('app.Google contacts eye disabled hint') }}" role="img" aria-label="{{ __('app.Google contacts eye disabled hint') }}">
                        <i class="ti ti-eye-off ti-sm"></i>
                      </span>
                    @endif
                  </div>
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
