@extends('layouts/layoutMaster')

@section('title', 'Agregar servicios al proyecto')

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
        // Servicios vinculados - Funcionalidad
        let serviceIndex = 1;

        // Inicializar Select2 en filas existentes al cargar la página
        $('.service-row').each(function() {
            const index = $(this).data('index');
            initializeExistingServiceRow(index);
        });

        // Agregar nuevo servicio
        $('#add-service').on('click', function() {
            $.get('{{ route("project.get-service-template") }}', { index: serviceIndex })
                .done(function(template) {
                    $('#services-container').append(template);
                    
                    // Inicializar Select2 en los nuevos elementos
                    initializeNewServiceRow(serviceIndex);
                    
                    serviceIndex++;
                })
                .fail(function() {
                    alert('Error agregando servicio. Por favor intente de nuevo.');
                });
        });

        // Función para inicializar Select2 en filas existentes
        function initializeExistingServiceRow(index) {
            // Los componentes x-variant-language-select ya se inicializan solos
            // Solo necesitamos inicializar el select de tarifas si no está inicializado
            const fareSelect = $(`#fare_${index}`);
            
            if (fareSelect.length && !fareSelect.hasClass('select2-hidden-accessible')) {
                fareSelect.select2({
                    dropdownParent: fareSelect.parent(),
                    width: '100%'
                });
            }
        }

        // Función para inicializar Select2 en una nueva fila de servicio
        function initializeNewServiceRow(index) {
            // Inicializar selects de idiomas con banderas
            const sourceSelect = $(`#source_language_${index}`);
            const targetSelect = $(`#target_language_${index}`);
            const fareSelect = $(`#fare_${index}`);
            
            if (sourceSelect.length && !sourceSelect.hasClass('select2-hidden-accessible')) {
                sourceSelect.select2({
                    dropdownParent: sourceSelect.parent(),
                    templateResult: window.formatVariantLanguage || function(lang) { return lang.text; },
                    templateSelection: window.formatVariantLanguage || function(lang) { return lang.text; },
                    width: '100%'
                });
            }
            
            if (targetSelect.length && !targetSelect.hasClass('select2-hidden-accessible')) {
                targetSelect.select2({
                    dropdownParent: targetSelect.parent(),
                    templateResult: window.formatVariantLanguage || function(lang) { return lang.text; },
                    templateSelection: window.formatVariantLanguage || function(lang) { return lang.text; },
                    width: '100%'
                });
            }
            
            if (fareSelect.length && !fareSelect.hasClass('select2-hidden-accessible')) {
                fareSelect.select2({
                    dropdownParent: fareSelect.parent(),
                    width: '100%'
                });
            }
        }

        // Eliminar servicio
        $(document).on('click', '.remove-service', function() {
            if ($('.service-row').length > 1) {
                const serviceRow = $(this).closest('.service-row');
                
                // Limpiar Select2 antes de eliminar
                serviceRow.find('.select2-hidden-accessible').each(function() {
                    $(this).select2('destroy');
                });
                
                serviceRow.remove();
            } else {
                alert('Al menos un servicio es requerido');
            }
        });

        // Manejar cambio de tarifa para actualizar unidades
        $(document).on('change', 'select[id^="fare_"]', function() {
            const fareId = $(this).val();
            const serviceRow = $(this).closest('.service-row');
            const index = serviceRow.data('index');
            const unitSelect = serviceRow.find(`select[id="unit_${index}"]`);
            
            console.log('Tarifa seleccionada:', fareId);
            console.log('Índice de fila:', index);
            console.log('Selector de unidad encontrado:', unitSelect.length);
            
            if (!fareId) {
                // Si no hay tarifa seleccionada, limpiar unidades
                unitSelect.html('<option value="">Seleccionar unidad</option>');
                return;
            }

            // Hacer llamada AJAX para obtener unidades
            $.get('{{ route("project.get-fare-units") }}', { fare_id: fareId })
                .done(function(response) {
                    console.log('Respuesta de unidades:', response);
                    let options = '<option value="">Seleccionar unidad</option>';
                    
                    if (response.units && response.units.length > 0) {
                        response.units.forEach(function(unit) {
                            options += `<option value="${unit.type}">${unit.label}</option>`;
                        });
                    }
                    
                    unitSelect.html(options);
                })
                .fail(function(xhr, status, error) {
                    console.error('Error cargando unidades para tarifa:', fareId, error);
                    unitSelect.html('<option value="">Error cargando unidades</option>');
                });
        });
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Proyectos / {{ $project->name }} /</span> Agregar servicios</h4>
        <p class="text-muted">Agrega los servicios vinculados al proyecto</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('project.show', $project->id) }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>Volver al proyecto
        </a>
    </div>
</div>

<!-- Información del proyecto -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted">Proyecto</h6>
                <p class="mb-2"><strong>{{ $project->name }}</strong></p>
                @if($project->real_name)
                    <p class="mb-2"><small class="text-muted">Nombre real: {{ $project->real_name }}</small></p>
                @endif
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Cliente</h6>
                <p class="mb-2">{{ $project->client->name ?? 'N/A' }}</p>
                <h6 class="text-muted">Responsable</h6>
                <p class="mb-2">{{ $project->responsible->name ?? 'N/A' }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Formulario de servicios -->
<div class="card mb-4">
    <h5 class="card-header">Servicios vinculados</h5>
    <form class="card-body" action="{{ route('project.store-services', $project->id) }}" method="POST">
        @csrf
        
        <div class="row g-4">
            <div class="col-12">
                <div id="services-container">
                    @include('project.partials.service-row', ['index' => 0, 'projectFare' => null])
                </div>
                
                <button type="button" id="add-service" class="btn btn-outline-primary btn-sm">
                    <i class="ti ti-plus me-1"></i>Agregar servicio
                </button>
            </div>
        </div>
        
        <div class="pt-4">
            <div class="d-flex gap-3">
                <button type="submit" class="btn btn-primary px-5">Guardar servicios</button>
                <a href="{{ route('project.show', $project->id) }}" class="btn btn-label-secondary">Omitir por ahora</a>
            </div>
        </div>
    </form>
</div>

@endsection 