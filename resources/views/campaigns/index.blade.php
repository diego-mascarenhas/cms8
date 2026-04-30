@extends('layouts/layoutMaster')

@section('title', __('Campaigns'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script>
    $(function ()
    {
        const campaignTypeFilter = $('#campaign-type-filter');
        const campaignStatusFilter = $('#campaign-status-filter');

        if (campaignTypeFilter.length && $.fn.select2)
        {
            campaignTypeFilter.select2({
                placeholder: @json(__('Type')),
                minimumResultsForSearch: Infinity,
                width: '170px',
            });
        }

        if (campaignStatusFilter.length && $.fn.select2)
        {
            campaignStatusFilter.select2({
                placeholder: @json(__('Status')),
                minimumResultsForSearch: Infinity,
                width: '170px',
            });
        }
    });
</script>
@endsection

@section('content')
@php
    $campaigns = [
        [
            'name' => 'Teacher onboarding flow',
            'type' => __('Sequences'),
            'summary' => __('7 emails over 150 days'),
            'sends' => 0,
            'opened' => null,
            'clicked' => null,
            'unsubscribed' => null,
            'status' => __('Active'),
            'status_class' => 'bg-label-success',
            'status_text' => 'text-success',
        ],
        [
            'name' => 'Why your students do not progress',
            'type' => __('Broadcasts'),
            'summary' => __('Scheduled for May 07, 2026 07:00 PM'),
            'sends' => null,
            'opened' => null,
            'clicked' => null,
            'unsubscribed' => null,
            'status' => __('Scheduled'),
            'status_class' => 'bg-label-warning',
            'status_text' => 'text-warning',
        ],
        [
            'name' => 'What I learned from new students',
            'type' => __('Broadcasts'),
            'summary' => __('Sent April 23, 2026 07:03 PM'),
            'sends' => 2381,
            'opened' => '20%',
            'clicked' => '0%',
            'unsubscribed' => '0%',
            'status' => __('Sent'),
            'status_class' => 'bg-label-info',
            'status_text' => 'text-info',
        ],
        [
            'name' => 'Core activation mistakes',
            'type' => __('Broadcasts'),
            'summary' => __('Sent April 15, 2026 06:03 PM'),
            'sends' => 2399,
            'opened' => '40%',
            'clicked' => '3%',
            'unsubscribed' => '0%',
            'status' => __('Sent'),
            'status_class' => 'bg-label-info',
            'status_text' => 'text-info',
        ],
    ];
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Email Campaigns') }}</h4>
        <p class="text-muted">{{ __('Create, schedule and track your campaigns in one place.') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
        <a href="#" class="btn btn-label-secondary">
            <i class="ti ti-pencil me-1"></i>{{ __('Manage Templates') }}
        </a>
        <a href="#" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>{{ __('New Email Campaign') }}
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 mb-4">
            <select id="campaign-type-filter" class="form-select">
                <option value="">{{ __('Type') }}</option>
                @foreach ($campaignTypes as $campaignType)
                    <option value="{{ $campaignType->value }}">{{ $campaignType->label() }}</option>
                @endforeach
            </select>
            <select id="campaign-status-filter" class="form-select">
                <option value="">{{ __('Status') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="scheduled">{{ __('Scheduled') }}</option>
                <option value="sent">{{ __('Sent') }}</option>
                <option value="paused">{{ __('Paused') }}</option>
            </select>
            <div class="input-group input-group-merge ms-lg-auto" style="max-width: 360px; width: 100%;">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input type="text" class="form-control" placeholder="{{ __('Search...') }}" />
            </div>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table border-top">
                <thead>
                    <tr>
                        <th>{{ __('Campaign') }}</th>
                        <th>{{ __('Performance') }}</th>
                        <th class="text-center">{{ __('Status') }}</th>
                        <th class="text-center">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($campaigns as $campaign)
                        <tr>
                            <td>
                                <div class="d-flex align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold">{{ $campaign['name'] }}</div>
                                        <small class="text-muted d-block">{{ $campaign['type'] }} - {{ $campaign['summary'] }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-3">
                                    <div>
                                        <small class="text-muted d-block">{{ __('Sends') }}</small>
                                        <span class="fw-medium">{{ $campaign['sends'] ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">{{ __('Opened') }}</small>
                                        <span class="fw-medium">{{ $campaign['opened'] ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">{{ __('Clicked') }}</small>
                                        <span class="fw-medium">{{ $campaign['clicked'] ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">{{ __('Unsubscribed') }}</small>
                                        <span class="fw-medium">{{ $campaign['unsubscribed'] ?? '-' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $campaign['status_class'] }} {{ $campaign['status_text'] }}">{{ $campaign['status'] }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-3">
                                    <a class="d-inline-flex align-items-center text-body" href="{{ route('campaigns.edit', ['campaign' => \Illuminate\Support\Str::slug($campaign['name'])]) }}" aria-label="{{ __('Edit') }}">
                                        <i class="ti ti-edit ti-sm"></i>
                                    </a>
                                    <a class="d-inline-flex align-items-center text-body" href="javascript:;" aria-label="{{ __('Report') }}">
                                        <i class="ti ti-chart-bar"></i>
                                    </a>
                                    <a class="d-inline-flex align-items-center text-body" href="javascript:;" aria-label="{{ __('Duplicate') }}">
                                        <i class="ti ti-copy"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            <nav aria-label="Campaign pagination">
                <ul class="pagination mb-0">
                    <li class="page-item disabled"><span class="page-link">{{ __('Back') }}</span></li>
                    <li class="page-item active"><span class="page-link">1</span></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#">4</a></li>
                    <li class="page-item"><a class="page-link" href="#">{{ __('Next') }}</a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>

@endsection
