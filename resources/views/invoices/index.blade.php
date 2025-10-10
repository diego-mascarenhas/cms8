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


