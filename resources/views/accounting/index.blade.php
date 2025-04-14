@extends('layouts/layoutMaster')

@section('title', 'Accounting - Invoices')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Saldo</h4>
        <p class="text-muted">Gestionar facturas y pagos</p>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger mb-4">
    {{ session('error') }}
</div>
@endif

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Facturas</h5>
                    <h2 class="mb-0">{{ ($stripeData['metrics']['total_invoices'] ?? 0) + ($stripeData['metrics']['unpaid_invoices'] ?? 0) }}</h2>
                    <small class="text-muted">Total facturas</small>
                </div>
                <div class="avatar bg-label-primary p-2">
                    <i class="ti ti-file ti-md"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Pagado</h5>
                    <h2 class="mb-0 text-success">{{ $stripeData['metrics']['total_paid'] }}€</h2>
                    <small class="text-muted">{{ $stripeData['metrics']['total_invoices'] ?? 0 }} facturas</small>
                </div>
                <div class="avatar bg-label-success p-2">
                    <i class="ti ti-currency-euro ti-md"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Pendiente</h5>
                    <h2 class="mb-0 text-warning">{{ $stripeData['metrics']['unpaid'] }}€</h2>
                    <small class="text-muted">{{ $stripeData['metrics']['unpaid_invoices'] ?? 0 }} facturas</small>
                </div>
                <div class="avatar bg-label-warning p-2">
                    <i class="ti ti-alert-circle ti-md"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Incobrable</h5>
                    <h2 class="mb-0 text-danger">0.00€</h2>
                    <small class="text-muted">0 facturas</small>
                </div>
                <div class="avatar bg-label-danger p-2">
                    <i class="ti ti-receipt-off ti-md"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Facturas</h5>
    </div>
    <div class="card-datatable table-responsive">
        @forelse($stripeData['grouped_invoices'] as $quarter => $invoices)
            <div class="d-flex align-items-center bg-light p-2 border-bottom">
                <i class="ti ti-calendar-stats me-2"></i>
                <h6 class="mb-0">{{ $quarter }}</h6>
            </div>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Importe</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice['number'] }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <a href="{{ route('accounting.customer', $invoice['customer_id']) }}" class="text-body fw-semibold">
                                    {{ $invoice['customer_name'] ?? 'Desconocido' }}
                                </a>
                                <small class="text-muted">{{ $invoice['customer_email'] ?? '' }}</small>
                            </div>
                        </td>
                        <td>
                            <span class="fw-semibold">{{ number_format($invoice['amount'], 2) }}€</span>
                            <small class="text-muted">{{ $invoice['currency'] }}</small>
                        </td>
                        <td>
                            @if($invoice['status'] === 'paid')
                            <span class="badge bg-label-success">Pagado</span>
                            @elseif($invoice['status'] === 'open')
                            <span class="badge bg-label-warning">Pendiente</span>
                            @else
                            <span class="badge bg-label-secondary">{{ ucfirst($invoice['status']) }}</span>
                            @endif
                        </td>
                        <td>{{ $invoice['date'] }}</td>
                        <td>
                            <div class="d-flex justify-content-center">
                                <a href="{{ route('accounting.invoice', $invoice['id']) }}" class="btn btn-sm btn-icon">
                                    <i class="ti ti-eye text-primary"></i>
                                </a>
                                <a href="{{ $invoice['pdf'] }}" target="_blank" class="btn btn-sm btn-icon">
                                    <i class="ti ti-download text-success"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <div class="text-center py-5">
                <i class="ti ti-file-x fs-1 text-secondary mb-2"></i>
                <p>No se encontraron facturas</p>
            </div>
        @endforelse
    </div>
</div>
@endsection 