@extends('layouts/layoutMaster')

@section('title', __('Paid Ads dashboard'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Paid Ads dashboard') }}</h4>
        <p class="text-muted">{{ __('Unified metrics across all connected ad platforms') }}</p>
    </div>
    <a href="{{ route('paid-ads.index') }}" class="btn btn-label-secondary mt-3 mt-md-0">{{ __('Back to campaigns') }}</a>
</div>

<div class="row mb-4">
    <div class="col-md-3 col-6 mb-3">
        <div class="card"><div class="card-body">
            <small class="text-muted">{{ __('Total spend') }}</small>
            <h4 class="mb-0">{{ number_format($totals['spend'], 2) }}</h4>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card"><div class="card-body">
            <small class="text-muted">{{ __('Impressions') }}</small>
            <h4 class="mb-0">{{ number_format($totals['impressions']) }}</h4>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card"><div class="card-body">
            <small class="text-muted">{{ __('Clicks') }}</small>
            <h4 class="mb-0">{{ number_format($totals['clicks']) }} <span class="text-muted small">({{ number_format($totals['ctr'], 2) }}% CTR)</span></h4>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card"><div class="card-body">
            <small class="text-muted">{{ __('Active campaigns') }}</small>
            <h4 class="mb-0">{{ number_format($activeCampaigns) }}</h4>
        </div></div>
    </div>
</div>

<div class="card">
    <h5 class="card-header">{{ __('Breakdown by platform') }}</h5>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Platform') }}</th>
                    <th class="text-end">{{ __('Impressions') }}</th>
                    <th class="text-end">{{ __('Clicks') }}</th>
                    <th class="text-end">{{ __('Spend') }}</th>
                    <th class="text-end">{{ __('Conversions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($breakdown as $row)
                    <tr>
                        <td><i class="{{ $row['icon'] }} me-1"></i>{{ $row['label'] }}</td>
                        <td class="text-end">{{ number_format($row['impressions']) }}</td>
                        <td class="text-end">{{ number_format($row['clicks']) }}</td>
                        <td class="text-end">{{ number_format($row['spend'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['conversions']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center">{{ __('No metrics yet. Publish a campaign and sync metrics to see data here.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
