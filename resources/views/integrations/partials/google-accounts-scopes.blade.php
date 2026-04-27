@php
  use App\Support\GoogleOAuthScopePresenter;
  use Illuminate\Support\Str;
@endphp
@if ($googleAccounts->isNotEmpty())
<div class="card mb-4">
  <div class="card-header">
    <h6 class="mb-0">{{ __('app.Connected Google accounts (this team)') }}</h6>
    <small class="text-muted d-block mt-1">{{ __('app.Google OAuth scopes note') }}</small>
  </div>
  <div class="card-body pt-0 px-0 pb-2">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr class="align-middle">
            <th scope="col" class="py-2 fw-normal small text-muted">{{ __('app.User') }}</th>
            <th scope="col" class="py-2 fw-normal small text-muted">{{ __('app.Google accounts table_calendar') }}</th>
            <th scope="col" class="py-2 fw-normal small text-muted">{{ __('app.Google accounts table_contacts') }}</th>
            <th scope="col" class="py-2 fw-normal small text-muted">{{ __('app.Google accounts table_sync') }}</th>
            <th scope="col" class="py-2 fw-normal small text-muted text-center">{{ __('app.Google accounts table_scope') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($googleAccounts as $acct)
            @php
              $scopeList = GoogleOAuthScopePresenter::normalized($acct->scopes);
              $calLv = GoogleOAuthScopePresenter::calendarAccessLevel($acct->scopes);
              $conLv = GoogleOAuthScopePresenter::contactsAccessLevel($acct->scopes);
              $calBadge = match ($calLv) {
                  'write' => 'bg-label-success',
                  'readonly' => 'bg-label-warning',
                  default => 'bg-label-secondary',
              };
              $conBadge = match ($conLv) {
                  'write' => 'bg-label-success',
                  'readonly' => 'bg-label-warning',
                  default => 'bg-label-secondary',
              };
              $syncFormatted = null;
              if ($acct->last_synced_at)
              {
                  $syncAt = $acct->last_synced_at->copy()->timezone(config('app.timezone'))->locale(app()->getLocale());
                  $syncFormatted = Str::startsWith((string) app()->getLocale(), 'es')
                      ? $syncAt->translatedFormat('j \d\e F \d\e Y, H:i')
                      : $syncAt->translatedFormat('M j, Y, H:i');
              }
            @endphp
            <tr class="align-middle">
              <td>{{ $acct->user?->name ?? '—' }} <span class="text-muted small">({{ $acct->user?->email ?? '—' }})</span></td>
              <td>
                <span class="badge {{ $calBadge }}">{{ GoogleOAuthScopePresenter::calendarBadgeLabel($acct->scopes) }}</span>
              </td>
              <td>
                <span class="badge {{ $conBadge }}">{{ GoogleOAuthScopePresenter::contactsBadgeLabel($acct->scopes) }}</span>
              </td>
              <td>
                @if ($syncFormatted !== null)
                  {{ $syncFormatted }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-nowrap text-center">
                <button type="button" class="btn btn-sm btn-icon btn-text-secondary border-0 shadow-none" data-bs-toggle="modal" data-bs-target="#googleAccountScopesModal-{{ $acct->id }}" title="{{ __('app.Google scopes modal open') }}" aria-label="{{ __('app.Google scopes modal open') }}">
                  <i class="ti ti-info-circle ti-sm"></i>
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @foreach ($googleAccounts as $acct)
      @php
        $modalScopes = GoogleOAuthScopePresenter::normalized($acct->scopes);
      @endphp
      <div class="modal modal-transparent fade" id="googleAccountScopesModal-{{ $acct->id }}" tabindex="-1" aria-labelledby="googleAccountScopesModalLabel-{{ $acct->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
          <div class="modal-content border-0 shadow-none bg-transparent">
            <div class="modal-body position-relative px-3 px-sm-4 py-4">
              <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
              <div class="text-center mb-4 pt-2">
                <span class="avatar avatar-xl mx-auto">
                  <span class="avatar-initial rounded-circle bg-primary">
                    <i class="ti ti-info-circle ti-md text-white" aria-hidden="true"></i>
                  </span>
                </span>
                <span id="googleAccountScopesModalLabel-{{ $acct->id }}" class="visually-hidden">{{ __('app.Google scopes modal title') }}</span>
              </div>
              <div class="rounded-3 bg-body p-4 mx-auto w-100">
                <p class="text-muted small mb-3 text-center">{{ $acct->user?->name ?? '—' }} <span class="text-muted">({{ $acct->user?->email ?? '—' }})</span></p>
                @forelse ($modalScopes as $s)
                  <div class="mb-4 pb-1">
                    <div class="fw-medium">{{ GoogleOAuthScopePresenter::describeScope($s) }}</div>
                    <code class="text-muted small user-select-all d-block mt-1">{{ $s }}</code>
                  </div>
                @empty
                  <p class="text-muted mb-0 text-center">{{ __('app.Google scopes modal empty') }}</p>
                @endforelse
              </div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endif
