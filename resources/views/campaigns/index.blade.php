@extends('layouts/layoutMaster')

@section('title', __('Campaigns'))

@section('content')
@php
    $campaigns = [
        [
            'name' => 'Teacher onboarding flow',
            'type' => __('Sequence'),
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
            'type' => __('Broadcast'),
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
            'type' => __('Broadcast'),
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
            'type' => __('Broadcast'),
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
        <ul class="nav nav-pills mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button">{{ __('All emails') }}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button">{{ __('Folders') }}</button>
            </li>
        </ul>

        <div class="row g-3 align-items-center mb-4">
            <div class="col-12 col-lg-8">
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary">
                        <i class="ti ti-filter me-1"></i>{{ __('Type') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary">
                        <i class="ti ti-filter me-1"></i>{{ __('Status') }}
                    </button>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti ti-search"></i></span>
                    <input type="text" class="form-control" placeholder="{{ __('Search...') }}" />
                </div>
            </div>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table border-top">
                <thead>
                    <tr>
                        <th>{{ __('Campaign') }}</th>
                        <th>{{ __('Performance') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-center">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($campaigns as $campaign)
                        <tr>
                            <td>
                                <div class="d-flex align-items-start gap-3">
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" />
                                    </div>
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
                            <td>
                                <span class="badge {{ $campaign['status_class'] }} {{ $campaign['status_text'] }}">{{ $campaign['status'] }}</span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center align-items-center">
                                    <a href="javascript:;" class="text-body me-2"><i class="ti ti-eye ti-sm"></i></a>
                                    <a href="javascript:;" class="text-body me-2"><i class="ti ti-edit ti-sm"></i></a>
                                    <a href="javascript:;" class="text-body"><i class="ti ti-dots-vertical ti-sm"></i></a>
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

<div class="text-center">
    <a href="https://help.kajabi.com/hc/en-us/articles/360036990014" class="text-primary" target="_blank" rel="noopener noreferrer">
        {{ __('Learn more about Email Campaigns') }} <i class="ti ti-external-link"></i>
    </a>
    <div class="card-body">
        <p class="text-muted mb-0">{{ __('Inspired by Kajabi campaign manager layout adapted to Vuexy.') }}</p>
    </div>
</div>
@endsection
