@extends('layouts/layoutMaster')

@section('title', 'Contacto CMS7')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-user-view.css') }}" />
@endsection

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">Contacto CMS7: {{ $contact->nombre }} {{ $contact->apellido }}</h4>
            <p class="text-muted">
                ID: {{ $contact->id }} | Creado el {{ \Carbon\Carbon::parse($contact->fecha_alta)->isoFormat('D [de] MMMM [de] YYYY, HH:mm [hs]') }}
            </p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            <a href="javascript:history.back()" class="btn btn-secondary waves-effect waves-light">
                <i class="ti ti-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    <div class="row">
        <!-- User Sidebar -->
        <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
            <!-- User Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="user-avatar-section">
                        <div class="d-flex align-items-center flex-column">
                            <img class="img-fluid rounded mb-3 pt-1 mt-4"
                                src="https://ui-avatars.com/api/?format=svg&name={{ $contact->nombre }}+{{ $contact->apellido }}" height="100"
                                width="100" alt="User avatar" />
                            <div class="user-info text-center">
                                <h4 class="mb-2">{{ $contact->nombre }} {{ $contact->apellido }}</h4>
                                <span class="badge bg-label-primary">ID: {{ $contact->id }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 info-container">
                        <ul class="list-unstyled">
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Cargo:</span>
                                <span>{{ $contact->cargo ?? 'No especificado' }}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Email:</span>
                                <span>{{ $contact->email ?? 'No especificado' }}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Teléfono:</span>
                                <span>{{ $contact->telefono ?? 'No especificado' }}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Celular:</span>
                                <span>{{ $contact->celular ?? 'No especificado' }}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Interno:</span>
                                <span>{{ $contact->interno ?? 'No especificado' }}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Sexo:</span>
                                <span>
                                    @if($contact->sexo == 'M')
                                        Masculino
                                    @elseif($contact->sexo == 'F')
                                        Femenino
                                    @else
                                        No especificado
                                    @endif
                                </span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Fecha de nacimiento:</span>
                                <span>
                                    @if(!empty($contact->fecha_nacimiento))
                                        {{ \Carbon\Carbon::parse($contact->fecha_nacimiento)->format('d/m/Y') }}
                                    @else
                                        No especificada
                                    @endif
                                </span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Idioma:</span>
                                <span>{{ $contact->idioma ?? 'No especificado' }}</span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Última visita:</span>
                                <span>
                                    @if(!empty($contact->ultima_visita))
                                        {{ \Carbon\Carbon::parse($contact->ultima_visita)->format('d/m/Y H:i') }}
                                    @else
                                        No disponible
                                    @endif
                                </span>
                            </li>
                            <li class="mb-2 pt-1">
                                <span class="fw-medium me-1">Estado:</span>
                                <span class="badge bg-label-{{ $contact->estado == 1 ? 'success' : 'warning' }}">
                                    {{ $contact->estado == 1 ? 'Activo' : 'Inactivo' }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- /User Card -->
        </div>
        <!--/ User Sidebar -->

        <!-- User Content -->
        <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
            <!-- User Pills -->
            <ul class="nav nav-pills flex-column flex-md-row mb-4">
                <li class="nav-item">
                    <a class="nav-link active" href="javascript:void(0);">
                        <i class="ti ti-building ti-xs me-1"></i>Empresa
                    </a>
                </li>
            </ul>
            <!--/ User Pills -->

            <!-- Company Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información de la Empresa</h5>
                </div>
                <div class="card-body">
                    @if($empresa)
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-semibold">Nombre</h6>
                                <p>
                                    <a href="{{ route('cms7.enterprise', $empresa->id) }}">
                                        {{ $empresa->empresa }}
                                    </a>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-semibold">Teléfono</h6>
                                <p>{{ $empresa->telefono ?? 'No especificado' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-semibold">Email</h6>
                                <p>{{ $empresa->email ?? 'No especificado' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-semibold">Dirección</h6>
                                <p>{{ $empresa->domicilio ?? 'No especificada' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-semibold">Localidad</h6>
                                <p>{{ $empresa->localidad ?? 'No especificada' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-semibold">CP</h6>
                                <p>{{ $empresa->codigo_postal ?? 'No especificado' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-semibold">Provincia</h6>
                                <p>{{ $empresa->provincia ?? 'No especificada' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-semibold">Estado</h6>
                                <p>
                                    <span class="badge bg-label-{{ $empresa->estado == 1 ? 'success' : 'warning' }}">
                                        {{ $empresa->estado == 1 ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-12 mt-3">
                                <div class="d-flex">
                                    <a href="{{ route('cms7.services', ['empresaId' => $empresa->id]) }}" class="btn btn-primary me-2">
                                        <i class="ti ti-server me-1"></i>Ver servicios
                                    </a>
                                    <a href="{{ route('cms7.invoices', ['empresaId' => $empresa->id]) }}" class="btn btn-info">
                                        <i class="ti ti-receipt me-1"></i>Ver facturas
                                    </a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-info mb-0">
                            Este contacto no tiene una empresa asociada.
                        </div>
                    @endif
                </div>
            </div>
            <!-- /Company Info -->
        </div>
        <!--/ User Content -->
    </div>
@endsection 