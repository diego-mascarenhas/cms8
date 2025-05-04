@extends('layouts.contentNavbarLayout')

@section('title', 'Servers')

@section('vendor-style')
<!-- Vendor CSS -->
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
<!-- Vendor JS -->
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
<!-- Page JS -->
{!! $dataTable->scripts() !!}
@endsection

@section('content')
<div class="container">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1>Servers</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('server.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Server
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{ $dataTable->table() }}
        </div>
    </div>
</div>
@endsection 