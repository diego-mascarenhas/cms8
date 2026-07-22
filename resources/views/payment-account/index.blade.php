@extends('layouts/layoutMaster')

@section('title', 'Cuentas de pago')

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
        <h4 class="mb-1 mt-3">Cuentas de pago</h4>
        <p class="text-muted">Configura las cuentas de tu empresa y las formas de pago que acepta cada una.</p>
    </div>
    @can('create', \App\Models\PaymentAccount::class)
        <div class="mt-3 mt-md-0">
            <a href="{{ route('payment-account.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Nueva cuenta
            </a>
        </div>
    @endcan
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-body">
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
    </div>
</div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
