@extends('layouts/layoutMaster')

@section('title', $collaborator->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('page-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-user-view.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
    <div class="row">
        <!-- Collaborator Sidebar -->
        <div class="col-xl-4 col-lg-5 col-md-5">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center flex-column mb-3">
                        <img src="{{ asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle mb-3" width="100" height="100">
                        <h4 class="mb-1">{{ $collaborator->name }}</h4>
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
                                <span>{{ $collaborator->email }}</span>
                            </li>
                            <li class="mb-2">
                                <span class="fw-medium me-1">Estado:</span>
                                <span class="badge bg-label-success">Activo</span>
                            </li>
                            <li class="mb-2">
                                <span class="fw-medium me-1">Contacto:</span>
                                <span>{{ $collaborator->phone }}</span>
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
                            <a href="{{ route('collaborator.edit', ['id' => $collaborator->id]) }}" class="btn btn-primary flex-grow-1">
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

        <!-- Collaborator Content -->
        <div class="col-xl-8 col-lg-7 col-md-7">
            <!-- Tabs -->
            <div class="d-flex mb-3">
                <a href="javascript:void(0)" class="btn btn-primary me-3">
                    <i class="ti ti-refresh me-1"></i>Resumen
                </a>
                <a href="{{ route('collaborator.rates', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary me-3">
                    <i class="ti ti-tag me-1"></i>Tarifas
                </a>
                <a href="javascript:void(0)" class="btn btn-outline-secondary me-3">
                    <i class="ti ti-users me-1"></i>Ausencias
                </a>
                <a href="javascript:void(0)" class="btn btn-outline-secondary">
                    <i class="ti ti-bell me-1"></i>Notificaciones
                </a>
            </div>

            <!-- Proyectos con bbo -->
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

            <!-- Experiencia -->
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

            <!-- Actividad -->
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

            <!-- Servicios -->
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

            <!-- Software de trabajo -->
            <div class="card mb-4">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Software de trabajo</h5>
                    <a href="javascript:void(0)" class="text-secondary"><i class="ti ti-pencil"></i></a>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge bg-label-primary rounded-pill me-1">Subtitle Edit</span>
                        <span class="badge bg-label-primary rounded-pill me-1">Davinci</span>
                        <span class="badge bg-label-primary rounded-pill">Memsource</span>
                    </div>
                    <div class="mt-3">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">Aegisub</li>
                            <li class="mb-2">EZTitles</li>
                            <li class="mb-2">SDL Trados</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Temáticas -->
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
@endsection 