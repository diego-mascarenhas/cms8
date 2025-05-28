@extends('layouts/layoutMaster')

@section('title', __('Certificaciones'))

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
        // Delete functionality with form submission
        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            const form = $(this).closest('form');
            
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
                    
                    // Enviar el formulario
                    form.submit();
                }
            });
        });
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Certificaciones') }}/</span> {{ isset($certification) ? __('Editar') : __('Crear') }}</h4>
        <p class="text-muted">{{ __('Gestión de certificaciones disponibles') }}</p>
    </div>
    @if(isset($certification))
    <div class="d-flex align-content-center flex-wrap gap-3">
        <form action="{{ route('certification.destroy', $certification->id) }}" method="POST" style="display: inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger waves-effect waves-light btn-delete">
                <i class="ti ti-trash me-1"></i> Eliminar
            </button>
        </form>
    </div>
    @endif
</div>

<div class="card mb-4">
    <h5 class="card-header">Certificación</h5>
    <form class="card-body" action="{{ isset($certification) ? route('certification.update', $certification->id) : route('certification.store') }}" method="POST">
        @csrf
        @if(isset($certification))
            @method('PUT')
        @endif
        
        <div class="row g-3">
            <div class="col-md-6">
                <x-input-general id="certification" label="Certificación (*)" value="{{ old('certification', $certification->certification ?? '') }}" />
            </div>
            
            <div class="col-md-6">
                <x-language-select 
                    name="language" 
                    id="language" 
                    label="Idioma (*)" 
                    value="{{ old('language', $certification->language ?? '') }}" 
                />
                @error('language')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>
        
        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('certification.index') }}'">Cancelar</button>
            </div>
        </div>
    </form>
</div>
@endsection 