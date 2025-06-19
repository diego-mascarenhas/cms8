@extends('layouts/layoutMaster')

@section('title', 'Tarifas de ' . $collaborator->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flag-icons/flag-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <!-- Collaborator Sidebar -->
    @include('collaborator.partials.sidebar')
    <!--/ Collaborator Sidebar -->

    <!-- Rates Content -->
    <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Tabs -->
        @include('collaborator.partials.tabs')
        
        <div class="card mb-4">
            <div class="card-body">
                <form id="rates-form" method="POST" action="{{ route('collaborator.rates.save', $collaborator->id) }}">
                    @csrf
                    <!-- Selección de divisa -->
                    <div class="mb-3 row">
                        <label class="col-form-label col-md-2">Divisa *</label>
                        <div class="col-md-4">
                            <select class="form-select" name="currency">
                                <option value="EUR" selected>EUR</option>
                                <option value="USD">USD</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </div>
                    </div>

                    <!-- Selección de idiomas -->
                    <div class="mb-3">
                        <h5 class="mb-3">Combinaciones de idiomas</h5>
                        
                        @if($collaborator->languageVariants && $collaborator->languageVariants->count() > 0)
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach($collaborator->languageVariants as $index => $variant)
                                    @php
                                        $sourceFlag = strtolower($variant->sourceLanguage ? $variant->sourceLanguage->country_code ?? '' : '');
                                        if (empty($sourceFlag) && $variant->sourceLanguage) {
                                            $sourceFlag = strtolower($variant->source_language_code);
                                        }
                                        
                                        $targetFlag = strtolower($variant->targetLanguage ? $variant->targetLanguage->country_code ?? '' : '');
                                        if (empty($targetFlag) && $variant->targetLanguage) {
                                            $targetFlag = strtolower($variant->target_language_code);
                                        }
                                        
                                        $isActive = $index === 0; // Primera combinación activa por defecto
                                    @endphp
                                    
                                                                         <div class="btn-group me-2">
                                        <button type="button" class="btn btn-outline-primary {{ $isActive ? 'active' : '' }} px-3"
                                                data-source="{{ $variant->source_language_code }}" 
                                                data-target="{{ $variant->target_language_code }}">
                                            @if(!empty($sourceFlag))
                                                <span class="fi fi-{{ $sourceFlag }} me-1"></span>
                                            @endif
                                            {{ $variant->sourceLanguage ? $variant->sourceLanguage->name : $variant->source_language_code }}
                                            <span class="mx-1"><i class="ti ti-arrow-right text-muted"></i></span>
                                            @if(!empty($targetFlag))
                                                <span class="fi fi-{{ $targetFlag }} me-1"></span>
                                            @endif
                                            {{ $variant->targetLanguage ? $variant->targetLanguage->name : $variant->target_language_code }}
                                            @if($variant->is_certified)
                                                <span class="badge bg-label-success ms-1">Nativo</span>
                                            @endif
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="sameRates" name="same_rates" checked>
                                <label class="form-check-label" for="sameRates">
                                    Usar las mismas tarifas para todas las combinaciones
                                </label>
                            </div>
                            
                            <input type="hidden" name="current_language_pair" id="current_language_pair" 
                                   value="{{ $collaborator->languageVariants->first() ? $collaborator->languageVariants->first()->source_language_code . '|' . $collaborator->languageVariants->first()->target_language_code : '' }}">
                        @else
                            <div class="alert alert-warning">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-alert-triangle me-2"></i>
                                    <span>No hay combinaciones de idiomas registradas para este colaborador.</span>
                                </div>
                                <a href="{{ route('collaborator.edit', ['id' => $collaborator->id ?? 0]) }}" class="btn btn-sm btn-warning mt-2">
                                    <i class="ti ti-plus me-1"></i>Añadir idiomas
                                </a>
                            </div>
                        @endif
                    </div>

                    <hr>

                    <!-- Tarifas audiovisuales -->
                    <h5 class="mt-4 mb-3">Traducción audiovisual</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Traducción de plantilla</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[template]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/min</span>
                            </div>
                            <small class="text-muted">Traducción básica de guiones o plantillas.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Traducción + subtitulado sin guion</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[sub_no_script]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/min</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Traducción + subtitulado con guion</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[sub_with_script]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/min</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Traducción para locución/voice over/doblaje</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[voice_over]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/10 min</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Traducción de guion literario</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[literary_script]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/pág</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transcripción (publicidad)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[transcription_ad]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/hora</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Transcripción</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[transcription]" value="10" step="0.01" min="0">
                                <span class="input-group-text">/pág</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transcripción + subtitulado</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[transcription_sub]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/min</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Adaptación + subtitulado</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[adaptation_sub]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/min</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Revisión de subtítulos</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[sub_review]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/min</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary">Guardar tarifas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include Valoration Modal -->
@include('collaborator.partials.valoration-modal')

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Funcionalidad para los botones de selección de idiomas
        $('.btn-group .btn').on('click', function() {
            // Actualizar estado activo
            $(this).addClass('active').siblings().removeClass('active');
            
            // Obtener los códigos de idioma
            const sourceCode = $(this).data('source');
            const targetCode = $(this).data('target');
            
            if (sourceCode && targetCode) {
                // Actualizar el campo oculto con la combinación actual
                $('#current_language_pair').val(sourceCode + '|' + targetCode);
                
                // Aquí podrías cargar las tarifas específicas para esta combinación mediante AJAX
                // Por ejemplo:
                /*
                $.ajax({
                    url: '/collaborator/' + {{ $collaborator->id }} + '/rates/get',
                    method: 'GET',
                    data: {
                        source_language: sourceCode,
                        target_language: targetCode
                    },
                    success: function(response) {
                        // Actualizar los campos del formulario con las tarifas recibidas
                        if (response.rates) {
                            for (const [key, value] of Object.entries(response.rates)) {
                                $(`input[name="rates[${key}]"]`).val(value);
                            }
                        }
                    },
                    error: function(error) {
                        console.error('Error al cargar las tarifas:', error);
                    }
                });
                */
            }
        });
        
        // Manejar el checkbox de mismas tarifas
        $('#sameRates').on('change', function() {
            if ($(this).is(':checked')) {
                // Si está marcado, se usarán las mismas tarifas para todas las combinaciones
                // Podrías deshabilitar la selección de combinaciones o mostrar un mensaje
                $('.btn-group .btn').not('.active').addClass('opacity-50');
            } else {
                // Si no está marcado, se pueden seleccionar diferentes combinaciones
                $('.btn-group .btn').removeClass('opacity-50');
            }
        });
        
        // Inicializar el estado del checkbox
        $('#sameRates').trigger('change');
    });
</script>
@endpush 