@extends('layouts/layoutMaster')

@section('title', __('payment_sync.reconcile.title'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('payment_sync.reconcile.title') }}</h4>
        <p class="text-muted">{{ __('payment_sync.reconcile.subtitle_table') }}</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
        <a href="{{ route('payments.reconcile', ['rebuild' => 1]) }}" class="btn btn-label-primary">
            <i class="ti ti-refresh me-1"></i>{{ __('payment_sync.reconcile.rebuild') }}
        </a>
        <a href="{{ route('payments.syncs.mercadopago.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-list me-1"></i>{{ __('payment_sync.reconcile.full_queue') }}
        </a>
        <a href="{{ route('payments.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning alert-dismissible" role="alert">
        {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="alert alert-info" role="alert">
    {{ __('payment_sync.reconcile.table_hint') }}
</div>

<div class="card">
    <div class="card-body">
        {!! $dataTable->table(['class' => 'table table-hover']) !!}
    </div>
</div>
@endsection

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-script')
{!! $dataTable->scripts() !!}
@endsection
