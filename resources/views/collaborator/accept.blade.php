@extends('layouts/layoutMaster')

@section('title', 'Aceptar Colaborador - ' . $collaborator->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Collaborators') }}/</span> Aceptar colaborador</h4>
            <p class="text-muted">Revisa la información del colaborador antes de aceptarlo</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            <a href="{{ route('collaborator-list') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i>Volver al listado
            </a>
        </div>
    </div>

    @php
        $formData = $collaborator->data ? json_decode($collaborator->data, true) : [];
    @endphp

    <div class="row">
        <!-- Collaborator Information -->
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-user me-2"></i>Información Personal</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted">Nombre</label>
                                <p class="fw-medium">{{ $collaborator->name }} {{ $collaborator->surname }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted">Email</label>
                                <p class="fw-medium">
                                    <i class="ti ti-mail me-1"></i>{{ $collaborator->email }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Language Combinations -->
            @if(isset($formData['language_pairs']) && count($formData['language_pairs']) > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-language me-2"></i>Combinaciones de Idiomas</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($formData['language_pairs'] as $index => $pair)
                            @if(!empty($pair))
                                @php
                                    [$sourceLanguage, $targetLanguage] = explode('|', $pair);
                                    $isNative = isset($formData['is_native'][$index]) && $formData['is_native'][$index];
                                @endphp
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="d-flex align-items-center">
                                            <strong>{{ $sourceLanguage }}</strong>
                                            <i class="ti ti-arrow-right mx-2 text-muted"></i>
                                            <strong>{{ $targetLanguage }}</strong>
                                            @if($isNative)
                                                <span class="badge bg-success ms-2">Nativo</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Services/Fares -->
            @if(isset($formData['fare_ids']) && count($formData['fare_ids']) > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-briefcase me-2"></i>Servicios que Ofrece</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($formData['fare_ids'] as $fareId)
                            @if(!empty($fareId))
                                <span class="badge bg-label-primary rounded-pill">
                                    ID: {{ $fareId }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Additional Form Data -->
            @if(!empty($formData))
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-file-text me-2"></i>Información Adicional del Formulario</h5>
                </div>
                <div class="card-body">
                    @foreach($formData as $key => $value)
                        @if(!in_array($key, ['language_pairs', 'is_native', 'fare_ids']) && !empty($value))
                            <div class="mb-3">
                                <label class="form-label text-muted">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                                @if(is_array($value))
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($value as $item)
                                            @if(!empty($item))
                                                <span class="badge bg-label-info rounded-pill">{{ $item }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <p class="fw-medium">{{ $value }}</p>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Decision Panel -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-check-box me-2"></i>Decisión de Aceptación</h5>
                </div>
                <div class="card-body">
                    <form id="decision-form" method="POST" action="{{ route('collaborator.process-accept', $collaborator->id) }}">
                        @csrf
                        
                        <div class="alert alert-info">
                            <div class="d-flex">
                                <i class="ti ti-info-circle me-2"></i>
                                <div>
                                    <strong>Información importante:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Al aceptar, se creará un usuario automáticamente</li>
                                        <li>Se le asignará el rol de "colaborador"</li>
                                        <li>Se agregará al equipo actual</li>
                                        <li>Recibirá acceso completo al sistema</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Decisión *</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="action" value="accept" id="accept" required>
                                <label class="form-check-label text-success fw-medium" for="accept">
                                    <i class="ti ti-check me-1"></i>Aceptar colaborador
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="action" value="reject" id="reject" required>
                                <label class="form-check-label text-danger fw-medium" for="reject">
                                    <i class="ti ti-x me-1"></i>Rechazar colaborador
                                </label>
                            </div>
                        </div>

                        <div class="mb-3 d-none" id="rejection-reason-field">
                            <label class="form-label">Motivo del rechazo *</label>
                            <textarea class="form-control" name="rejection_reason" rows="3" 
                                placeholder="Explica el motivo del rechazo..."></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="ti ti-check me-1"></i>Confirmar Decisión
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Application Summary -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ti ti-info-circle me-2"></i>Resumen de Solicitud</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Fecha de registro:</span>
                        <span>{{ $collaborator->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Idiomas:</span>
                        <span>{{ isset($formData['language_pairs']) ? count(array_filter($formData['language_pairs'])) : 0 }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Servicios:</span>
                        <span>{{ isset($formData['fare_ids']) ? count(array_filter($formData['fare_ids'])) : 0 }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Campos completados:</span>
                        <span>{{ count(array_filter($formData, function($value) { return !empty($value); })) }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Estado actual:</span>
                        <span class="badge bg-warning">Pendiente</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Show/hide rejection reason field
        $('input[name="action"]').on('change', function() {
            const rejectionField = $('#rejection-reason-field');
            const rejectionTextarea = $('textarea[name="rejection_reason"]');
            
            if ($(this).val() === 'reject') {
                rejectionField.removeClass('d-none');
                rejectionTextarea.attr('required', true);
            } else {
                rejectionField.addClass('d-none');
                rejectionTextarea.attr('required', false);
                rejectionTextarea.val('');
            }
        });

        // Form submission with confirmation
        $('#decision-form').on('submit', function(e) {
            e.preventDefault();
            
            const action = $('input[name="action"]:checked').val();
            const actionText = action === 'accept' ? 'aceptar' : 'rechazar';
            const actionIcon = action === 'accept' ? 'success' : 'warning';
            
            Swal.fire({
                title: `¿Confirmar ${actionText}?`,
                text: `¿Estás seguro de que deseas ${actionText} a este colaborador?`,
                icon: actionIcon,
                showCancelButton: true,
                confirmButtonText: `Sí, ${actionText}`,
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: action === 'accept' ? 'btn btn-success me-3' : 'btn btn-warning me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    this.submit();
                }
            });
        });
    });
</script>
@endpush 