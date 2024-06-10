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
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Clientes/</span> {{ isset($data) ? 'Edición' : 'Creación' }}</h4>
        <p class="text-muted">Gestión de categorías</p>
    </div>
    <!-- <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('category.create') }}" type="submit" class="btn btn-primary waves-effect waves-light">Eliminar</a>
    </div> -->
</div>

<div class="card mb-4">
	<h5 class="card-header">Clientes</h5>
	<form class="card-body" action="{{ route('category.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
		
		<h6>1. Información Personal</h6>
		<div class="row g-3">
			<div class="col-md-4">
				<label class="form-label" for="nombre">Nombre (*)</label>
					<input type="text" id="nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $data->nombre ?? '') }}" />
					@error('nombre')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
			</div>
			<div class="col-md-4">
				<label class="form-label" for="apellido1">Primer Apellido (*)</label>
				<div class="input-group input-group-merge">
					<input type="text" id="apellido1" name="apellido1" class="form-control @error('apellido1') is-invalid @enderror" value="{{ old('apellido1', $data->apellido1 ?? '') }}" />
					@error('apellido1')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="apellido2">Segundo Apellido (*)</label>
				<div class="input-group input-group-merge">
					<input type="text" id="apellido2" name="apellido2" class="form-control @error('apellido2') is-invalid @enderror" value="{{ old('apellido2', $data->apellido2 ?? '') }}" />
					@error('apellido2')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>

			<div class="col-md-4">
				<label class="form-label" for="nif_nie">NIF/NIE (*)</label>
				<div class="input-group input-group-merge">
					<input type="text" id="nif_nie" name="nif_nie" class="form-control @error('nif_nie') is-invalid @enderror" value="{{ old('nif_nie', $data->nif_nie ?? '') }}" />
					@error('nif_nie')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="nacionalidad">Nacionalidad</label>
				<select id="nacionalidad" name="nacionalidad" class="select2 form-select" data-allow-clear="false">
					<option value="España">Española</option>
				</select>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="fecha_nacimiento">Fecha de Nacimiento (*)</label>
					<input type="text" id="fecha_nacimiento" name="fecha_nacimiento" placeholder="YYYY-MM-DD" class="form-control dob-picker @error('fecha_nacimiento') is-invalid @enderror" value="{{ old('fecha_nacimiento', $data->fecha_nacimiento ?? '') }}" />
					@error('fecha_nacimiento')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
			</div>
		</div>
		<hr class="my-4 mx-n4" />

		<h6>2. Datos Adicionales</h6>
		<div class="row g-3">
			<div class="col-md-4">
				<label class="form-label" for="fecha_carne_conducir">Fecha de Carné de Conducir (**)</label>
				<input type="text" id="fecha_carne_conducir" name="fecha_carne_conducir" placeholder="YYYY-MM-DD" class="form-control dob-picker @error('fecha_carne_conducir') is-invalid @enderror" value="{{ old('fecha_carne_conducir', $data->fecha_carne_conducir ?? '') }}" />
					@error('fecha_carne_conducir')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
					<div class="form-text">** Obligatorio para pólizas de coche o moto</div>
			</div>
			<div class="col-md-8">
				<label class="form-label" for="direccion">Dirección (*)</label>
				<div class="input-group input-group-merge">
					<input type="text" id="direccion" name="direccion" class="form-control @error('direccion') is-invalid @enderror" value="{{ old('direccion', $data->direccion ?? '') }}" />
					@error('direccion')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>
			<div class="col-md-3">
				<label class="d-block form-label">Sexo</label>
				<div class="form-check d-inline-block mb-2">
					<input type="radio" id="basic-default-radio-male" name="sexo" value="M" class="form-check-input" {{ old('sexo', $data->sexo ?? '') == 'M' ? 'checked' : '' }} />
					<label class="form-check-label" for="basic-default-radio-male">Masculino</label>
				</div>
				<div class="form-check d-inline-block">
					<input type="radio" id="basic-default-radio-female" name="sexo" value="F" class="form-check-input" {{ old('sexo', $data->sexo ?? '') == 'F' ? 'checked' : '' }} />
					<label class="form-check-label" for="basic-default-radio-female">Femenino</label>
				</div>
				@error('sexo')
					<div class="invalid-feedback d-block">{{ $message }}</div>
				@enderror
			</div>
			<div class="col-md-3">
				<label class="form-label" for="estado_civil">Estado Civil</label>
				<select id="estado_civil" name="estado_civil" class="select2 form-select" data-allow-clear="false">
					<option value="">Selecciona una opción</option>
					<option value="Soltero" {{ old('estado_civil', $data->estado_civil ?? '') == 'Soltero' ? 'selected' : '' }}>Soltero</option>
					<option value="Casado" {{ old('estado_civil', $data->estado_civil ?? '') == 'Casado' ? 'selected' : '' }}>Casado</option>
					<option value="Divorciado" {{ old('estado_civil', $data->estado_civil ?? '') == 'Divorciado' ? 'selected' : '' }}>Divorciado</option>
					<option value="Viudo" {{ old('estado_civil', $data->estado_civil ?? '') == 'Viudo' ? 'selected' : '' }}>Viudo</option>
				</select>
				@error('estado_civil')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-2">
				<label class="form-label" for="numero_hijos">Hijos</label>
				<div class="input-group input-group-merge">
					<input type="number" id="numero_hijos" name="numero_hijos" class="form-control @error('numero_hijos') is-invalid @enderror" value="{{ old('numero_hijos', $data->numero_hijos ?? '') }}" />
					@error('numero_hijos')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>

			<div class="col-md-4">
				<label class="form-label" for="profesion">Profesión</label>
				<div class="input-group input-group-merge">
					<input type="text" id="profesion" name="profesion" class="form-control @error('profesion') is-invalid @enderror" value="{{ old('profesion', $data->profesion ?? '') }}" />
					@error('profesion')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>
		</div>
		<hr class="my-4 mx-n4" />

		<h6>3. Datos de Contacto</h6>
		<div class="row g-3">
			<div class="col-md-4">
				<label class="form-label" for="telefono1">Teléfono</label>
				<div class="input-group input-group-merge">
					<input type="text" id="telefono1" name="telefono1" class="form-control phone-mask @error('telefono1') is-invalid @enderror" value="{{ old('telefono1', $data->telefono1 ?? '') }}" />
					@error('telefono1')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="telefono2">Móvil (*)</label>
				<div class="input-group input-group-merge">
					<input type="text" id="telefono2" name="telefono2" class="form-control phone-mask @error('telefono2') is-invalid @enderror" value="{{ old('telefono2', $data->telefono2 ?? '') }}" />
					@error('telefono2')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="correo_electronico">Email (*)</label>
				<div class="input-group input-group-merge">
					<input type="text" id="correo_electronico" name="correo_electronico" class="form-control @error('correo_electronico') is-invalid @enderror" value="{{ old('correo_electronico', $data->correo_electronico ?? '') }}" />
					@error('correo_electronico')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>
		</div>
		<hr class="my-4 mx-n4" />

		<h6>4. Datos Bancarios</h6>
		<div class="row g-3">
			<div class="col-md-6">
				<label class="form-label" for="cuenta_bancaria">Cuenta Bancaria (*)</label>
				<input type="text" id="cuenta_bancaria" name="cuenta_bancaria" class="form-control @error('cuenta_bancaria') is-invalid @enderror" value="{{ old('cuenta_bancaria', $data->cuenta_bancaria ?? '') }}" />
				@error('cuenta_bancaria')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			<div class="col-md-6">
				<label class="form-label" for="tarjeta_bancaria">Tarjeta Bancaria</label>
				<input type="text" id="tarjeta_bancaria" name="tarjeta_bancaria" class="form-control credit-card-mask @error('tarjeta_bancaria') is-invalid @enderror" value="{{ old('tarjeta_bancaria', $data->tarjeta_bancaria ?? '') }}" />
				@error('tarjeta_bancaria')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
		</div>
		<div class="pt-4">
			<button type="submit" class="btn btn-primary me-sm-3 me-1">Enviar</button>
			<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('app-insurance-client-list') }}'">Cancelar</button>
		</div>
	</form>
</div>

@endsection