@extends('layouts/layoutMaster')

@section('title', __('Fares'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>

<script>
    $(function() {
        if ($.fn.select2) {
            $('#unit_ids, #type_id').select2();
        }

        // Form validation
        $('form').on('submit', function(e) {
            const unitIds = $('#unit_ids').val();
            if (!unitIds || unitIds.length === 0) {
                $('#unit_ids_error').show();
                $('#unit_ids').addClass('is-invalid');
                e.preventDefault();
                return false;
            } else {
                $('#unit_ids_error').hide();
                $('#unit_ids').removeClass('is-invalid');
            }
        });

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
                    // Mostrar indicador de carga
                    Swal.fire({
                        title: 'Eliminando...',
                        text: 'Por favor espere',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    $.ajax({
                        url: route,
                        type: 'DELETE',
                        data: {
                            "_token": $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Eliminado!',
                                text: 'La tarifa ha sido eliminada.',
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                },
                                buttonsStyling: false
                            }).then(function() {
                                // Redirección manual
                                window.location.href = "{{ route('fare.index') }}";
                            });
                        },
                        error: function(error) {
                            console.error('Error al eliminar:', error);
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Ocurrió un error al eliminar. Redirigiendo...',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                },
                                buttonsStyling: false,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(function() {
                                // Redirección en caso de error también
                                window.location.href = "{{ route('fare.index') }}";
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
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Tarifas') }}/</span> {{ isset($fare) ? __('Editar') : __('Crear') }}</h4>
        <p class="text-muted">{{ __('Gestión de tarifas para servicios') }}</p>
    </div>
    @if(isset($fare))
    <div class="d-flex align-content-center flex-wrap gap-3">
        <form action="{{ route('fare.destroy', $fare->id) }}" method="POST" style="display: inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger waves-effect waves-light" onclick="return confirm('¿Estás seguro? Esta acción no se puede deshacer.')">
                <i class="ti ti-trash me-1"></i> Eliminar
            </button>
        </form>
    </div>
    @endif
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
                <div class="text-danger" id="unit_ids_error" style="display: none;">El campo unidades es obligatorio.</div>
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