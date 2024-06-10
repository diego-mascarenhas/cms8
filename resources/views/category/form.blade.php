@extends('layouts/layoutMaster')

@section('title', ' Clientes')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Categorías/</span> {{ isset($data) ? 'Edición' : 'Creación' }}</h4>
        <p class="text-muted">Gestión de categorías</p>
    </div>
    <!-- <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('category.create') }}" type="submit" class="btn btn-primary waves-effect waves-light">Eliminar</a>
    </div> -->
</div>

<div class="card mb-4">
	<h5 class="card-header">Categoría</h5>
	<form class="card-body" action="{{ route('category.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		
		<div class="row g-3">
			<div class="col-md-4">
				<label class="form-label" for="name">Nombre (*)</label>
					<input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $data->name ?? '') }}" />
					@error('name')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
			</div>
			<div class="col-xl-12 p-4">
				<div class="text-light small fw-medium">Estado</div>
				<div class="demo-inline-spacing">
					<x-input-checkbox name="status" label="Activa" value="{{ old('status', $data->status ?? '') }}" />
				</div>
			</div>
		</div>
		<hr class="my-4 mx-n4" />

		<div class="pt-4">
			<button type="submit" class="btn btn-primary me-sm-3 me-1">Enviar</button>
			<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('app-category-list') }}'">Cancelar</button>
		</div>
	</form>
</div>

@endsection