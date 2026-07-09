@extends('layouts/layoutMaster')

@section('title', $campaign->name)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Paid Ads') }}/</span> {{ $campaign->name }}</h4>
        <p class="text-muted"><span class="badge {{ $campaign->status->badgeClasses() }}">{{ $campaign->status->label() }}</span> · {{ $campaign->objective?->label() }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2">
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
                <dl class="row mb-0">
                    <dt class="col-5">{{ __('Budget') }}</dt>
                    <dd class="col-7">{{ $campaign->budget_amount !== null ? number_format((float) $campaign->budget_amount, 2).' '.$campaign->currency : '—' }} <span class="text-muted small">{{ $campaign->budget_type === 'daily' ? __('daily') : __('lifetime') }}</span></dd>
                    <dt class="col-5">{{ __('Start date') }}</dt>
                    <dd class="col-7">{{ optional($campaign->start_at)->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-5">{{ __('End date') }}</dt>
                    <dd class="col-7">{{ optional($campaign->end_at)->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Created by') }}</dt>
                    <dd class="col-7">{{ $campaign->creator?->name ?? '—' }}</dd>
                </dl>
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
                    <th>{{ __('Platform') }}</th>
                    <th>{{ __('Ad account') }}</th>
                    <th>{{ __('Publish status') }}</th>
                    <th>{{ __('External ID') }}</th>
                    <th>{{ __('Last sync') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($campaign->platforms as $platform)
                    <tr>
                        <td><i class="{{ $platform->platform->icon() }} me-1"></i>{{ $platform->platform->label() }}</td>
                        <td>{{ $platform->connection?->ad_account_name ?? $platform->connection?->ad_account_id ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $platform->publish_status->badgeClasses() }}">{{ $platform->publish_status->label() }}</span>
                            @if ($platform->publish_error)
                                <i class="ti ti-alert-triangle text-danger ms-1" title="{{ $platform->publish_error }}"></i>
                            @endif
                        </td>
                        <td><code>{{ $platform->external_campaign_id ?? '—' }}</code></td>
                        <td>{{ optional($platform->last_synced_at)->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center">{{ __('No platforms attached.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
