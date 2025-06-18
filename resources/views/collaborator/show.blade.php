@extends('layouts/layoutMaster')

@section('title', $collaborator->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
    <div class="row">
        <!-- Collaborator Sidebar -->
        @include('collaborator.partials.sidebar')
        <!--/ Collaborator Sidebar -->

        <!-- Collaborator Content -->
        <div class="col-xl-8 col-lg-7 col-md-7">
            <!-- Tabs -->
            @include('collaborator.partials.tabs')

            <!-- Projects with bbo -->
            <div class="card mb-4">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Proyectos con bbo</h5>
                        <div class="d-flex">
                            <div class="input-group input-group-merge me-2">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="text" class="form-control" placeholder="Buscar">
                            </div>
                            <button class="btn btn-icon btn-primary">
                                <i class="ti ti-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th></th>
                                <th>PROYECTO</th>
                                <th>PM</th>
                                <th>CANTIDAD</th>
                                <th>UNIDAD</th>
                                <th>FECHA</th>
                                <th>ACCIÓN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox">
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm bg-label-info me-2">
                                            <span class="avatar-initial rounded-circle">F</span>
                                        </div>
                                        <div>
                                            <span class="fw-medium">Fifa 2025</span>
                                            <small class="d-block text-muted">Manual de usuario</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Rocio</td>
                                <td>34</td>
                                <td>pág</td>
                                <td>12-04-2024</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-icon btn-text-secondary p-0" data-bs-toggle="dropdown">
                                            <i class="ti ti-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="javascript:void(0)">Ver</a>
                                            <a class="dropdown-item" href="javascript:void(0)">Editar</a>
                                            <a class="dropdown-item" href="javascript:void(0)">Eliminar</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox">
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm bg-label-danger me-2">
                                            <span class="avatar-initial rounded-circle">K</span>
                                        </div>
                                        <div>
                                            <span class="fw-medium">Kiarna</span>
                                        </div>
                                    </div>
                                </td>
                                <td>Carla</td>
                                <td>174</td>
                                <td>min</td>
                                <td>23-10-2023</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-icon btn-text-secondary p-0" data-bs-toggle="dropdown">
                                            <i class="ti ti-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="javascript:void(0)">Ver</a>
                                            <a class="dropdown-item" href="javascript:void(0)">Editar</a>
                                            <a class="dropdown-item" href="javascript:void(0)">Eliminar</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Mostrando 1 a 3 de 3 proyectos</span>
                        <div class="d-flex">
                            <button class="btn btn-icon btn-sm btn-outline-secondary me-1"><i class="ti ti-chevron-left"></i></button>
                            <button class="btn btn-icon btn-sm btn-primary me-1">1</button>
                            <button class="btn btn-icon btn-sm btn-outline-secondary me-1"><i class="ti ti-chevron-right"></i></button>
                            <button class="btn btn-icon btn-sm btn-outline-secondary"><i class="ti ti-chevrons-right"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Experience -->
            <div class="card mb-4">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Experiencia</h5>
                        <div class="d-flex">
                            <div class="input-group input-group-merge me-2">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="text" class="form-control" placeholder="Buscar">
                            </div>
                            <button class="btn btn-icon btn-primary">
                                <i class="ti ti-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th></th>
                                <th>PROYECTO</th>
                                <th>PUESTO</th>
                                <th>AÑO</th>
                                <th>IDIOMAS</th>
                                <th>NOTAS</th>
                                <th>ACCIÓN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox">
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm bg-label-info me-2">
                                            <span class="avatar-initial rounded-circle">T</span>
                                        </div>
                                        <span>Titanic</span>
                                    </div>
                                </td>
                                <td>Qué hizo</td>
                                <td>1995</td>
                                <td>ES<br>EN</td>
                                <td>Aquí algo de la experiencia</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-icon btn-text-secondary p-0" data-bs-toggle="dropdown">
                                            <i class="ti ti-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="javascript:void(0)">Ver</a>
                                            <a class="dropdown-item" href="javascript:void(0)">Editar</a>
                                            <a class="dropdown-item" href="javascript:void(0)">Eliminar</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Mostrando 1 a 3 de 3 proyectos</span>
                        <div class="d-flex">
                            <button class="btn btn-icon btn-sm btn-outline-secondary me-1"><i class="ti ti-chevron-left"></i></button>
                            <button class="btn btn-icon btn-sm btn-primary me-1">1</button>
                            <button class="btn btn-icon btn-sm btn-outline-secondary me-1"><i class="ti ti-chevron-right"></i></button>
                            <button class="btn btn-icon btn-sm btn-outline-secondary"><i class="ti ti-chevrons-right"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity -->
            <div class="card mb-4">
                <div class="card-header border-bottom">
                    <h5 class="mb-0">Actividad</h5>
                </div>
                <div class="card-body">
                    <ul class="timeline ps-3 pt-2">
                        <li class="timeline-item pb-4 border-left-dashed">
                            <span class="timeline-indicator timeline-indicator-success">
                                <i class="ti ti-login"></i>
                            </span>
                            <div class="timeline-event ps-0 pb-0">
                                <div class="timeline-header">
                                    <h6 class="mb-0">Acceso a la plataforma</h6>
                                    <small class="text-muted">Hace 10 min</small>
                                </div>
                            </div>
                        </li>
                        <li class="timeline-item pb-4 border-left-dashed">
                            <span class="timeline-indicator timeline-indicator-primary">
                                <i class="ti ti-file-text"></i>
                            </span>
                            <div class="timeline-event ps-0 pb-0">
                                <div class="timeline-header">
                                    <h6 class="mb-0">Envio Acuerdo de colaboración</h6>
                                    <small class="text-muted">Hace 25 min</small>
                                </div>
                                <div class="d-flex flex-wrap">
                                    <div class="avatar me-3">
                                        <img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar">
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Acuerdo de colaboración.pdf</h6>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li class="timeline-item pb-4 border-left-dashed">
                            <span class="timeline-indicator timeline-indicator-warning">
                                <i class="ti ti-user-check"></i>
                            </span>
                            <div class="timeline-event ps-0 pb-0">
                                <div class="timeline-header">
                                    <h6 class="mb-0">Aceptado como colaborador</h6>
                                    <small class="text-muted">Hace 45 min</small>
                                </div>
                                <div class="d-flex flex-wrap">
                                    <div class="avatar me-3">
                                        <img src="{{ asset('assets/img/avatars/2.png') }}" alt="Avatar">
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Aceptado por Romi</h6>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li class="timeline-item border-transparent">
                            <span class="timeline-indicator timeline-indicator-info">
                                <i class="ti ti-send"></i>
                            </span>
                            <div class="timeline-event ps-0 pb-0">
                                <div class="timeline-header">
                                    <h6 class="mb-0">Envio de datos personales</h6>
                                    <small class="text-muted">Hace 2 días</small>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Services -->
            <div class="card mb-4">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Servicios</h5>
                    <a href="javascript:void(0)" class="text-secondary"><i class="ti ti-pencil"></i></a>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge bg-label-primary rounded-pill me-1">Subtitulado</span>
                        <span class="badge bg-label-primary rounded-pill me-1">Transcripción</span>
                        <span class="badge bg-label-primary rounded-pill">Documentos</span>
                    </div>
                    <div class="mt-3">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">Transcripción</li>
                            <li class="mb-2">Guiones</li>
                            <li class="mb-2">Doblaje</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Work Software -->
            <div class="card mb-4">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Software de trabajo</h5>
                    <a href="javascript:void(0)" id="toggleSoftwareEdit" class="text-secondary">
                        <i class="ti ti-edit ti-sm"></i>
                    </a>
                </div>
                <div class="card-body pt-4">
                    <!-- Read-only view -->
                    <div id="software-display">
                        @if($collaborator->softwares && $collaborator->softwares->count() > 0)
                            @foreach($collaborator->softwares as $software)
                                <span class="badge bg-label-primary rounded-pill me-1 mb-1">
                                    {{ $software->name }}{{ $software->type ? ' (' . $software->type->name . ')' : '' }}
                                </span>
                            @endforeach
                        @else
                            <div class="mt-2">
                                <span class="text-muted">No hay software asignado</span>
                            </div>
                        @endif
                    </div>
                    
                    <form id="software-edit-form" class="mt-3 d-none">
                        @csrf
                        <x-software-select 
                            id="collaborator_software_ids" 
                            label="Software que domina"
                            :selected="$collaborator->softwares ? $collaborator->softwares->pluck('id')->toArray() : []"
                        />
                        <div class="mt-3">
                            <button type="button" id="saveSoftware" class="btn btn-primary btn-sm">Guardar</button>
                            <button type="button" id="cancelSoftwareEdit" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Topics -->
            <div class="card">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Temáticas</h5>
                    <a href="javascript:void(0)" class="text-secondary"><i class="ti ti-pencil"></i></a>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge bg-label-primary rounded-pill me-1">Medicina</span>
                        <span class="badge bg-label-primary rounded-pill me-1">Viajes</span>
                        <span class="badge bg-label-primary rounded-pill">Técnica</span>
                    </div>
                    <div class="mt-3">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">Ciencia</li>
                            <li class="mb-2">Cine</li>
                            <li class="mb-2">Letras</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Collaborator Content -->
    </div>

    <!-- Include Valoration Modal -->
    @include('collaborator.partials.valoration-modal')

    <!-- Modal para vincular usuario en colaboradores -->
    <div class="modal fade" id="linkCollaboratorUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vincular Usuario al Colaborador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="linkCollaboratorUserTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="existing-collab-user-tab" data-bs-toggle="tab" data-bs-target="#existing-collab-user" type="button" role="tab">
                                <i class="ti ti-user ti-xs me-1"></i>Usuario Existente
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="new-collab-user-tab" data-bs-toggle="tab" data-bs-target="#new-collab-user" type="button" role="tab">
                                <i class="ti ti-user-plus ti-xs me-1"></i>Crear Usuario
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3" id="linkCollaboratorUserTabsContent">
                        <!-- Existing User Tab -->
                        <div class="tab-pane fade show active" id="existing-collab-user" role="tabpanel">
                            <form id="linkExistingCollaboratorUserForm">
                                @csrf
                                <div class="mb-3">
                                    <label for="collab_user_search" class="form-label">Buscar Usuario</label>
                                    <select id="collab_user_search" name="user_id" class="form-select select2" required>
                                        <option value="">-- Seleccionar usuario --</option>
                                        @foreach(\App\Models\User::whereHas('teams', function($q) { $q->where('team_id', auth()->user()->currentTeam->id); })->orderBy('name')->get() as $user)
                                            <option value="{{ $user->id }}" data-email="{{ $user->email }}" data-role="{{ $user->roles->first()->name ?? 'user' }}">
                                                {{ $user->name }} ({{ $user->email }}) - {{ $user->roles->first()->name ?? 'user' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Vincular Usuario</button>
                                </div>
                            </form>
                        </div>

                        <!-- New User Tab -->
                        <div class="tab-pane fade" id="new-collab-user" role="tabpanel">
                            <form id="createCollaboratorUserForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="new_collab_user_name" class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" id="new_collab_user_name" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="new_collab_user_email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="new_collab_user_email" name="email" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="new_collab_user_phone" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="new_collab_user_phone" name="phone">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="new_collab_user_role" class="form-label">Rol *</label>
                                        <select class="form-select" id="new_collab_user_role" name="role" required>
                                            <option value="">-- Seleccionar rol --</option>
                                            @foreach(\Spatie\Permission\Models\Role::all() as $role)
                                                <option value="{{ $role->name }}" {{ $role->name === 'collaborator' ? 'selected' : '' }}>
                                                    {{ ucfirst($role->name) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="new_collab_user_password" class="form-label">Contraseña temporal *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="new_collab_user_password" name="password" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="generateCollaboratorPassword()">
                                                <i class="ti ti-refresh ti-xs"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">El usuario deberá cambiar esta contraseña en su primer acceso</small>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Crear y Vincular</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection 

@push('scripts')
<script type="text/javascript">
    // Wait for the document to be fully loaded
    window.addEventListener('load', function() {
        console.log('Window loaded, setting up events...');
        
        // Initialize Select2
        try {
            $('#collaborator_software_ids').select2({
                placeholder: 'Seleccionar software',
                allowClear: true,
                closeOnSelect: false,
                width: '100%'
            });
            console.log('Select2 initialized');
        } catch (e) {
            console.error('Error initializing Select2:', e);
        }
        
        // Toggle between view and edit
        document.getElementById('toggleSoftwareEdit').addEventListener('click', function() {
            console.log('Toggle edit clicked');
            document.getElementById('software-display').classList.add('d-none');
            document.getElementById('software-edit-form').classList.remove('d-none');
            this.classList.add('d-none');
        });

        // Cancel edit
        document.getElementById('cancelSoftwareEdit').addEventListener('click', function() {
            console.log('Cancel edit clicked');
            document.getElementById('software-edit-form').classList.add('d-none');
            document.getElementById('software-display').classList.remove('d-none');
            document.getElementById('toggleSoftwareEdit').classList.remove('d-none');
        });

        // Save changes
        document.getElementById('saveSoftware').addEventListener('click', function() {
            console.log('Save software clicked');
            
            // Use vanilla JS or jQuery depending on what's available
            let softwareIds;
            if (typeof $ !== 'undefined' && $.fn.select2) {
                softwareIds = $('#collaborator_software_ids').val();
            } else {
                softwareIds = Array.from(document.getElementById('collaborator_software_ids').selectedOptions).map(option => option.value);
            }
            
            const collaboratorId = {{ $collaborator->id }};
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            console.log('Software IDs:', softwareIds);
            console.log('Collaborator ID:', collaboratorId);
            console.log('CSRF Token:', csrfToken);
            
            // Use fetch API instead of jQuery AJAX
            fetch(`/collaborator/${collaboratorId}/update-software`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    software_ids: softwareIds
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Fetch Success:', data);
                if (data.success) {
                    // Update the badges in the view
                    let badgesHtml = '';
                    if (data.softwares && data.softwares.length > 0) {
                        data.softwares.forEach(function(software) {
                            const softwareText = software.name + (software.type_name ? ' (' + software.type_name + ')' : '');
                            badgesHtml += `<span class="badge bg-label-primary rounded-pill me-1 mb-1">${softwareText}</span>`;
                        });
                    } else {
                        badgesHtml = '<div class="mt-2"><span class="text-muted">No hay software asignado</span></div>';
                    }
                    
                    document.getElementById('software-display').innerHTML = badgesHtml;
                    
                    // Return to read-only view
                    document.getElementById('software-edit-form').classList.add('d-none');
                    document.getElementById('software-display').classList.remove('d-none');
                    document.getElementById('toggleSoftwareEdit').classList.remove('d-none');
                    
                    // Show success notification
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'Software actualizado correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert('Software actualizado correctamente');
                    }
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('Error al actualizar el software');
            });
        });

        // Collaborator User linking functionality
        function showLinkCollaboratorUserModal(collaboratorId) {
            window.currentCollaboratorId = collaboratorId;
            // Pre-fill collaborator data if available
            const collaboratorName = '{{ $collaborator->name ?? '' }}';
            const collaboratorEmail = '{{ $collaborator->email ?? '' }}';
            
            if (collaboratorName) {
                document.getElementById('new_collab_user_name').value = collaboratorName;
            }
            if (collaboratorEmail) {
                document.getElementById('new_collab_user_email').value = collaboratorEmail;
            }
            
            $('#linkCollaboratorUserModal').modal('show');
        }

        function unlinkCollaboratorUser(collaboratorId) {
            if (confirm('¿Estás seguro de que quieres desvincular este usuario del colaborador?')) {
                fetch(`/collaborator/${collaboratorId}/unlink-user`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: 'Usuario desvinculado correctamente',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                        location.reload();
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Error al desvincular usuario'
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al desvincular usuario'
                        });
                    }
                });
            }
        }

        function generateCollaboratorPassword() {
            const length = 10;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%";
            let password = "";
            for (let i = 0, n = charset.length; i < length; ++i) {
                password += charset.charAt(Math.floor(Math.random() * n));
            }
            document.getElementById('new_collab_user_password').value = password;
        }

        // Initialize Select2 when modal is shown
        $('#linkCollaboratorUserModal').on('shown.bs.modal', function () {
            $('#collab_user_search').select2({
                dropdownParent: $('#linkCollaboratorUserModal'),
                placeholder: 'Buscar usuario...',
                allowClear: true
            });
        });

        // Handle existing user linking for collaborators
        $('#linkExistingCollaboratorUserForm').on('submit', function(e) {
            e.preventDefault();
            
            const userId = $('#collab_user_search').val();
            if (!userId) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atención',
                        text: 'Por favor selecciona un usuario'
                    });
                }
                return;
            }

            fetch(`/collaborator/${window.currentCollaboratorId}/link-user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    user_id: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'Usuario vinculado correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                    $('#linkCollaboratorUserModal').modal('hide');
                    location.reload();
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Error al vincular usuario'
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al vincular usuario'
                    });
                }
            });
        });

        // Handle new user creation and linking for collaborators
        $('#createCollaboratorUserForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);

            fetch(`/collaborator/${window.currentCollaboratorId}/create-and-link-user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'Usuario creado y vinculado correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                    $('#linkCollaboratorUserModal').modal('hide');
                    location.reload();
                } else {
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error de validación',
                                    text: data.errors[field][0]
                                });
                            }
                        });
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Error al crear usuario'
                            });
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al crear usuario'
                    });
                }
            });
        });

        // Generate password on page load
        window.addEventListener('load', function() {
            generateCollaboratorPassword();
        });
    });
</script>
@endpush 