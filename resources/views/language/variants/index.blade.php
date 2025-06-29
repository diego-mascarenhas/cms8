@extends('layouts/layoutMaster')

@section('title', 'Variantes de Idioma')

@section('vendor-style')
	<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
	<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
	<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
	<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}" />
@endsection

@section('vendor-script')
	<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
	<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-script')
	<script src="{{asset('assets/js/tables-datatables-basic.js')}}"></script>
@endsection

@section('content')
	<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
		<div class="d-flex flex-column justify-content-center">
			<h4 class="mb-1 mt-3">Variantes de Idioma</h4>
			<p class="text-muted">Gestión de variantes de idioma disponibles</p>
		</div>
		<div class="mt-3 mt-md-0">
			<a href="{{ route('language-variants.create') }}" class="btn btn-primary"> <i class="ti ti-plus me-1"></i> Nueva Variante </a>
		</div>
	</div>

	@if(session('success'))
		<div class="alert alert-success">
			{{ session('success') }}
		</div>
	@endif

	<div class="card">
		<div class="card-body">
			{{ $dataTable->table(['class' => 'table table-hover']) }}
		</div>
	</div>

	@push('scripts')
		{{ $dataTable->scripts() }}
	@endpush
@endsection