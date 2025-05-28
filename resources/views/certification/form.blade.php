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
                                    text: 'La certificación ha sido eliminada.',
                                    customClass: {
                                        confirmButton: 'btn btn-success'
                                    },
                                    buttonsStyling: false
                                }).then(function() {
                                    window.location.href = "{{ route('certification.index') }}";
                                });
                            }
                        },
                        error: function(error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Ocurrió un error al eliminar la certificación.',
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
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Certificaciones') }}/</span> {{ isset($certification) ? __('Editar') : __('Crear') }}</h4>
        <p class="text-muted">{{ __('Gestión de certificaciones disponibles') }}</p>
    </div>
    @if(isset($certification))
    <div class="d-flex align-content-center flex-wrap gap-3">
        <button type="button" class="btn btn-danger waves-effect waves-light delete-record" data-route="{{ route('certification.destroy', $certification->id) }}">
            <i class="ti ti-trash me-1"></i> Eliminar
        </button>
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
                <label class="form-label" for="language">Idioma (*)</label>
                <select name="language" id="language" class="form-select @error('language') is-invalid @enderror" required>
                    <option value="">Seleccione un idioma</option>
                    <option value="ES" {{ (isset($certification) && $certification->language == 'ES') || old('language') == 'ES' ? 'selected' : '' }}>Español</option>
                    <option value="EN" {{ (isset($certification) && $certification->language == 'EN') || old('language') == 'EN' ? 'selected' : '' }}>Inglés</option>
                    <option value="FR" {{ (isset($certification) && $certification->language == 'FR') || old('language') == 'FR' ? 'selected' : '' }}>Francés</option>
                    <option value="DE" {{ (isset($certification) && $certification->language == 'DE') || old('language') == 'DE' ? 'selected' : '' }}>Alemán</option>
                    <option value="IT" {{ (isset($certification) && $certification->language == 'IT') || old('language') == 'IT' ? 'selected' : '' }}>Italiano</option>
                    <option value="PT" {{ (isset($certification) && $certification->language == 'PT') || old('language') == 'PT' ? 'selected' : '' }}>Portugués</option>
                    <option value="JA" {{ (isset($certification) && $certification->language == 'JA') || old('language') == 'JA' ? 'selected' : '' }}>Japonés</option>
                    <option value="ZH" {{ (isset($certification) && $certification->language == 'ZH') || old('language') == 'ZH' ? 'selected' : '' }}>Chino</option>
                    <option value="KO" {{ (isset($certification) && $certification->language == 'KO') || old('language') == 'KO' ? 'selected' : '' }}>Coreano</option>
                    <option value="RU" {{ (isset($certification) && $certification->language == 'RU') || old('language') == 'RU' ? 'selected' : '' }}>Ruso</option>
                    <option value="AR" {{ (isset($certification) && $certification->language == 'AR') || old('language') == 'AR' ? 'selected' : '' }}>Árabe</option>
                </select>
                @error('language')
                    <div class="invalid-feedback">{{ $message }}</div>
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