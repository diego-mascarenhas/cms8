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
        <h4 class="mb-1 mt-3">Invoices</h4>
        <p class="text-muted">Manage invoices and payments</p>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger mb-4">
    {{ session('error') }}
</div>
@endif

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Total Paid</h5>
                    <h2 class="mb-0 text-success">${{ $stripeData['metrics']['total_paid'] }}</h2>
                    <small class="text-muted">{{ $stripeData['metrics']['total_invoices'] ?? 0 }} invoices</small>
                </div>
                <div class="avatar bg-label-success p-2">
                    <i class="ti ti-currency-dollar ti-md"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Unpaid</h5>
                    <h2 class="mb-0 text-warning">${{ $stripeData['metrics']['unpaid'] }}</h2>
                    <small class="text-muted">{{ $stripeData['metrics']['unpaid_invoices'] ?? 0 }} invoices</small>
                </div>
                <div class="avatar bg-label-warning p-2">
                    <i class="ti ti-alert-circle ti-md"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Invoices</h5>
    </div>
    <div class="card-datatable table-responsive">
        <table class="table table-hover border-top">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stripeData['invoices'] as $invoice)
                <tr>
                    <td>{{ $invoice['number'] }}</td>
                    <td>
                        <div class="d-flex flex-column">
                            <a href="{{ route('accounting.customer', $invoice['customer_id']) }}" class="text-body fw-semibold">
                                {{ $invoice['customer_name'] ?? 'Unknown' }}
                            </a>
                            <small class="text-muted">{{ $invoice['customer_email'] ?? '' }}</small>
                        </div>
                    </td>
                    <td>
                        <span class="fw-semibold">${{ number_format($invoice['amount'], 2) }}</span>
                        <small class="text-muted">{{ $invoice['currency'] }}</small>
                    </td>
                    <td>
                        @if($invoice['status'] === 'paid')
                        <span class="badge bg-label-success">Paid</span>
                        @elseif($invoice['status'] === 'open')
                        <span class="badge bg-label-warning">Open</span>
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
                @empty
                <tr>
                    <td colspan="6" class="text-center py-4">No invoices found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection 