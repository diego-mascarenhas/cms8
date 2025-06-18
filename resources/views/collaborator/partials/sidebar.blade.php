<!-- Collaborator Sidebar -->
<div class="col-xl-4 col-lg-5 col-md-5">
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center flex-column mb-3">
                <img class="img-fluid rounded-circle mb-3" 
                    src="https://ui-avatars.com/api/?format=svg&name={{ $collaborator->name }}" 
                    height="100" width="100" alt="User avatar" />
                <h4 class="mb-1">{{ $collaborator->name ?? 'Colaborador' }}</h4>
                @if($collaborator->valoration)
                    <span class="badge bg-label-{{ $collaborator->valoration->name == 'Lista negra' ? 'danger' : ($collaborator->valoration->name == 'Top' ? 'warning' : 'primary') }} rounded-pill">
                        {{ $collaborator->valoration->icon ?? '' }} {{ $collaborator->valoration->name }}
                    </span>
                @else
                    <span class="badge bg-label-secondary rounded-pill">Sin valoración</span>
                @endif
            </div>
            <div class="d-flex flex-wrap justify-content-around mt-3 pt-3 pb-4 border-bottom">
                <div class="d-flex align-items-start gap-3 mb-2 px-3">
                    <div class="bg-label-primary p-3 rounded" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                        <i class='ti ti-folder text-primary' style="font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <p class="mb-0 fw-bold fs-4" style="line-height: 1.2;">5</p>
                        <small class="text-muted" style="line-height: 1.2;">Proyectos</small>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3 mb-2 px-3">
                    <div class="bg-label-primary p-3 rounded" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                        <i class='ti ti-clock text-primary' style="font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <p class="mb-0 fw-bold fs-4" style="line-height: 1.2;">648</p>
                        <small class="text-muted" style="line-height: 1.2;">Minutos</small>
                    </div>
                </div>
            </div>
            <div class="info-container">
                <ul class="list-unstyled mb-4 pt-3">
                    <li class="mb-2">
                        <span class="fw-medium me-1">Estado:</span>
                        <span class="badge bg-label-success">Activo</span>
                    </li>
                    <li class="mb-2">
                        <span class="fw-medium me-1">Email:</span>
                        <span>{{ $collaborator->email ?? 'Sin email' }}</span>
                    </li>
                    <li class="mb-2">
                        <span class="fw-medium me-1">Contacto:</span>
                        <span>{{ $collaborator->phone ?? 'Sin teléfono' }}</span>
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
                    <li class="mb-2 pt-3">
                        @if ($collaborator->user_id)
                            @php $linkedUser = \App\Models\User::find($collaborator->user_id); @endphp
                            @if ($linkedUser)
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="badge bg-label-primary me-2">{{ $linkedUser->roles->first()->name ?? 'Sin rol' }}</span>
                                        <span>{{ $linkedUser->name }} ({{ $linkedUser->email }})</span>
                                    </div>
                                    @can('collaborator.edit')
                                        <a href="{{ route('user-unlink.show', ['collaborator', $collaborator->id]) }}" class="btn btn-sm btn-icon btn-outline-secondary">
                                            <i class="ti ti-unlink ti-xs"></i>
                                        </a>
                                    @endcan
                                </div>
                            @else
                                <span class="badge bg-label-danger">Usuario no encontrado</span>
                                @can('collaborator.edit')
                                    <a href="{{ route('user-link.show', ['collaborator', $collaborator->id]) }}" class="btn btn-sm btn-outline-primary ms-2">
                                        <i class="ti ti-link ti-xs me-1"></i>Vincular usuario
                                    </a>
                                @endcan
                            @endif
                        @else
                            @can('collaborator.edit')
                                <a href="{{ route('user-link.show', ['collaborator', $collaborator->id]) }}" class="btn btn-sm btn-outline-primary ms-2">
                                    <i class="ti ti-link ti-xs me-1"></i>Vincular usuario
                                </a>
                            @endcan
                        @endif
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