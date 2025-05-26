@extends('layouts/layoutMaster')

@section('title', 'Tarifas de ' . $collaborator->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flag-icons/flag-icons.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <!-- Collaborator Sidebar -->
    <div class="col-xl-4 col-lg-5 col-md-5">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center flex-column mb-3">
                    <img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle mb-3" width="100" height="100">
                    <h4 class="mb-1">{{ $collaborator->name ?? 'Colaborador' }}</h4>
                    <span class="badge bg-label-secondary rounded-pill">Top</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-center me-4">
                        <div class="badge bg-label-primary rounded-circle p-2">
                            <i class="ti ti-file-text ti-sm"></i>
                        </div>
                        <h6 class="mt-2 mb-0">5</h6>
                        <span class="text-muted small">Proyectos</span>
                    </div>
                    <div class="text-center">
                        <div class="badge bg-label-info rounded-circle p-2">
                            <i class="ti ti-clock ti-sm"></i>
                        </div>
                        <h6 class="mt-2 mb-0">648</h6>
                        <span class="text-muted small">Minutos</span>
                    </div>
                </div>
                <h5 class="pb-2 border-bottom mb-4">Detalles</h5>
                <div class="info-container">
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2">
                            <span class="fw-medium me-1">Email:</span>
                            <span>{{ $collaborator->email ?? '' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">Estado:</span>
                            <span class="badge bg-label-success">Activo</span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">Contacto:</span>
                            <span>{{ $collaborator->phone ?? '' }}</span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">Idiomas:</span>
                            <span>Español, Inglés</span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">País:</span>
                            <span>España</span>
                        </li>
                        <li class="mb-2">
                            <span class="fw-medium me-1">Trabaja fines de semana:</span>
                            <span>Sí</span>
                        </li>
                    </ul>
                    <div class="d-flex gap-3 mb-4">
                        <a href="{{ route('collaborator.edit', ['id' => $collaborator->id ?? 0]) }}" class="btn btn-primary flex-grow-1">
                            <i class="ti ti-edit me-1"></i>Editar
                        </a>
                        <a href="javascript:void(0)" class="btn btn-label-danger flex-grow-1">
                            Marcar como ojo
                        </a>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-file-description me-2"></i>
                            <span>Acuerdo de colaboración</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-file-description me-2"></i>
                            <span>Curriculum Vitae</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-file-description me-2"></i>
                            <span>Certificado de retenciones</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <i class="ti ti-file-description me-2"></i>
                            <span>Certificado de alta autónomo</span>
                        </div>
                    </div>
                    <h5 class="border-bottom pb-2 mb-4">Comentarios</h5>
                    <p class="small">
                        Trabaja muy bien lo que sale en sus fotos, es un fenómeno. 
                        De vacaciones 3 meses al año.
                        Dominio de diferentes temáticas.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!--/ Collaborator Sidebar -->

    <!-- Rates Content -->
    <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Tabs -->
        <div class="d-flex mb-3">
            <a href="{{ route('collaborator.show', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary me-3">
                <i class="ti ti-refresh me-1"></i>Resumen
            </a>
            <a href="{{ route('collaborator.rates', ['id' => $collaborator->id]) }}" class="btn btn-primary me-3">
                <i class="ti ti-tag me-1"></i>Tarifas
            </a>
            <a href="{{ route('collaborator.absences', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary me-3">
                <i class="ti ti-users me-1"></i>Ausencias
            </a>
            <a href="{{ route('collaborator.notifications', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary">
                <i class="ti ti-bell me-1"></i>Notificaciones
            </a>
        </div>
        
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
                            <label class="form-label">Revisión</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[review]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/min</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Ajuste</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[adjustment]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/min</span>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Tarifas traducción general -->
                    <h5 class="mt-4 mb-3">Traducción general (texto)</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Traducción general</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[general]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/palabra</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Revisión</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[review_text]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/palabra</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Jurídica</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[legal]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/palabra</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Médica</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Eur</span>
                                <input type="number" class="form-control" name="rates[medical]" value="0.00" step="0.01" min="0">
                                <span class="input-group-text">/palabra</span>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    $(document).ready(function() {
        // Funcionalidad para los botones de selección de idiomas
        $('.btn-group .btn').on('click', function() {
            $(this).addClass('active').siblings().removeClass('active');
        });
    });
</script>
@endsection 