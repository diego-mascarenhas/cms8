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
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
  <div class="d-flex flex-column justify-content-center">
    <h4 class="mb-1 mt-3">{{ __('app.Google synced contacts') }}</h4>
    <p class="text-muted mb-0">{{ __('app.Google synced contacts heading subtitle') }}</p>
  </div>
  <div class="mt-3 mt-md-0">
    <div class="d-flex flex-wrap gap-2">
      <form method="post" action="{{ route('integrations.google.sync-contacts') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary waves-effect waves-light" aria-label="{{ __('app.Sync Google contacts now') }}">{{ __('app.Google synced header sync') }}</button>
      </form>
      <a href="{{ route('contact-list') }}" class="btn btn-label-secondary waves-effect waves-light"><i class="ti ti-arrow-left me-1"></i>{{ __('app.Google synced header back') }}</a>
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
            <span class="d-block text-muted small">{{ __('app.Google stats kpi_deleted_short') }}</span>
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
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">{{ __('app.Contacts from Google (remote identifiers)') }}</h5>
    <small class="text-muted">{{ __('app.Each row is a Google People resource id mapped to a CRM contact. Up to 500 rows.') }}</small>
  </div>
  <div class="card-body">
    @if ($rows->isEmpty())
      <div class="text-center py-4 py-md-5 px-3">
        <div class="avatar avatar-xl mx-auto mb-3">
          <span class="avatar-initial rounded-circle bg-label-primary">
            <i class="ti ti-brand-google ti-md"></i>
          </span>
        </div>
        <h5 class="mb-2">{{ __('app.Google synced contacts empty title') }}</h5>
        <p class="text-muted mb-4 mx-auto" style="max-width: 28rem;">{{ __('app.No synced contact rows yet. Connect Google in team settings and run sync (or wait for the scheduled job).') }}</p>
        <a href="{{ route('integrations.google.connect') }}" class="btn btn-primary">
          {{ $googleAccounts->isNotEmpty() ? __('app.Google integration reconnect') : __('app.Google integration connect') }}
        </a>
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>{{ __('app.Google contacts column name') }}</th>
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
                      <a href="{{ route('contact.show', $row->contact_id) }}" class="text-body" title="{{ __('app.View contact detail') }}" aria-label="{{ __('app.View contact detail') }}">
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
