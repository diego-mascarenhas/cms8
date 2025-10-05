@extends('layouts/layoutMaster')

@section('title', 'Customer Invoices')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
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
        <h4 class="mb-1 mt-3">Customer Invoices</h4>
        <p class="text-muted">View invoices for {{ $stripeData['customer']['name'] ?? 'customer' }}</p>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger mb-4"></div>
    {{ session('error') }}
</div>
@endif

<div class="mb-4">
    <a href="{{ route('accounting.index') }}" class="btn btn-outline-primary">
        <i class="ti ti-arrow-left me-1"></i> Back to Invoices
    </a>
</div>

<!-- Customer Info -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Customer Information</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <h6 class="fw-semibold mb-2">{{ $stripeData['customer']['name'] ?? 'Unknown Customer' }}</h6>
                    <p class="mb-1">{{ $stripeData['customer']['email'] ?? '' }}</p>
                    
                    @if($enterprise)
                    <div class="mt-2">
                        <a href="{{ route('contact.show', $enterprise->responsible_id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-user me-1"></i> View in CRM
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Customer since:</small>
                            <h6>{{ $stripeData['customer']['created'] ?? 'N/A' }}</h6>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Total Paid:</small>
                            <h5 class="text-success">${{ $stripeData['metrics']['total_paid'] }}</h5>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Unpaid:</small>
                            <h5 class="text-warning">${{ $stripeData['metrics']['unpaid'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoices -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Invoices</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover border-top">
            <thead>
                <tr>
                    <th>Number</th>
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
                    <td colspan="5" class="text-center py-4">No invoices found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection 