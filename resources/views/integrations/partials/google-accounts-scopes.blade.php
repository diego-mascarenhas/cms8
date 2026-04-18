@php
  use App\Support\GoogleOAuthScopePresenter;
@endphp
@if ($googleAccounts->isNotEmpty())
<div class="card mb-4">
  <div class="card-header">
    <h6 class="mb-0">{{ __('app.Connected Google accounts (this team)') }}</h6>
    <small class="text-muted d-block mt-1">{{ __('app.Google OAuth scopes note') }}</small>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>{{ __('app.User') }}</th>
            <th>{{ __('app.Google permission column calendar') }}</th>
            <th>{{ __('app.Google permission column contacts') }}</th>
            <th>{{ __('app.Last account sync') }}</th>
            <th>{{ __('app.OAuth scopes granted') }}</th>
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
            @endphp
            <tr>
              <td>{{ $acct->user?->name ?? '—' }} <span class="text-muted small">({{ $acct->user?->email ?? '—' }})</span></td>
              <td>
                <span class="badge {{ $calBadge }}">{{ GoogleOAuthScopePresenter::calendarPermissionLabel($acct->scopes) }}</span>
              </td>
              <td>
                <span class="badge {{ $conBadge }}">{{ GoogleOAuthScopePresenter::contactsPermissionLabel($acct->scopes) }}</span>
              </td>
              <td>
                @if ($acct->last_synced_at)
                  {{ $acct->last_synced_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="small">
                @forelse ($scopeList as $s)
                  <div class="mb-1">
                    <span class="text-body">{{ GoogleOAuthScopePresenter::describeScope($s) }}</span>
                    <br><code class="text-muted small user-select-all">{{ $s }}</code>
                  </div>
                @empty
                  <span class="text-muted">—</span>
                @endforelse
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif
