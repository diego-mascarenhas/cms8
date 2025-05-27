@extends('layouts/layoutMaster')

@section('title', __('Fares'))

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

<script>
    $(function() {
        // Inicializar Select2 si está disponible
        if ($.fn.select2) {
            $('#unit_ids, #type_id').select2();
        }
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Tarifas') }}/</span> {{ isset($fare) ? __('Editar') : __('Crear') }}</h4>
        <p class="text-muted">{{ __('Gestión de tarifas para servicios') }}</p>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">Tarifas</h5>
    <form class="card-body" action="{{ isset($fare) ? route('fare.update', $fare->id) : route('fare.store') }}" method="POST">
        @csrf
        @if(isset($fare))
            @method('PUT')
        @endif
        
        <div class="row g-3">
            <div class="col-md-6">
                <x-input-general id="name" label="Nombre (*)" value="{{ old('name', $fare->name ?? '') }}" />
            </div>
            
            <div class="col-md-6">
                <label class="form-label" for="unit_ids">Unidades (*)</label>
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
        </div>
        
        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('fare.index') }}'">Cancelar</button>
            </div>
        </div>
    </form>
</div>
@endsection 