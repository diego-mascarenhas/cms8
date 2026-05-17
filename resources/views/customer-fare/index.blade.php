@extends('layouts/layoutMaster')

@section('title', 'Tarifas')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
<h4 class="fw-bold py-3 mb-4">
    <span class="text-muted fw-light">Tarifas /</span> Lista
</h4>

<!-- DataTable Card -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Tarifas</h5>
        <a href="{{ route('customer-fare.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nueva Tarifa
        </a>
    </div>
    <div class="card-body">
        {!! $dataTable->table(['class' => 'table table-striped table-bordered table-hover']) !!}
    </div>
</div>
<!--/ DataTable Card -->

{!! $dataTable->scripts() !!}
@endsection 