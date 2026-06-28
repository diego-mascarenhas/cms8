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
        <p class="text-muted small mb-0">Totales en {{ $reportingCurrency }} (tipos de cambio del sistema).</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary me-2">
            <i class="ti ti-list me-1"></i> Todos los pagos
        </a>
        <a href="{{ route('expense.create') }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-plus me-1"></i> Añadir gasto
        </a>
    </div>
</div>

<!-- Financial Metrics Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Mes actual</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $formatCardAmount($currentMonthExpense) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                            @if($percentageChange != 0)
                                <p class="mb-0 {{ $percentageChange < 0 ? 'text-success' : 'text-danger' }}">
                                    ({{ $percentageChange > 0 ? '+' : '' }}{{ number_format($percentageChange, 1) }}%)
                                </p>
                            @endif
                        </div>
                        <p class="mb-0">vs mes anterior</p>
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
                        <span>Año en curso</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $formatCardAmount($ytdExpense) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
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
                        <span>Total gastos</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ $formatCardAmount($totalExpense) }} <small class="text-muted fs-6">{{ $reportingCurrency }}</small></h3>
                        </div>
                        <p class="mb-0">Histórico</p>
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
                        <span>Cuentas</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">{{ count($accounts) }}</h3>
                        </div>
                        <p class="mb-0">Cuentas activas</p>
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
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
