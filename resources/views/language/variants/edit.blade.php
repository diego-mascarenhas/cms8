@extends('layouts/layoutMaster')

@section('title', 'Editar Variante de Idioma')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flag-icons/flag-icons.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('content')
<h4 class="py-3 breadcrumb-wrapper mb-4">
  <span class="text-muted fw-light">Variantes de Idioma /</span> Editar
</h4>

<div class="row">
  <div class="col-md-12">
    <div class="card mb-4">
      <h5 class="card-header">Editar Variante de Idioma</h5>
      <div class="card-body">
        <form action="{{ route('language-variants.update', $variant->id) }}" method="POST">
          @csrf
          @method('PUT')
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="native_name" class="form-label">Nombre Nativo</label>
              <input type="text" class="form-control @error('native_name') is-invalid @enderror" 
                    id="native_name" name="native_name" placeholder="Español (España)" 
                    value="{{ old('native_name', $variant->native_name) }}">
              <small class="text-muted">Nombre del idioma en el propio idioma</small>
              @error('native_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            
            <div class="col-md-6">
              <label for="base_language" class="form-label">Idioma Base</label>
              <select id="base_language" name="base_language" class="form-select select2 @error('base_language') is-invalid @enderror" required>
                <option value="">Seleccione un idioma</option>
                @foreach($languages as $language)
                  <option value="{{ $language->code }}" {{ old('base_language', $variant->base_language) == $language->code ? 'selected' : '' }}>{{ $language->name }}</option>
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
              <input type="text" class="form-control @error('code') is-invalid @enderror" 
                    id="code" name="code" placeholder="es_ES" 
                    value="{{ old('code', $variant->code) }}" required>
              <small class="text-muted">Formato recomendado: idioma_PAIS (ej: es_ES, en_US)</small>
              @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            
            <div class="col-md-6">
              <label for="name" class="form-label">Nombre</label>
              <input type="text" class="form-control @error('name') is-invalid @enderror" 
                    id="name" name="name" placeholder="Español (España)" 
                    value="{{ old('name', $variant->name) }}" required>
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="country_code" class="form-label">Código de País</label>
              <input type="text" class="form-control @error('country_code') is-invalid @enderror" 
                    id="country_code" name="country_code" placeholder="es" 
                    value="{{ old('country_code', $variant->country_code) }}" maxlength="2">
              <small class="text-muted">Código ISO de 2 letras (es, us, etc)</small>
              @error('country_code')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            
            <div class="col-md-6">
              <label for="flag" class="form-label">Código de Bandera</label>
              <input type="text" class="form-control @error('flag') is-invalid @enderror" 
                    id="flag" name="flag" placeholder="es" 
                    value="{{ old('flag', $variant->flag) }}" maxlength="2">
              <small class="text-muted">Código ISO de 2 letras para mostrar la bandera</small>
              @error('flag')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          
          <div class="mt-4">
            <button type="submit" class="btn btn-primary me-2">Actualizar</button>
            <a href="{{ route('language-variants.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2
    $('.select2').select2();
  });
</script>
@endsection 