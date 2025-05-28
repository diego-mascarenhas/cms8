<form
	action="{{ isset($variant) ? route('language-variants.update', $variant->id) : route('language-variants.store') }}"
	method="POST">
	@csrf
	@if(isset($variant))
		@method('PUT')
	@endif

	<div class="row mb-3">
		<div class="col-md-6">
			<label for="native_name" class="form-label">Nombre Nativo</label>
			<input type="text" class="form-control @error('native_name') is-invalid @enderror" id="native_name"
				name="native_name" placeholder="Español (España)"
				value="{{ old('native_name', $variant->native_name ?? '') }}">
			<small class="text-muted">Nombre del idioma en el propio idioma</small>
			@error('native_name')
				<div class="invalid-feedback">{{ $message }}</div>
			@enderror
		</div>

		<div class="col-md-6">
			<label for="base_language" class="form-label">Idioma Base</label>
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
	</div>

	<div class="row mb-3">
		<div class="col-md-6">
			<label for="code" class="form-label">Código</label>
			<input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code"
				placeholder="es-ES" value="{{ old('code', $variant->code ?? '') }}" required>
			<small class="text-muted">Formato recomendado: idioma-PAIS (ej: es-ES, en-US)</small>
			@error('code')
				<div class="invalid-feedback">{{ $message }}</div>
			@enderror
		</div>

		<div class="col-md-6">
			<label for="name" class="form-label">Nombre</label>
			<input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
				placeholder="Español (España)" value="{{ old('name', $variant->name ?? '') }}" required>
			@error('name')
				<div class="invalid-feedback">{{ $message }}</div>
			@enderror
		</div>
	</div>

	<div class="row mb-3">
		<div class="col-md-6">
			<label for="country_code" class="form-label">Código de País</label>
			<input type="text" class="form-control @error('country_code') is-invalid @enderror" id="country_code"
				name="country_code" placeholder="es" value="{{ old('country_code', $variant->country_code ?? '') }}"
				maxlength="2">
			<small class="text-muted">Código ISO de 2 letras (es, us, etc)</small>
			@error('country_code')
				<div class="invalid-feedback">{{ $message }}</div>
			@enderror
		</div>

		<div class="col-md-6">
			<label for="flag" class="form-label">Código de Bandera</label>
			<input type="text" class="form-control @error('flag') is-invalid @enderror" id="flag" name="flag"
				placeholder="es" value="{{ old('flag', $variant->flag ?? '') }}" maxlength="2">
			<small class="text-muted">Código ISO de 2 letras para mostrar la bandera</small>
			@error('flag')
				<div class="invalid-feedback">{{ $message }}</div>
			@enderror
		</div>
	</div>

	<div class="pt-4">
		<div class="col-12 d-flex">
			<button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
			<button type="reset" class="btn btn-label-secondary"
				onclick="location.href='{{ route('language-variants.index') }}'">Cancelar</button>
			@if(isset($variant))
				<a href="javascript:void(0)" onclick="confirmDelete()" class="btn btn-danger ms-2">Eliminar</a>
				<form id="delete-form" action="{{ route('language-variants.destroy', $variant->id) }}" method="POST" style="display: none;">
					@csrf
					@method('DELETE')
				</form>
			@endif
		</div>
	</div>
</form>

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
	});
	
	function confirmDelete() {
		if (confirm('¿Está seguro que desea eliminar esta variante de idioma?')) {
			document.getElementById('delete-form').submit();
		}
	}
</script>