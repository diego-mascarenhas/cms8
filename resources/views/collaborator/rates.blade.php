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
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-primary active px-3">
                                <span class="fi fi-es me-1"></span> es-SP
                                <span class="mx-1">></span>
                                <span class="fi fi-fr me-1"></span> fr-FR
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary px-3">
                                <span class="fi fi-fr me-1"></span> fr-FR
                                <span class="mx-1">></span>
                                <span class="fi fi-es me-1"></span> es-SP
                            </button>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="sameRates" name="same_rates" checked>
                            <label class="form-check-label" for="sameRates">
                                Son las mismas tarifas de fr-FR a es-SP
                            </label>
                        </div>
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
            $(this).addClass('active').siblings().removeClass('active');
        });
    });
</script>
@endpush 