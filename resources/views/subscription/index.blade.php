@extends('layouts/layoutMaster')

@section('title', __('stripe_subscription.title'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('stripe_subscription.title') }}</h4>
        <p class="text-muted">{{ __('stripe_subscription.subtitle') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <form action="{{ route('subscription.sync') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-refresh me-1"></i>{{ __('stripe_subscription.sync_button') }}
            </button>
        </form>
    </div>
</div>

@php
    $statusIcons = [
        'active' => ['icon' => 'ti-circle-check', 'bg' => 'bg-label-success', 'text' => 'text-success'],
        'trialing' => ['icon' => 'ti-hourglass', 'bg' => 'bg-label-info', 'text' => 'text-info'],
        'past_due' => ['icon' => 'ti-alert-triangle', 'bg' => 'bg-label-warning', 'text' => 'text-warning'],
        'unpaid' => ['icon' => 'ti-credit-card-off', 'bg' => 'bg-label-danger', 'text' => 'text-danger'],
        'incomplete' => ['icon' => 'ti-progress', 'bg' => 'bg-label-dark', 'text' => 'text-dark'],
        'incomplete_expired' => ['icon' => 'ti-clock-x', 'bg' => 'bg-label-secondary', 'text' => 'text-secondary'],
        'canceled' => ['icon' => 'ti-circle-x', 'bg' => 'bg-label-danger', 'text' => 'text-danger'],
        'paused' => ['icon' => 'ti-player-pause', 'bg' => 'bg-label-secondary', 'text' => 'text-secondary'],
    ];
@endphp
<div class="row g-3 mb-3">
    @foreach(($subscriptionStatuses ?? []) as $statusKey)
        @php
            $iconMeta = $statusIcons[$statusKey] ?? ['icon' => 'ti-circle', 'bg' => 'bg-label-primary', 'text' => 'text-primary'];
        @endphp
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-muted small">{{ __('stripe_subscription.status.'.$statusKey) }}</span>
                        <span class="avatar avatar-sm">
                            <span class="avatar-initial rounded {{ $iconMeta['bg'] }}">
                                <i class="ti {{ $iconMeta['icon'] }} {{ $iconMeta['text'] }}"></i>
                            </span>
                        </span>
                    </div>
                    <h4 class="mb-0 mt-2">{{ number_format((int) ($statusCounts[$statusKey] ?? 0)) }}</h4>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
