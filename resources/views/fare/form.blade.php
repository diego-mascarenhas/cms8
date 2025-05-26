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
                        <label class="form-label" for="unit_id">Unidad</label>
                        <select name="unit_id" id="unit_id" class="form-select @error('unit_id') is-invalid @enderror" required>
                            <option value="">Seleccione unidad</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ isset($fare) && $fare->unit_id == $unit->id ? 'selected' : '' }}>
                                    {{ $unit->type }}
                                </option>
                            @endforeach
                        </select>
                        @error('unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="block_id">Bloque</label>
                        <select name="block_id" id="block_id" class="form-select @error('block_id') is-invalid @enderror">
                            <option value="">Seleccione bloque (opcional)</option>
                            @foreach($blocks as $block)
                                <option value="{{ $block->id }}" {{ isset($fare) && $fare->block_id == $block->id ? 'selected' : '' }}>
                                    {{ $block->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('block_id')
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
            $('#unit_id, #block_id').select2();
        }
    });
</script>
@endsection 