@extends('layouts/layoutMaster')

@section('title', __('Expenses'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Expenses') }}</h4>
        <p class="text-muted">{{ __('Manage your expenses and costs') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary me-2">
            <i class="ti ti-list me-1"></i> {{ __('All Payments') }}
        </a>
        <button class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('Add Expense') }}
        </button>
    </div>
</div>

<!-- Financial Metrics Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Current Month') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ number_format($currentMonthExpense, 2) }}</h3>
                            @if($percentageChange != 0)
                                <p class="mb-0 {{ $percentageChange < 0 ? 'text-success' : 'text-danger' }}">
                                    ({{ $percentageChange > 0 ? '+' : '' }}{{ number_format($percentageChange, 1) }}%)
                                </p>
                            @endif
                        </div>
                        <p class="mb-0">{{ __('vs last month') }}</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="ti ti-trending-down ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Year to Date') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ number_format($ytdExpense, 2) }}</h3>
                        </div>
                        <p class="mb-0">{{ date('Y') }}</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="ti ti-calendar-stats ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Total Expenses') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ number_format($totalExpense, 2) }}</h3>
                        </div>
                        <p class="mb-0">{{ __('All time') }}</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="ti ti-receipt ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('Accounts') }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ count($accounts) }}</h3>
                        </div>
                        <p class="mb-0">{{ __('Active accounts') }}</p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-wallet ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Account Balances -->
<div class="card mb-4">
    <div class="card-widget-separator-wrapper">
        <div class="card-body card-widget-separator">
            <div class="row gy-4 gy-sm-1">
                @foreach($accounts as $index => $account)
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start card-widget border-end pb-3 pb-sm-0">
                            <div>
                                <h4 class="mb-2">{{ number_format($account['balance'], 2) }}</h4>
                                <p class="mb-0 fw-medium">{{ $account['name'] }}</p>
                            </div>
                            <span class="avatar me-sm-4">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    {{ $account['currency_code'] }}
                                </span>
                            </span>
                        </div>
                        @if ($index < count($accounts) - 1)
                            <hr class="d-none d-sm-block d-lg-none me-4">
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Expense List Table -->
<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
