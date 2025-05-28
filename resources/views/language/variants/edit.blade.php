@extends('layouts/layoutMaster')

@section('title', 'Variantes de Idioma')

@section('vendor-style')
	<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
	<link rel="stylesheet" href="{{asset('assets/vendor/libs/flag-icons/flag-icons.css')}}" />
@endsection

@section('vendor-script')
	<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('content')
	<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
		<div class="d-flex flex-column justify-content-center">
			<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Variantes de Idioma /</span> Editar</h4>
			<p class="text-muted">Editar detalles de la variante de idioma</p>
		</div>
		<div class="d-flex align-content-center flex-wrap gap-3">
			<a href="{{ route('language-variants.index') }}" class="btn btn-primary waves-effect waves-light"><i
					class="ti ti-list me-1"></i>Listar Variantes</a>
		</div>
	</div>

	<div class="card mb-4">
		<h5 class="card-header">Detalles de la Variante</h5>
		<div class="card-body">
			@include('language.variants.form')
		</div>
	</div>
@endsection