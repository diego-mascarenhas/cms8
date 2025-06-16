@extends('layouts/layoutMaster')

@section('title', 'Ausencias')

@section('content')
<div class="row">
    <!-- Collaborator Sidebar -->
    <div class="col-xl-4 col-lg-5 col-md-5">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center flex-column mb-3">
                    <img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle mb-3" width="100" height="100">
                    <h4 class="mb-1">{{ $collaborator->name ?? 'Colaborador' }}</h4>
                    @if($collaborator->valoration)
                        <span class="badge bg-label-{{ $collaborator->valoration->name == 'Lista negra' ? 'danger' : ($collaborator->valoration->name == 'Top' ? 'warning' : 'primary') }} rounded-pill">
                            {{ $collaborator->valoration->icon ?? '' }} {{ $collaborator->valoration->name }}
                        </span>
                    @else
                        <span class="badge bg-label-secondary rounded-pill">Sin valoración</span>
                    @endif
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
                        <a href="javascript:void(0)" class="btn btn-label-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#valorationModal">
                            <i class="ti ti-star me-1"></i>Valorar
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

    <!-- Ausencias Content -->
    <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Tabs -->
        <div class="d-flex mb-3">
            <a href="{{ route('collaborator.show', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary me-3">
                <i class="ti ti-refresh me-1"></i>Resumen
            </a>
            <a href="{{ route('collaborator.rates', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary me-3">
                <i class="ti ti-tag me-1"></i>Tarifas
            </a>
            <a href="{{ route('collaborator.absences', ['id' => $collaborator->id]) }}" class="btn btn-primary me-3">
                <i class="ti ti-users me-1"></i>Ausencias
            </a>
            <a href="{{ route('collaborator.notifications', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary">
                <i class="ti ti-bell me-1"></i>Notificaciones
            </a>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-4">Ausencias</h5>
                <p>Ejemplo de contenido para la sección de ausencias.</p>
            </div>
        </div>
    </div>
    <!--/ Ausencias Content -->
</div>

<!-- Valoration Modal -->
<div class="modal fade" id="valorationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Valorar colaborador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="valoration_id" class="form-label">Selecciona una valoración</label>
                    <select class="form-select" id="valoration_id" name="valoration_id">
                        @foreach(\App\Models\ContactValoration::getOptions() as $id => $name)
                            <option value="{{ $id }}" {{ $collaborator->valoration_id == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveValoration">Guardar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Save valoration
        document.getElementById('saveValoration').addEventListener('click', function() {
            const valorationId = document.getElementById('valoration_id').value;
            const collaboratorId = {{ $collaborator->id }};
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            fetch(`/collaborator/${collaboratorId}/update-valoration`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    valoration_id: valorationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close the modal
                    const modalElement = document.getElementById('valorationModal');
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    modal.hide();
                    
                    // Update the badge
                    const valoration = data.valoration;
                    const badgeClass = valoration.name === 'Lista negra' ? 'danger' : 
                                     (valoration.name === 'Top' ? 'warning' : 'primary');
                    
                    const badgeHtml = `
                        <span class="badge bg-label-${badgeClass} rounded-pill">
                            ${valoration.icon} ${valoration.name}
                        </span>
                    `;
                    
                    // Find the badge element (it's the first badge after the h4)
                    const badgeContainer = document.querySelector('.d-flex.align-items-center.flex-column.mb-3');
                    const badgeElement = badgeContainer.querySelector('.badge');
                    badgeElement.outerHTML = badgeHtml;
                    
                    // Show success notification
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'Valoración actualizada correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert('Valoración actualizada correctamente');
                    }
                }
            })
            .catch(error => {
                console.error('Error updating valoration:', error);
                alert('Error al actualizar la valoración');
            });
        });
    });
</script>
@endpush 