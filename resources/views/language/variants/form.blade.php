@extends('layouts/layoutMaster')

@section('title', 'Variantes de Idioma')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flag-icons/flag-icons.css')}}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
<script>
	document.addEventListener('DOMContentLoaded', function () {
		// Initialize Select2
		$('.select2').select2();

		@if(!isset($variant))
			// Auto-fill code when base language is selected (only for create)
			$('#base_language').on('change', function () {
				const baseCode = $(this).val();
				if (baseCode) {
					$('#code').val(baseCode + '-');
					$('#country_code').val(baseCode.toUpperCase());
					$('#flag').val(baseCode.toUpperCase());
				}
			});
		@endif
        
        // Delete functionality
        $(document).on('click', '.delete-record', function() {
            const route = $(this).data('route');
            
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡No podrás revertir esto!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.value) {
                    $.ajax({
                        url: route,
                        type: 'DELETE',
                        data: {
                            "_token": $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Eliminado!',
                                    text: 'La variante de idioma ha sido eliminada.',
                                    customClass: {
                                        confirmButton: 'btn btn-success'
                                    },
                                    buttonsStyling: false
                                }).then(function() {
                                    window.location.href = "{{ route('language-variants.index') }}";
                                });
                            }
                        },
                        error: function(error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Ocurrió un error al eliminar la variante de idioma.',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                },
                                buttonsStyling: false
                            });
                        }
                    });
                }
            });
        });
	});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
	<div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">Variantes de Idioma /</span> {{ isset($variant) ? 'Editar' : 'Crear' }}</h4>
		<p class="text-muted">Gestión de variantes de idioma disponibles</p>
	</div>
	@if(isset($variant))
	<div class="d-flex align-content-center flex-wrap gap-3">
		<button type="button" class="btn btn-danger waves-effect waves-light delete-record" data-route="{{ route('language-variants.destroy', $variant->id) }}">
			<i class="ti ti-trash me-1"></i> Eliminar
		</button>
	</div>
	@endif
</div>

<div class="card mb-4">
	<h5 class="card-header">Variantes de Idioma</h5>
	<form class="card-body" action="{{ isset($variant) ? route('language-variants.update', $variant->id) : route('language-variants.store') }}" method="POST">
		@csrf
		@if(isset($variant))
			@method('PUT')
		@endif
		
		<div class="row g-3">
			<div class="col-md-6">
				<label class="form-label" for="name">Nombre (*)</label>
				<input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
					placeholder="Español (España)" value="{{ old('name', $variant->name ?? '') }}" required>
				@error('name')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			
			<div class="col-md-6">
				<label class="form-label" for="native_name">Nombre Nativo</label>
				<input type="text" class="form-control @error('native_name') is-invalid @enderror" id="native_name"
					name="native_name" placeholder="Español (España)" value="{{ old('native_name', $variant->native_name ?? '') }}">
				<small class="text-muted">Nombre del idioma en el propio idioma</small>
				@error('native_name')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			
			<div class="col-md-6">
				<label class="form-label" for="code">Código (*)</label>
				<input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code"
					placeholder="es-ES" value="{{ old('code', $variant->code ?? '') }}" required>
				<small class="text-muted">Formato recomendado: idioma-PAIS (ej: es-ES, en-US)</small>
				@error('code')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			
			<div class="col-md-6">
				<label class="form-label" for="base_language">Idioma Base (*)</label>
				<select id="base_language" name="base_language"
					class="form-select select2 @error('base_language') is-invalid @enderror" required>
					<option value="">Seleccione un idioma</option>
					@foreach($languages as $language)
						<option value="{{ $language->code }}" {{ old('base_language', $variant->base_language ?? '') == $language->code ? 'selected' : '' }}>{{ $language->name }}</option>
					@endforeach
				</select>
				@error('base_language')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			
			<div class="col-md-6">
				<label class="form-label" for="country_code">Código de País</label>
				<input type="text" class="form-control @error('country_code') is-invalid @enderror" id="country_code"
					name="country_code" placeholder="ES" value="{{ old('country_code', $variant->country_code ?? '') }}"
					maxlength="2">
				<small class="text-muted">Código ISO de 2 letras (ES, US, etc)</small>
				@error('country_code')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
			
			<div class="col-md-6">
				<label class="form-label" for="flag">Código de Bandera</label>
				<input type="text" class="form-control @error('flag') is-invalid @enderror" id="flag" name="flag"
					placeholder="ES" value="{{ old('flag', $variant->flag ?? '') }}" maxlength="2">
				<small class="text-muted">Código ISO de 2 letras para mostrar la bandera</small>
				@error('flag')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>
		</div>
		
		<div class="pt-4">
			<div class="col-12 d-flex">
				<button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
				<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('language-variants.index') }}'">Cancelar</button>
			</div>
		</div>
	</form>
</div>
@endsection