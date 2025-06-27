@extends('layouts/layoutMaster')

@section('title', $collaborator->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flag-icons/flag-icons.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .fi {
            font-size: 1.2em;
            vertical-align: middle;
        }
    </style>
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
                        <h5 class="mb-0">Proyectos con bbo ({{ $collaborator->projects->count() }})</h5>
                        <div class="d-flex">
                            <div class="input-group input-group-merge me-2">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="text" class="form-control" placeholder="Buscar" id="projects-search">
                            </div>
                            @can('project.create')
                            <a href="{{ route('project.create') }}" class="btn btn-icon btn-primary" title="Crear nuevo proyecto">
                                <i class="ti ti-plus"></i>
                            </a>
                            @endcan
                        </div>
                    </div>
                </div>
                @if($collaborator->projects && $collaborator->projects->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover" id="projects-table">
                        <thead>
                            <tr>
                                <th>PROYECTO</th>
                                <th>PM</th>
                                <th>CLIENTE</th>
                                <th>ESTADO</th>
                                <th>FECHA</th>
                                <th>ACCIÓN</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($collaborator->projects as $project)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm bg-label-{{ ['primary', 'success', 'info', 'warning', 'danger'][array_rand(['primary', 'success', 'info', 'warning', 'danger'])] }} me-2">
                                            <span class="avatar-initial rounded-circle">{{ strtoupper(substr($project->name, 0, 1)) }}</span>
                                        </div>
                                        <div>
                                            <span class="fw-medium">{{ $project->name }}</span>
                                            @if($project->real_name && $project->real_name !== $project->name)
                                                <small class="d-block text-muted">{{ $project->real_name }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $project->responsible ? $project->responsible->name : '-' }}</td>
                                <td>{{ $project->enterprise ? $project->enterprise->name : '-' }}</td>
                                <td>
                                    @if($project->status)
                                        <span class="badge {{ $project->status->label_class ?? 'bg-label-secondary' }}">
                                            {{ $project->status->name }}
                                        </span>
                                    @else
                                        <span class="badge bg-label-secondary">Sin estado</span>
                                    @endif
                                </td>
                                <td>
                                    @if($project->date_end)
                                        {{ \Carbon\Carbon::parse($project->date_end)->format('d-m-Y') }}
                                    @elseif($project->date_material)
                                        {{ \Carbon\Carbon::parse($project->date_material)->format('d-m-Y') }}
                                    @else
                                        {{ \Carbon\Carbon::parse($project->created_at)->format('d-m-Y') }}
                                    @endif
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn p-0" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            @can('project.show')
                                            <a class="dropdown-item" href="{{ route('project.show', $project->id) }}">
                                                <i class="ti ti-eye me-2"></i>Ver
                                            </a>
                                            @endcan
                                            @can('project.edit')
                                            <a class="dropdown-item" href="{{ route('project.edit', $project->id) }}">
                                                <i class="ti ti-edit me-2"></i>Editar
                                            </a>
                                            @endcan
                                            @if($project->pivot && $project->pivot->status)
                                            <div class="dropdown-divider"></div>
                                            <h6 class="dropdown-header">Estado colaboración:</h6>
                                            <span class="dropdown-item-text">
                                                @switch($project->pivot->status)
                                                    @case('sent')
                                                        <i class="ti ti-clock me-1 text-warning"></i>Mensaje enviado
                                                        @break
                                                    @case('viewed')
                                                        <i class="ti ti-eye me-1 text-info"></i>Visto
                                                        @break
                                                    @case('accepted')
                                                        <i class="ti ti-check me-1 text-success"></i>Aceptado
                                                        @break
                                                    @case('rejected')
                                                        <i class="ti ti-x me-1 text-danger"></i>Rechazado
                                                        @break
                                                @endswitch
                                            </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Mostrando {{ $collaborator->projects->count() }} proyecto{{ $collaborator->projects->count() !== 1 ? 's' : '' }}</span>
                        @if($collaborator->projects->count() > 0)
                        <small class="text-muted">
                            Último proyecto: {{ $collaborator->projects->first()->created_at->format('d/m/Y') }}
                        </small>
                        @endif
                    </div>
                </div>
                @else
                <!-- Empty State -->
                <div class="card-body text-center py-5">
                    <div class="avatar avatar-xl mx-auto mb-3">
                        <span class="avatar-initial rounded-circle bg-label-secondary">
                            <i class="ti ti-briefcase ti-md"></i>
                        </span>
                    </div>
                    <h5 class="mb-2">No hay proyectos asociados</h5>
                    <p class="mb-4 text-muted">Este colaborador aún no está asociado a ningún proyecto.</p>
                    @can('project.create')
                    <a href="{{ route('project.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i>Crear primer proyecto
                    </a>
                    @endcan
                </div>
                @endif
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
                    <a href="javascript:void(0)" id="toggleServicesEdit" class="text-secondary">
                        <i class="ti ti-edit ti-sm"></i>
                    </a>
                </div>
                <div class="card-body pt-4">
                    <!-- Read-only view -->
                    <div id="services-display">
                        @if($collaborator->fares && $collaborator->fares->count() > 0)
                            @foreach($collaborator->fares as $fare)
                                <span class="badge bg-label-primary rounded-pill me-1 mb-1">
                                    {{ $fare->name }}{{ $fare->type ? ' (' . $fare->type->name . ')' : '' }}
                                </span>
                            @endforeach
                        @else
                            <div class="mt-2">
                                <span class="text-muted">No hay servicios asignados</span>
                            </div>
                        @endif
                    </div>
                    
                    <form id="services-edit-form" class="mt-3 d-none">
                        @csrf
                        <x-fare-select 
                            id="collaborator_fare_ids" 
                            name="fare_ids[]"
                            label="Servicios que ofrece"
                            placeholder="Seleccione servicios"
                            :selected="$collaborator->fares ? $collaborator->fares->pluck('id')->toArray() : []"
                        />
                        <div class="mt-3">
                            <button type="button" id="saveServices" class="btn btn-primary btn-sm">Guardar</button>
                            <button type="button" id="cancelServicesEdit" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </form>
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
                    <a href="javascript:void(0)" id="toggleTopicsEdit" class="text-secondary">
                        <i class="ti ti-edit ti-sm"></i>
                    </a>
                </div>
                <div class="card-body pt-4">
                    <!-- Read-only view -->
                    <div id="topics-display">
                        @if($collaborator->topics && $collaborator->topics->count() > 0)
                            @foreach($collaborator->topics as $topic)
                                <span class="badge bg-label-primary rounded-pill me-1 mb-1">
                                    {{ $topic->name }}
                                </span>
                            @endforeach
                        @else
                            <div class="mt-2">
                                <span class="text-muted">No hay temáticas asignadas</span>
                            </div>
                        @endif
                    </div>
                    
                    <form id="topics-edit-form" class="mt-3 d-none">
                        @csrf
                        <x-topics-select 
                            id="collaborator_topic_ids" 
                            label="Temáticas de especialización"
                            :selected="$collaborator->topics ? $collaborator->topics->pluck('id')->toArray() : []"
                        />
                        <div class="mt-3">
                            <button type="button" id="saveTopics" class="btn btn-primary btn-sm">Guardar</button>
                            <button type="button" id="cancelTopicsEdit" class="btn btn-outline-secondary btn-sm">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- /Collaborator Content -->
    </div>

    <!-- Include Valoration Modal -->
    @include('collaborator.partials.valoration-modal')


@endsection 

@push('scripts')
<script type="text/javascript">
    // Document ready function
    $(document).ready(function() {
        console.log('Document ready, setting up collaborator events...');
        
        // Initialize Select2 for software, services and topics
        try {
            $('#collaborator_software_ids, #collaborator_fare_ids, #collaborator_topic_ids').select2({
                placeholder: 'Seleccionar',
                allowClear: true,
                closeOnSelect: false,
                width: '100%'
            });
            console.log('Select2 initialized');
        } catch (e) {
            console.error('Error initializing Select2:', e);
        }
        
        // Toggle between view and edit for software
        $('#toggleSoftwareEdit').on('click', function() {
            console.log('Toggle edit clicked');
            $('#software-display').addClass('d-none');
            $('#software-edit-form').removeClass('d-none');
            $(this).addClass('d-none');
        });

        // Cancel edit for software
        $('#cancelSoftwareEdit').on('click', function() {
            console.log('Cancel edit clicked');
            $('#software-edit-form').addClass('d-none');
            $('#software-display').removeClass('d-none');
            $('#toggleSoftwareEdit').removeClass('d-none');
        });
        
        // Toggle between view and edit for services
        $('#toggleServicesEdit').on('click', function() {
            console.log('Toggle services edit clicked');
            $('#services-display').addClass('d-none');
            $('#services-edit-form').removeClass('d-none');
            $(this).addClass('d-none');
        });

        // Cancel edit for services
        $('#cancelServicesEdit').on('click', function() {
            console.log('Cancel services edit clicked');
            $('#services-edit-form').addClass('d-none');
            $('#services-display').removeClass('d-none');
            $('#toggleServicesEdit').removeClass('d-none');
        });
        
        // Toggle between view and edit for topics
        $('#toggleTopicsEdit').on('click', function() {
            console.log('Toggle topics edit clicked');
            $('#topics-display').addClass('d-none');
            $('#topics-edit-form').removeClass('d-none');
            $(this).addClass('d-none');
        });

        // Cancel edit for topics
        $('#cancelTopicsEdit').on('click', function() {
            console.log('Cancel topics edit clicked');
            $('#topics-edit-form').addClass('d-none');
            $('#topics-display').removeClass('d-none');
            $('#toggleTopicsEdit').removeClass('d-none');
        });

        // Save changes for software
        $('#saveSoftware').on('click', function() {
            console.log('Save software clicked');
            
            const softwareIds = $('#collaborator_software_ids').val() || [];
            const collaboratorId = {{ $collaborator->id }};
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            
            console.log('Software IDs:', softwareIds);
            console.log('Collaborator ID:', collaboratorId);
            
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
                    
                    $('#software-display').html(badgesHtml);
                    
                    // Return to read-only view
                    $('#software-edit-form').addClass('d-none');
                    $('#software-display').removeClass('d-none');
                    $('#toggleSoftwareEdit').removeClass('d-none');
                    
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
        
        // Save changes for services
        $('#saveServices').on('click', function() {
            console.log('Save services clicked');
            
            const fareIds = $('#collaborator_fare_ids').val() || [];
            const collaboratorId = {{ $collaborator->id }};
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            
            console.log('Service IDs:', fareIds);
            console.log('Collaborator ID:', collaboratorId);
            
            fetch(`/collaborator/${collaboratorId}/update-services`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    fare_ids: fareIds
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Fetch Success:', data);
                if (data.success) {
                    // Update the badges in the view
                    let badgesHtml = '';
                    if (data.services && data.services.length > 0) {
                        data.services.forEach(function(service) {
                            const serviceText = service.name + (service.type_name ? ' (' + service.type_name + ')' : '');
                            badgesHtml += `<span class="badge bg-label-primary rounded-pill me-1 mb-1">${serviceText}</span>`;
                        });
                    } else {
                        badgesHtml = '<div class="mt-2"><span class="text-muted">No hay servicios asignados</span></div>';
                    }
                    
                    $('#services-display').html(badgesHtml);
                    
                    // Return to read-only view
                    $('#services-edit-form').addClass('d-none');
                    $('#services-display').removeClass('d-none');
                    $('#toggleServicesEdit').removeClass('d-none');
                    
                    // Show success notification
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'Servicios actualizados correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert('Servicios actualizados correctamente');
                    }
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('Error al actualizar los servicios');
            });
        });
        
        // Save changes for topics
        $('#saveTopics').on('click', function() {
            console.log('Save topics clicked');
            
            const topicIds = $('#collaborator_topic_ids').val() || [];
            const collaboratorId = {{ $collaborator->id }};
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            
            console.log('Topic IDs:', topicIds);
            console.log('Collaborator ID:', collaboratorId);
            
            fetch(`/collaborator/${collaboratorId}/update-topics`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    topic_ids: topicIds
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Fetch Success:', data);
                if (data.success) {
                    // Update the badges in the view
                    let badgesHtml = '';
                    if (data.topics && data.topics.length > 0) {
                        data.topics.forEach(function(topic) {
                            badgesHtml += `<span class="badge bg-label-primary rounded-pill me-1 mb-1">${topic.name}</span>`;
                        });
                    } else {
                        badgesHtml = '<div class="mt-2"><span class="text-muted">No hay temáticas asignadas</span></div>';
                    }
                    
                    $('#topics-display').html(badgesHtml);
                    
                    // Return to read-only view
                    $('#topics-edit-form').addClass('d-none');
                    $('#topics-display').removeClass('d-none');
                    $('#toggleTopicsEdit').removeClass('d-none');
                    
                    // Show success notification
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'Temáticas actualizadas correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert('Temáticas actualizadas correctamente');
                    }
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('Error al actualizar las temáticas');
            });
        });

        // Projects search functionality
        $('#projects-search').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('#projects-table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

    });
</script>
@endpush 