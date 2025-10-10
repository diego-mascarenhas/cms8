@extends('layouts/layoutMaster')

@section('title', __('Payments'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Payments') }}</h4>
        <p class="text-muted">{{ __('Manage your payments') }}</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        {!! $dataTable->table(['class' => 'table']) !!}
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

