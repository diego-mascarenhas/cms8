@extends('layouts/layoutMaster')

@section('title', __('Invoices'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Invoices') }}</h4>
        <p class="text-muted">{{ __('Manage your invoices and billing') }}</p>
    </div>
    @can('invoice.create')
    <div class="mt-3 mt-md-0">
        <a href="{{ route('invoice.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('New Invoice') }}
        </a>
    </div>
    @endcan
</div>

<!-- Exchange Rates Cards -->
<div class="row g-4 mb-4">
    <!-- USD to ARS -->
    <div class="col-sm-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left w-100">
                        <span class="text-muted d-block mb-1">USD → ARS</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">
                                @if($exchangeRates['USD_ARS'])
                                    ${{ number_format($exchangeRates['USD_ARS'], 2) }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </h3>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">1 ARS = {{ $exchangeRates['USD_ARS'] ? number_format(1 / $exchangeRates['USD_ARS'], 6) : 'N/A' }} USD</small>
                            @if($lastUpdate)
                            <small class="text-muted ms-2 text-nowrap">
                                <i class="ti ti-clock ti-xs me-1"></i>{{ $lastUpdate->fetched_at->diffForHumans() }}
                            </small>
                            @endif
                        </div>
                    </div>
                    <span class="badge bg-label-success rounded p-2">
                        <i class="ti ti-currency-dollar ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- USD to EUR -->
    <div class="col-sm-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left w-100">
                        <span class="text-muted d-block mb-1">USD → EUR</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">
                                @if($exchangeRates['USD_EUR'])
                                    €{{ number_format($exchangeRates['USD_EUR'], 4) }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </h3>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">1 EUR = {{ $exchangeRates['USD_EUR'] ? number_format(1 / $exchangeRates['USD_EUR'], 4) : 'N/A' }} USD</small>
                            @if($lastUpdate)
                            <small class="text-muted ms-2 text-nowrap">
                                <i class="ti ti-clock ti-xs me-1"></i>{{ $lastUpdate->fetched_at->diffForHumans() }}
                            </small>
                            @endif
                        </div>
                    </div>
                    <span class="badge bg-label-primary rounded p-2">
                        <i class="ti ti-currency-euro ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ARS to EUR -->
    <div class="col-sm-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left w-100">
                        <span class="text-muted d-block mb-1">ARS → EUR</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">
                                @if($exchangeRates['ARS_EUR'])
                                    ${{ number_format(1 / $exchangeRates['ARS_EUR'], 2) }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </h3>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">1 ARS = {{ $exchangeRates['ARS_EUR'] ? number_format($exchangeRates['ARS_EUR'], 6) : 'N/A' }} EUR</small>
                            @if($lastUpdate)
                            <small class="text-muted ms-2 text-nowrap">
                                <i class="ti ti-clock ti-xs me-1"></i>{{ $lastUpdate->fetched_at->diffForHumans() }}
                            </small>
                            @endif
                        </div>
                    </div>
                    <span class="badge bg-label-info rounded p-2">
                        <i class="ti ti-currency ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush


