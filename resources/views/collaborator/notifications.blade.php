@extends('layouts/layoutMaster')

@section('title', 'Notificaciones')

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

    <!-- Notificaciones Content -->
    <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Tabs -->
        <div class="d-flex mb-3">
            <a href="{{ route('collaborator.show', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary me-3">
                <i class="ti ti-refresh me-1"></i>Resumen
            </a>
            <a href="{{ route('collaborator.rates', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary me-3">
                <i class="ti ti-tag me-1"></i>Tarifas
            </a>
            <a href="{{ route('collaborator.absences', ['id' => $collaborator->id]) }}" class="btn btn-outline-secondary me-3">
                <i class="ti ti-users me-1"></i>Ausencias
            </a>
            <a href="{{ route('collaborator.notifications', ['id' => $collaborator->id]) }}" class="btn btn-primary">
                <i class="ti ti-bell me-1"></i>Notificaciones
            </a>
        </div>
        
        <!-- Notificaciones -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-4">Notificaciones</h5>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 pb-3 border-bottom position-relative">
                    <div class="position-absolute end-0 top-0">
                        <span class="bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    </div>
                    <div class="avatar avatar-md bg-light-primary rounded-circle">
                        <img src="{{ asset('assets/img/avatars/1.png') }}" alt="avatar">
                    </div>
                    <div>
                        <h6 class="mb-1">Congratulation Flora! 🎉</h6>
                        <p class="mb-1">Won the monthly best seller gold badge</p>
                        <small class="text-muted">Today</small>
                    </div>
                </div>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 pb-3 border-bottom">
                    <div class="avatar avatar-md bg-light-secondary rounded-circle d-flex align-items-center justify-content-center">
                        <span>VU</span>
                    </div>
                    <div>
                        <h6 class="mb-1">New user registered.</h6>
                        <p class="mb-1">Accepted your connection</p>
                        <small class="text-muted">Yesterday</small>
                    </div>
                </div>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 pb-3 border-bottom">
                    <div class="avatar avatar-md bg-light-info rounded-circle">
                        <img src="{{ asset('assets/img/avatars/2.png') }}" alt="avatar">
                    </div>
                    <div>
                        <h6 class="mb-1">New message received 📩</h6>
                        <p class="mb-1">You have new message from Natalie</p>
                        <small class="text-muted">11 Aug</small>
                    </div>
                </div>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 pb-3 border-bottom position-relative">
                    <div class="position-absolute end-0 top-0">
                        <span class="bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    </div>
                    <div class="avatar avatar-md bg-light-danger rounded-circle d-flex align-items-center justify-content-center">
                        <span>P</span>
                    </div>
                    <div>
                        <h6 class="mb-1">Paypal</h6>
                        <p class="mb-1">ACME Inc. made new order $1,154</p>
                        <small class="text-muted">25 May</small>
                    </div>
                </div>
                
                <!-- Notification Item -->
                <div class="d-flex gap-3 align-items-start mb-4 position-relative">
                    <div class="position-absolute end-0 top-0">
                        <span class="bg-primary rounded-circle d-inline-block" style="width: 8px; height: 8px;"></span>
                    </div>
                    <div class="avatar avatar-md bg-light-success rounded-circle">
                        <img src="{{ asset('assets/img/avatars/3.png') }}" alt="avatar">
                    </div>
                    <div>
                        <h6 class="mb-1">Application has been approved 🚀</h6>
                        <p class="mb-1">Your ABC project application has been approved.</p>
                        <small class="text-muted">19 Mar</small>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button class="btn btn-primary">Enviar notificación personalizada</button>
                </div>
            </div>
        </div>
    </div>
    <!--/ Notificaciones Content -->
</div>
@endsection 