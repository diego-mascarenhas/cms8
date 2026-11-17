@extends('layouts/layoutMaster')

@section('title', $campaign->name)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Paid Ads') }}/</span> {{ $campaign->name }}</h4>
        <p class="text-muted"><span class="badge {{ $campaign->status->badgeClasses() }}">{{ $campaign->status->label() }}</span> · {{ $campaign->objective?->label() }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2">
        <a href="{{ route('paid-ads.index') }}" class="btn btn-label-secondary"><i class="ti ti-arrow-left me-1"></i>{{ __('Back to campaigns') }}</a>
        @can('publish', $campaign)
            @if (in_array($campaign->status->value, ['draft', 'failed'], true))
                <form action="{{ route('paid-ads.publish', $campaign->id) }}" method="POST">@csrf
                    <button type="submit" class="btn btn-success"><i class="ti ti-rocket me-1"></i>{{ __('Publish') }}</button>
                </form>
            @elseif ($campaign->status->value === 'active')
                <form action="{{ route('paid-ads.pause', $campaign->id) }}" method="POST">@csrf
                    <button type="submit" class="btn btn-warning"><i class="ti ti-player-pause me-1"></i>{{ __('Pause') }}</button>
                </form>
            @elseif ($campaign->status->value === 'paused')
                <form action="{{ route('paid-ads.resume', $campaign->id) }}" method="POST">@csrf
                    <button type="submit" class="btn btn-success"><i class="ti ti-player-play me-1"></i>{{ __('Resume') }}</button>
                </form>
            @endif
        @endcan
        <form action="{{ route('paid-ads.sync-metrics', $campaign->id) }}" method="POST">@csrf
            <button type="submit" class="btn btn-label-secondary"><i class="ti ti-refresh me-1"></i>{{ __('Sync metrics') }}</button>
        </form>
        @can('update', $campaign)
            <a href="{{ route('paid-ads.edit', $campaign->id) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>{{ __('Edit') }}</a>
        @endcan
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <h5 class="card-header">{{ __('Details') }}</h5>
            <div class="card-body">
                <div class="row g-3">
                    @if ($campaign->budget_amount !== null)
                        <div class="col-6">
                            <div class="text-muted text-uppercase small mb-1">{{ __('Budget') }}</div>
                            <div>{{ number_format((float) $campaign->budget_amount, 2) }} {{ $campaign->currency }} <span class="text-muted small">{{ $campaign->budget_type === 'daily' ? __('daily') : __('lifetime') }}</span></div>
                        </div>
                    @endif
                    @if ($campaign->start_at)
                        <div class="col-6">
                            <div class="text-muted text-uppercase small mb-1">{{ __('Campaign start') }}</div>
                            <div>{{ $campaign->start_at->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</div>
                        </div>
                    @endif
                    @if ($campaign->end_at)
                        <div class="col-6">
                            <div class="text-muted text-uppercase small mb-1">{{ __('End') }}</div>
                            <div>{{ $campaign->end_at->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</div>
                        </div>
                    @endif
                    @if ($campaign->creator?->name)
                        <div class="col-6">
                            <div class="text-muted text-uppercase small mb-1">{{ __('Created') }}</div>
                            <div>{{ $campaign->creator->name }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <h5 class="card-header">{{ __('Performance') }}</h5>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col"><h4 class="mb-0">{{ number_format($metrics['impressions']) }}</h4><small class="text-muted">{{ __('Impressions') }}</small></div>
                    <div class="col"><h4 class="mb-0">{{ number_format($metrics['clicks']) }}</h4><small class="text-muted">{{ __('Clicks') }}</small></div>
                    <div class="col"><h4 class="mb-0">{{ number_format($metrics['spend'], 2) }} {{ $campaign->currency }}</h4><small class="text-muted">{{ __('Spend') }}</small></div>
                    <div class="col"><h4 class="mb-0">{{ number_format($metrics['conversions']) }}</h4><small class="text-muted">{{ __('Conversions') }}</small></div>
                    <div class="col"><h4 class="mb-0">{{ number_format($metrics['ctr'], 2) }}%</h4><small class="text-muted">{{ __('CTR') }}</small></div>
                    <div class="col"><h4 class="mb-0">{{ number_format($metrics['cpc'], 2) }}</h4><small class="text-muted">{{ __('CPC') }}</small></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Platform status') }}</h5>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th class="text-uppercase">{{ __('Platform') }}</th>
                    <th class="text-uppercase">{{ __('Account') }}</th>
                    <th class="text-uppercase">{{ __('Status') }}</th>
                    <th class="text-uppercase">{{ __('Sync') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($campaign->platforms as $platform)
                    <tr>
                        <td>
                            <i class="{{ $platform->platform->icon() }} me-1"></i>{{ $platform->platform->label() }}
                            @if ($platform->external_campaign_id)
                                <i class="ti ti-info-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $platform->external_campaign_id }}" aria-label="{{ $platform->external_campaign_id }}"></i>
                            @endif
                        </td>
                        <td>{{ $platform->connection?->ad_account_name ?? $platform->connection?->ad_account_id }}</td>
                        <td>
                            <span class="badge {{ $platform->publish_status->badgeClasses() }}">{{ $platform->publish_status->label() }}</span>
                            @if ($platform->publish_error)
                                <i class="ti ti-alert-triangle text-danger ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $platform->publish_error }}"></i>
                            @endif
                        </td>
                        <td>{{ $platform->last_synced_at?->locale('es')->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center">{{ __('No platforms attached.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
