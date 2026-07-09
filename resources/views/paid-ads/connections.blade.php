@extends('layouts/layoutMaster')

@section('title', __('Ad platform connections'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Ad platform connections') }}</h4>
        <p class="text-muted">{{ __('Connect your ad accounts to publish campaigns.') }}</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        @if (auth()->user()->currentTeam)
            <a href="{{ route('team-settings.edit', ['team' => auth()->user()->currentTeam, 'group' => 'paid_ads']) }}" class="btn btn-label-primary"><i class="ti ti-key me-1"></i>{{ __('Configure credentials') }}</a>
        @endif
        <a href="{{ route('paid-ads.index') }}" class="btn btn-label-secondary">{{ __('Back to campaigns') }}</a>
    </div>
</div>

<div class="alert alert-info">
    {{ __('Each platform uses the API credentials configured in this team\'s settings. Add them per team, then connect the ad account below.') }}
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif

<div class="row">
    @foreach ($platforms as $entry)
        @php($platform = $entry['platform'])
        @php($connection = $entry['connection'])
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-label-primary rounded p-2 me-2"><i class="{{ $platform->icon() }} ti-md"></i></span>
                        <h5 class="mb-0">{{ $platform->label() }}</h5>
                    </div>

                    @if (! $entry['enabled'])
                        <span class="badge bg-label-secondary mb-2">{{ __('Not available') }}</span>
                        <p class="text-muted small mb-0">{{ __('This platform requires approved API access and is disabled by feature flag.') }}</p>
                    @elseif (! $entry['configured'])
                        <span class="badge bg-label-warning mb-2">{{ __('Not configured') }}</span>
                        <p class="text-muted small mb-2">{{ __('Add this platform\'s API credentials in the team settings.') }}</p>
                        @if (auth()->user()->currentTeam)
                            <a href="{{ route('team-settings.edit', ['team' => auth()->user()->currentTeam, 'group' => 'paid_ads']) }}" class="btn btn-label-primary btn-sm"><i class="ti ti-key me-1"></i>{{ __('Configure credentials') }}</a>
                        @endif
                    @elseif ($connection === null)
                        <span class="badge bg-label-secondary mb-2">{{ __('Disconnected') }}</span>
                        <p class="text-muted small">{{ __('No account connected yet.') }}</p>
                        <a href="{{ route('integrations.ad-platforms.connect', $platform->value) }}" class="btn btn-primary btn-sm">
                            <i class="ti ti-plug me-1"></i>{{ __('Connect') }}
                        </a>
                    @else
                        <span class="badge {{ $connection->status->badgeClasses() }} mb-2">{{ $connection->status->label() }}</span>

                        @if ($connection->status->value === 'pending_account')
                            <form action="{{ route('integrations.ad-platforms.select-account', $connection->id) }}" method="POST" class="mt-2">
                                @csrf
                                <label class="form-label small">{{ __('Select ad account') }}</label>
                                @if (! empty($entry['accounts']))
                                    <select name="ad_account_id" class="form-select form-select-sm mb-2" required
                                        onchange="this.nextElementSibling.value = this.options[this.selectedIndex].dataset.name || this.value">
                                        <option value="">{{ __('Choose...') }}</option>
                                        @foreach ($entry['accounts'] as $account)
                                            <option value="{{ $account['id'] }}" data-name="{{ $account['name'] }}">{{ $account['name'] }} ({{ $account['id'] }})</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="ad_account_name" value="">
                                @else
                                    <input type="text" name="ad_account_id" class="form-control form-control-sm mb-2" placeholder="{{ __('Ad account ID') }}" required>
                                @endif
                                <button type="submit" class="btn btn-success btn-sm">{{ __('Save account') }}</button>
                            </form>
                        @else
                            <dl class="row mb-2 small">
                                <dt class="col-5">{{ __('Ad account') }}</dt>
                                <dd class="col-7">{{ $connection->ad_account_name ?? $connection->ad_account_id }}</dd>
                                <dt class="col-5">{{ __('Connected by') }}</dt>
                                <dd class="col-7">{{ $connection->user?->name ?? '—' }}</dd>
                            </dl>
                        @endif

                        <form action="{{ route('integrations.ad-platforms.disconnect', $connection->id) }}" method="POST" class="mt-2"
                            onsubmit="return confirm('{{ __('Disconnect this platform?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-label-danger btn-sm"><i class="ti ti-unlink me-1"></i>{{ __('Disconnect') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
