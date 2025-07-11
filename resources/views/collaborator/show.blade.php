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

            <!-- Quick Actions -->
            <div class="d-flex justify-content-end mb-3">
                @can('notification.create')
                <a href="{{ route('notification.create') }}?contact_id={{ $collaborator->id }}" class="btn btn-warning btn-sm waves-effect waves-light">
                    <i class="ti ti-bell me-1"></i>Enviar notificación
                </a>
                @endcan
            </div>

            <!-- Projects with bbo -->
            <div class="card mb-4">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Proyectos con bbo</h5>
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

            <!-- Portfolio -->
            <div class="card mb-4">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Experiencia</h5>
                        <div class="d-flex">
                            <div class="input-group input-group-merge me-2">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="text" class="form-control" placeholder="Buscar" id="portfolio-search">
                            </div>
                            @can('collaborator.edit')
                            <button class="btn btn-icon btn-primary" id="add-portfolio-btn">
                                <i class="ti ti-plus"></i>
                            </button>
                            @endcan
                        </div>
                    </div>
                </div>
                @if($collaborator->portfolios && $collaborator->portfolios->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover" id="portfolio-table">
                        <thead>
                            <tr>
                                <th>PROYECTO</th>
                                <th>PUESTO</th>
                                <th>AÑO</th>
                                <th>IDIOMAS</th>
                                <th>NOTAS</th>
                                <th>ACCIÓN</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($collaborator->portfolios as $portfolio)
                            <tr data-portfolio-id="{{ $portfolio->id }}" 
                                data-title="{{ $portfolio->title }}"
                                data-description="{{ $portfolio->description }}"
                                data-position="{{ $portfolio->position }}"
                                data-year="{{ $portfolio->year }}"
                                data-notes="{{ $portfolio->notes }}"
                                data-languages="{{ json_encode($portfolio->languages) }}">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm bg-label-{{ ['primary', 'success', 'info', 'warning', 'danger'][array_rand(['primary', 'success', 'info', 'warning', 'danger'])] }} me-2">
                                            <span class="avatar-initial rounded-circle">{{ strtoupper(substr($portfolio->title, 0, 1)) }}</span>
                                        </div>
                                        <div>
                                            <span class="fw-medium">{{ $portfolio->title }}</span>
                                            @if($portfolio->description)
                                                <small class="d-block text-muted">{{ Str::limit($portfolio->description, 50) }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $portfolio->position ?? '-' }}</td>
                                <td>{{ $portfolio->year ?? '-' }}</td>
                                <td>
                                    @if($portfolio->languages && is_array($portfolio->languages))
                                        @foreach($portfolio->languages as $language)
                                            @if(is_array($language) && isset($language['source']) && isset($language['target']))
                                                {{ strtoupper(explode('-', $language['source'])[0]) }} → {{ strtoupper(explode('-', $language['target'])[0]) }}@if(!$loop->last)<br>@endif
                                            @else
                                                {{ strtoupper($language) }}@if(!$loop->last)<br>@endif
                                            @endif
                                        @endforeach
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $portfolio->notes ? Str::limit($portfolio->notes, 50) : '-' }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn p-0" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="javascript:void(0)" onclick="viewPortfolio({{ $portfolio->id }})">
                                                <i class="ti ti-eye me-2"></i>Ver
                                            </a>
                                            @can('collaborator.edit')
                                            <a class="dropdown-item" href="javascript:void(0)" onclick="editPortfolio({{ $portfolio->id }})">
                                                <i class="ti ti-edit me-2"></i>Editar
                                            </a>
                                            <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deletePortfolio({{ $portfolio->id }})">
                                                <i class="ti ti-trash me-2"></i>Eliminar
                                            </a>
                                            @endcan
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
                        <span>Mostrando {{ $collaborator->portfolios->count() }} proyecto{{ $collaborator->portfolios->count() !== 1 ? 's' : '' }}</span>
                        @if($collaborator->portfolios->count() > 0)
                        <small class="text-muted">
                            Último proyecto: {{ $collaborator->portfolios->first()->created_at->format('d/m/Y') }}
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
                    <h5 class="mb-2">No hay experiencias registradas</h5>
                    <p class="mb-4 text-muted">Este colaborador aún no ha registrado experiencias laborales.</p>
                    @can('collaborator.edit')
                    <button class="btn btn-primary" id="add-first-portfolio-btn">
                        <i class="ti ti-plus me-1"></i>Agregar primera experiencia
                    </button>
                    @endcan
                </div>
                @endif
            </div>



            <!-- Services -->
            <div class="card mb-4 d-none">
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
                            @foreach($collaborator->fares->unique('id') as $fare)
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
                            :selected="$collaborator->fares ? $collaborator->fares->unique('id')->pluck('id')->toArray() : []"
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
        
        // Configure SweetAlert2 defaults to prevent extra buttons
        if (typeof Swal !== 'undefined') {
            Swal.mixin({
                showDenyButton: false,
                denyButtonText: false
            });
        }
        
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

        // Portfolio search functionality
        $('#portfolio-search').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('#portfolio-table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Portfolio management
        $('#add-portfolio-btn, #add-first-portfolio-btn').on('click', function() {
            showPortfolioModal();
        });

    });

    // Portfolio functions
    function showPortfolioModal(portfolioId = null) {
        const isEdit = portfolioId !== null;
        const title = isEdit ? 'Editar Experiencia' : 'Agregar Experiencia';
        
        let portfolioData = {};
        if (isEdit) {
            // Get portfolio data from the table or make an AJAX call
            portfolioData = getPortfolioData(portfolioId);
        }

        Swal.fire({
            title: title,
            showDenyButton: false,
            showCancelButton: true,
            denyButtonText: null,
            allowOutsideClick: false,
            buttonsStyling: true,
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-outline-secondary'
            },
            html: `
                <form id="portfolio-form">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Proyecto *</label>
                            <input type="text" class="form-control" name="title" placeholder="Nombre del proyecto" value="${portfolioData.title || ''}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Descripción del proyecto">${portfolioData.description || ''}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Puesto</label>
                            <input type="text" class="form-control" name="position" placeholder="Puesto desempeñado" value="${portfolioData.position || ''}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Año</label>
                            <input type="number" class="form-control" name="year" placeholder="Año" min="1900" max="${new Date().getFullYear() + 10}" value="${portfolioData.year || ''}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Variantes de idioma</label>
                            <div class="row mb-3">
                                <div class="col-md-5">
                                    <select id="source_language_portfolio" class="form-select">
                                        <option value="">Idioma origen</option>
                                        <option value="es-ES" data-flag="es">Español (España)</option>
                                        <option value="es-MX" data-flag="mx">Español (México)</option>
                                        <option value="es-AR" data-flag="ar">Español (Argentina)</option>
                                        <option value="en-US" data-flag="us">English (US)</option>
                                        <option value="en-GB" data-flag="gb">English (UK)</option>
                                        <option value="fr-FR" data-flag="fr">Français</option>
                                        <option value="de-DE" data-flag="de">Deutsch</option>
                                        <option value="it-IT" data-flag="it">Italiano</option>
                                        <option value="pt-BR" data-flag="br">Português (Brasil)</option>
                                        <option value="pt-PT" data-flag="pt">Português (Portugal)</option>
                                        <option value="zh-CN" data-flag="cn">中文 (简体)</option>
                                        <option value="ja-JP" data-flag="jp">日本語</option>
                                        <option value="ko-KR" data-flag="kr">한국어</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <select id="target_language_portfolio" class="form-select">
                                        <option value="">Idioma destino</option>
                                        <option value="es-ES" data-flag="es">Español (España)</option>
                                        <option value="es-MX" data-flag="mx">Español (México)</option>
                                        <option value="es-AR" data-flag="ar">Español (Argentina)</option>
                                        <option value="en-US" data-flag="us">English (US)</option>
                                        <option value="en-GB" data-flag="gb">English (UK)</option>
                                        <option value="fr-FR" data-flag="fr">Français</option>
                                        <option value="de-DE" data-flag="de">Deutsch</option>
                                        <option value="it-IT" data-flag="it">Italiano</option>
                                        <option value="pt-BR" data-flag="br">Português (Brasil)</option>
                                        <option value="pt-PT" data-flag="pt">Português (Portugal)</option>
                                        <option value="zh-CN" data-flag="cn">中文 (简体)</option>
                                        <option value="ja-JP" data-flag="jp">日本語</option>
                                        <option value="ko-KR" data-flag="kr">한국어</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-primary btn-sm w-100" id="add_language_pair_portfolio">
                                        <i class="ti ti-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="language-pairs-container-portfolio" class="mb-3">
                                <!-- Language pairs will be added here -->
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Notas adicionales">${portfolioData.notes || ''}</textarea>
                        </div>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: isEdit ? 'Actualizar' : 'Agregar',
            cancelButtonText: 'Cancelar',
            showDenyButton: false,
            preConfirm: () => {
                const form = document.getElementById('portfolio-form');
                const formData = new FormData(form);
                
                if (!formData.get('title')) {
                    Swal.showValidationMessage('El título del proyecto es requerido');
                    return false;
                }
                
                // Collect language pairs
                const languagePairs = [];
                document.querySelectorAll('#language-pairs-container-portfolio .language-pair-item').forEach(item => {
                    const pairValue = item.querySelector('input[name="language_pairs[]"]')?.value;
                    if (pairValue) {
                        const [source, target] = pairValue.split('|');
                        languagePairs.push({source: source, target: target});
                    }
                });
                
                const data = {
                    title: formData.get('title'),
                    description: formData.get('description'),
                    position: formData.get('position'),
                    year: formData.get('year') ? parseInt(formData.get('year')) : null,
                    languages: languagePairs,
                    notes: formData.get('notes')
                };
                
                return data;
            },
            didOpen: () => {
                // Initialize language pair functionality after modal opens
                initializeLanguagePairs();
                
                // Load existing language pairs if editing
                if (isEdit && portfolioData.languages && Array.isArray(portfolioData.languages)) {
                    portfolioData.languages.forEach(pair => {
                        if (typeof pair === 'object' && pair.source && pair.target) {
                            addLanguagePairToContainer(pair.source, pair.target);
                        } else if (typeof pair === 'string') {
                            // Handle legacy format if needed
                            console.log('Legacy language format detected:', pair);
                        }
                    });
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if (isEdit) {
                    updatePortfolio(portfolioId, result.value);
                } else {
                    createPortfolio(result.value);
                }
            }
        });
    }

    function getPortfolioData(portfolioId) {
        // Get portfolio data from the table row using data attributes
        const portfolioRow = document.querySelector(`tr[data-portfolio-id="${portfolioId}"]`);
        if (!portfolioRow) return {};
        
        let languages = [];
        try {
            const languagesData = portfolioRow.getAttribute('data-languages');
            if (languagesData && languagesData !== 'null') {
                languages = JSON.parse(languagesData);
            }
        } catch (e) {
            console.error('Error parsing languages data:', e);
            languages = [];
        }
        
        return {
            title: portfolioRow.getAttribute('data-title') || '',
            description: portfolioRow.getAttribute('data-description') || '',
            position: portfolioRow.getAttribute('data-position') || '',
            year: portfolioRow.getAttribute('data-year') || '',
            notes: portfolioRow.getAttribute('data-notes') || '',
            languages: languages
        };
    }

    function createPortfolio(data) {
        const collaboratorId = {{ $collaborator->id }};
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        
        fetch(`/collaborator/${collaboratorId}/portfolio`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                Swal.fire('¡Éxito!', result.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', result.message || 'Error al crear la experiencia', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Error al crear la experiencia', 'error');
        });
    }

    function updatePortfolio(portfolioId, data) {
        const collaboratorId = {{ $collaborator->id }};
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        
        fetch(`/collaborator/${collaboratorId}/portfolio/${portfolioId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                Swal.fire('¡Éxito!', result.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', result.message || 'Error al actualizar la experiencia', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Error al actualizar la experiencia', 'error');
        });
    }

    function editPortfolio(portfolioId) {
        showPortfolioModal(portfolioId);
    }

    function viewPortfolio(portfolioId) {
        // Get portfolio data
        const portfolioData = getPortfolioData(portfolioId);
        
        if (!portfolioData.title) {
            Swal.fire('Error', 'No se pudo cargar la información del portfolio', 'error');
            return;
        }
        
        // Format languages for display
        let languagesHtml = '-';
        if (portfolioData.languages && Array.isArray(portfolioData.languages) && portfolioData.languages.length > 0) {
            languagesHtml = portfolioData.languages.map(pair => {
                if (typeof pair === 'object' && pair.source && pair.target) {
                    const sourceFlag = getLanguageFlag(pair.source);
                    const targetFlag = getLanguageFlag(pair.target);
                    const sourceLang = getLanguageName(pair.source);
                    const targetLang = getLanguageName(pair.target);
                    return `<div class="mb-1"><i class="fi fi-${sourceFlag} me-1"></i>${sourceLang} <i class="ti ti-arrow-right mx-1 text-muted"></i> <i class="fi fi-${targetFlag} me-1"></i>${targetLang}</div>`;
                } else {
                    return `<div class="mb-1">${pair}</div>`;
                }
            }).join('');
        }
        
        Swal.fire({
            title: portfolioData.title,
            showConfirmButton: true,
            showCancelButton: false,
            showDenyButton: false,
            confirmButtonText: 'Cerrar',
            allowOutsideClick: true,
            customClass: {
                confirmButton: 'btn btn-primary'
            },
            html: `
                <div class="text-start">
                    ${portfolioData.description ? `
                        <div class="mb-3">
                            <h6 class="fw-semibold mb-2">Descripción:</h6>
                            <p class="text-muted mb-0">${portfolioData.description}</p>
                        </div>
                    ` : ''}
                    
                    <div class="row mb-3">
                        ${portfolioData.position ? `
                            <div class="col-md-6">
                                <h6 class="fw-semibold mb-1">Puesto:</h6>
                                <p class="text-muted mb-0">${portfolioData.position}</p>
                            </div>
                        ` : ''}
                        
                        ${portfolioData.year ? `
                            <div class="col-md-6">
                                <h6 class="fw-semibold mb-1">Año:</h6>
                                <p class="text-muted mb-0">${portfolioData.year}</p>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-semibold mb-2">Variantes de idioma:</h6>
                        <div class="text-muted">${languagesHtml}</div>
                    </div>
                    
                    ${portfolioData.notes ? `
                        <div class="mb-3">
                            <h6 class="fw-semibold mb-2">Notas:</h6>
                            <p class="text-muted mb-0">${portfolioData.notes}</p>
                        </div>
                    ` : ''}
                </div>
            `,
            width: '600px'
        });
    }

    function getLanguageName(languageCode) {
        const languageNames = {
            'es-ES': 'Español (España)',
            'es-MX': 'Español (México)', 
            'es-AR': 'Español (Argentina)',
            'en-US': 'English (US)',
            'en-GB': 'English (UK)',
            'fr-FR': 'Français',
            'de-DE': 'Deutsch',
            'it-IT': 'Italiano',
            'pt-BR': 'Português (Brasil)',
            'pt-PT': 'Português (Portugal)',
            'zh-CN': '中文 (简体)',
            'ja-JP': '日本語',
            'ko-KR': '한국어'
        };
        
        return languageNames[languageCode] || languageCode;
    }

    function deletePortfolio(portfolioId) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            showDenyButton: false,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            buttonsStyling: true,
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-outline-secondary'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const collaboratorId = {{ $collaborator->id }};
                const csrfToken = $('meta[name="csrf-token"]').attr('content');
                
                fetch(`/collaborator/${collaboratorId}/portfolio/${portfolioId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        Swal.fire('¡Eliminado!', result.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', result.message || 'Error al eliminar la experiencia', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Error al eliminar la experiencia', 'error');
                });
            }
        });
    }

    // Initialize language pairs functionality
    function initializeLanguagePairs() {
        // Add language pair button click handler
        document.getElementById('add_language_pair_portfolio').addEventListener('click', function() {
            const sourceSelect = document.getElementById('source_language_portfolio');
            const targetSelect = document.getElementById('target_language_portfolio');
            
            const sourceValue = sourceSelect.value;
            const targetValue = targetSelect.value;
            const sourceText = sourceSelect.options[sourceSelect.selectedIndex].text;
            const targetText = targetSelect.options[targetSelect.selectedIndex].text;
            
            // Validate selections
            if (!sourceValue) {
                Swal.showValidationMessage('Seleccione un idioma origen');
                return;
            }
            
            if (!targetValue) {
                Swal.showValidationMessage('Seleccione un idioma destino');
                return;
            }
            
            if (sourceValue === targetValue) {
                Swal.showValidationMessage('Los idiomas origen y destino no pueden ser iguales');
                return;
            }
            
            // Check if pair already exists
            const existingPairs = document.querySelectorAll('#language-pairs-container-portfolio input[name="language_pairs[]"]');
            let pairExists = false;
            existingPairs.forEach(input => {
                if (input.value === sourceValue + '|' + targetValue) {
                    pairExists = true;
                }
            });
            
            if (pairExists) {
                Swal.showValidationMessage('Esta combinación de idiomas ya existe');
                return;
            }
            
            // Add language pair to container
            addLanguagePairToContainer(sourceValue, targetValue, sourceText, targetText);
            
            // Reset selects
            sourceSelect.value = '';
            targetSelect.value = '';
        });
    }

    function addLanguagePairToContainer(sourceValue, targetValue, sourceText = null, targetText = null) {
        const container = document.getElementById('language-pairs-container-portfolio');
        
        // Get text if not provided
        if (!sourceText || !targetText) {
            const languageNames = {
                'es-ES': 'Español (España)',
                'es-MX': 'Español (México)',
                'es-AR': 'Español (Argentina)',
                'en-US': 'English (US)',
                'en-GB': 'English (UK)',
                'fr-FR': 'Français',
                'de-DE': 'Deutsch',
                'it-IT': 'Italiano',
                'pt-BR': 'Português (Brasil)',
                'pt-PT': 'Português (Portugal)',
                'zh-CN': '中文 (简体)',
                'ja-JP': '日本語',
                'ko-KR': '한국어'
            };
            
            sourceText = languageNames[sourceValue] || sourceValue;
            targetText = languageNames[targetValue] || targetValue;
        }
        
        // Get flag codes
        const sourceFlag = getLanguageFlag(sourceValue);
        const targetFlag = getLanguageFlag(targetValue);
        
        const pairHtml = `
            <div class="language-pair-item border rounded p-2 mb-2 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fi fi-${sourceFlag} me-2"></i>
                    <span class="fw-medium me-2">${sourceText}</span>
                    <i class="ti ti-arrow-right me-2 text-muted"></i>
                    <i class="fi fi-${targetFlag} me-2"></i>
                    <span class="fw-medium">${targetText}</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-language-pair">
                    <i class="ti ti-x"></i>
                </button>
                <input type="hidden" name="language_pairs[]" value="${sourceValue}|${targetValue}">
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', pairHtml);
        
        // Add remove functionality to the new button
        container.lastElementChild.querySelector('.remove-language-pair').addEventListener('click', function() {
            this.closest('.language-pair-item').remove();
        });
    }

    function getLanguageFlag(languageCode) {
        const flagMap = {
            'es-ES': 'es',
            'es-MX': 'mx',
            'es-AR': 'ar',
            'en-US': 'us',
            'en-GB': 'gb',
            'fr-FR': 'fr',
            'de-DE': 'de',
            'it-IT': 'it',
            'pt-BR': 'br',
            'pt-PT': 'pt',
            'zh-CN': 'cn',
            'ja-JP': 'jp',
            'ko-KR': 'kr'
        };
        
        return flagMap[languageCode] || languageCode.split('-')[1]?.toLowerCase() || 'us';
    }
</script>
@endpush 