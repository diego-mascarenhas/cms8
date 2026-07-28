@extends('layouts/layoutMaster')

@section('title', 'Gastos')

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
@php
    $formatCardAmount = static fn (float $amount): string => \App\Helpers\Helpers::formatDecimal($amount, 0);
@endphp
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Gastos</h4>
        <p class="text-muted">Gestiona tus gastos y costes</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
        @include('partials.vat-period-selector')
        <div class="d-flex flex-wrap gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="expenseExportDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti ti-download me-1"></i> {{ __('Export') }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="expenseExportDropdown">
                    <li>
                        <a class="dropdown-item" href="{{ route('expense.export-hacienda', ['vat_year' => $vatYear, 'vat_period' => $vatPeriod]) }}">
                            <i class="ti ti-file-invoice me-2"></i> {{ __('Invoices') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('expense.export-credit-notes', ['vat_year' => $vatYear, 'vat_period' => $vatPeriod]) }}">
                            <i class="ti ti-receipt-refund me-2"></i> {{ __('Credit notes') }}
                        </a>
                    </li>
                </ul>
            </div>
            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-list me-1"></i> Todos los pagos
            </a>
            <a href="{{ route('expense.create') }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-plus me-1"></i> Añadir gasto
            </a>
        </div>
    </div>
</div>

<!-- Financial Metrics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ $periodLabel }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $formatCardAmount($periodExpense) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                            @if($percentageChange != 0)
                                <p class="mb-0 {{ $percentageChange < 0 ? 'text-success' : 'text-danger' }}">
                                    ({{ $percentageChange > 0 ? '+' : '' }}{{ number_format($percentageChange, 1) }}%)
                                </p>
                            @endif
                        </div>
                        <p class="mb-0">
                            {{ $vatMode === 'quarter' ? 'vs trimestre anterior' : 'vs mes anterior' }}:
                            {{ $formatCardAmount($previousPeriodExpense) }} {{ $reportingCurrency }}
                        </p>
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
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ __('I.V.A.') }} {{ $periodLabel }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $formatCardAmount($selectedVat) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                            @if($vatPercentageChange != 0)
                                <p class="mb-0 {{ $vatPercentageChange < 0 ? 'text-success' : 'text-danger' }}">
                                    ({{ $vatPercentageChange > 0 ? '+' : '' }}{{ number_format($vatPercentageChange, 1) }}%)
                                </p>
                            @endif
                        </div>
                        <p class="mb-0">
                            vs año anterior: {{ $formatCardAmount($previousYearVat) }} {{ $reportingCurrency }}
                        </p>
                    </div>
                    <div class="avatar">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="ti {{ $vatMode === 'quarter' ? 'ti-calendar-event' : 'ti-receipt-tax' }} ti-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>{{ $vatYear }}</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $formatCardAmount($yearExpense) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                            @if($yearPercentageChange != 0)
                                <p class="mb-0 {{ $yearPercentageChange < 0 ? 'text-success' : 'text-danger' }}">
                                    ({{ $yearPercentageChange > 0 ? '+' : '' }}{{ number_format($yearPercentageChange, 1) }}%)
                                </p>
                            @endif
                        </div>
                        <p class="mb-0">
                            vs año anterior: {{ $formatCardAmount($previousYearExpense) }} {{ $reportingCurrency }}
                        </p>
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
</div>

<!-- Account Balances -->
<div class="card mb-4">
    <div class="card-widget-separator-wrapper">
        <div class="card-body card-widget-separator">
            <div class="row gy-4 gy-sm-1">
                @foreach($accounts as $index => $account)
                    <div class="col-sm-6 col-lg-3">
                        <a href="{{ route('payment-account.show', $account['id']) }}" class="text-body text-decoration-none d-block">
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
                        </a>
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
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
