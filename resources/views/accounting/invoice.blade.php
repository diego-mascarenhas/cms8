@extends('layouts/layoutMaster')

@section('title', 'Invoice Details')

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
        <h4 class="mb-1 mt-3">Invoice Details</h4>
        <p class="text-muted">View invoice information</p>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger mb-4">
    {{ session('error') }}
</div>
@endif

<div class="mb-4">
    <a href="{{ route('accounting.index') }}" class="btn btn-outline-primary">
        <i class="ti ti-arrow-left me-1"></i> Back to Invoices
    </a>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Invoice #{{ $invoiceData['number'] }}</h5>
        <div>
            <span class="badge rounded-pill bg-{{ $invoiceData['status'] === 'paid' ? 'success' : ($invoiceData['status'] === 'open' ? 'warning' : 'secondary') }}">
                {{ ucfirst($invoiceData['status']) }}
            </span>
            <a href="{{ $invoiceData['pdf'] }}" target="_blank" class="btn btn-sm btn-primary ms-2">
                <i class="ti ti-download me-1"></i> Download PDF
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-4"></div>
            <div class="col-md-6">
                <h6 class="mb-3">Customer Information</h6>
                <div class="p-3 bg-lighter rounded">
                    <p class="mb-1"><strong>{{ $invoiceData['customer_name'] ?? 'Unknown Customer' }}</strong></p>
                    <p class="mb-0">{{ $invoiceData['customer_email'] ?? '' }}</p>
                    
                    @if(isset($invoiceData['customer_address']))
                    <div class="mt-2">
                        <p class="mb-1">{{ $invoiceData['customer_address']['line1'] ?? '' }}</p>
                        @if(isset($invoiceData['customer_address']['line2']))
                        <p class="mb-1">{{ $invoiceData['customer_address']['line2'] }}</p>
                        @endif
                        <p class="mb-1">
                            {{ $invoiceData['customer_address']['city'] ?? '' }}, 
                            {{ $invoiceData['customer_address']['state'] ?? '' }} 
                            {{ $invoiceData['customer_address']['postal_code'] ?? '' }}
                        </p>
                        <p class="mb-0">{{ $invoiceData['customer_address']['country'] ?? '' }}</p>
                    </div>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="mb-3">Invoice Summary</h6>
                <div class="p-3 bg-lighter rounded">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span>${{ number_format($invoiceData['subtotal'] / 100, 2) }}</span>
                    </div>
                    
                    @if(isset($invoiceData['tax']) && $invoiceData['tax'] > 0)
                    <div class="d-flex justify-content-between mb-2 border-top pt-2">
                        <span>Tax</span>
                        <span>${{ number_format($invoiceData['tax'] / 100, 2) }}</span>
                    </div>
                    @endif
                    
                    <div class="d-flex justify-content-between mb-2 border-top pt-2">
                        <span class="fw-bold">Total</span>
                        <span class="fw-bold">${{ number_format($invoiceData['total'] / 100, 2) }}</span>
                    </div>
                    
                    @if($invoiceData['status'] === 'paid')
                    <div class="d-flex justify-content-between border-top pt-2">
                        <span class="text-success">Paid</span>
                        <span class="text-success">${{ number_format($invoiceData['amount_paid'] / 100, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <h6 class="mb-3">Line Items</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoiceData['items'] as $item)
                    <tr>
                        <td>
                            <div>{{ $item['description'] }}</div>
                            @if(isset($item['period_start']) && isset($item['period_end']))
                            <small class="text-muted">{{ $item['period_start'] }} - {{ $item['period_end'] }}</small>
                            @endif
                        </td>
                        <td class="text-center">{{ $item['quantity'] }}</td>
                        <td class="text-end">${{ number_format($item['amount'] / $item['quantity'], 2) }}</td>
                        <td class="text-end">${{ number_format($item['amount'], 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted">No line items found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection 