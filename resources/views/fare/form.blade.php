@extends('layouts/layoutMaster')

@section('title', isset($fare) ? 'Editar Tarifa' : 'Nueva Tarifa')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<h4 class="py-3 breadcrumb-wrapper mb-4">
  <span class="text-muted fw-light">Tarifas /</span> {{ isset($fare) ? 'Editar' : 'Nueva' }}
</h4>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <h5 class="card-header">{{ isset($fare) ? 'Editar Tarifa' : 'Nueva Tarifa' }}</h5>
            <div class="card-body">
                <form method="POST" action="{{ isset($fare) ? route('fare.update', $fare->id) : route('fare.store') }}" class="row g-3">
                    @csrf
                    @if(isset($fare))
                        @method('PUT')
                    @endif

                    <div class="col-md-6">
                        <label class="form-label" for="name">Nombre</label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ isset($fare) ? $fare->name : old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="unit_ids">Unidades</label>
                        <select name="unit_ids[]" id="unit_ids" class="form-select @error('unit_ids') is-invalid @enderror" multiple required>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ isset($fare) && $fare->units->contains($unit->id) ? 'selected' : '' }}>
                                    {{ $unit->type }}
                                </option>
                            @endforeach
                        </select>
                        @error('unit_ids')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="type_id">Tipo</label>
                        <select name="type_id" id="type_id" class="form-select @error('type_id') is-invalid @enderror">
                            <option value="">Seleccione tipo (opcional)</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" {{ isset($fare) && $fare->type_id == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 text-end">
                        <a href="{{ route('fare.index') }}" class="btn btn-outline-secondary me-1">Cancelar</a>
                        <button type="submit" class="btn btn-primary">{{ isset($fare) ? 'Actualizar' : 'Guardar' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    $(function() {
        // Inicializar Select2 si está disponible
        if ($.fn.select2) {
            $('#unit_ids, #type_id').select2();
        }
    });
</script>
@endsection 