@extends('layouts/layoutMaster')

@section('title', 'Tarifas - ' . $collaborator->name)

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'
])
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
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <span class="fw-medium me-2">Email:</span>
                        <span>{{ $collaborator->email ?? 'N/A' }}</span>
                    </li>
                    <li class="mb-2">
                        <span class="fw-medium me-2">Teléfono:</span>
                        <span>{{ $collaborator->phone ?? 'N/A' }}</span>
                    </li>
                    <li class="mb-2">
                        <span class="fw-medium me-2">Estado:</span>
                        <span class="badge bg-label-success">Activo</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!--/ Collaborator Sidebar -->

    <!-- Collaborator Content -->
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

        <!-- Rates DataTable Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Tarifas del Colaborador</h5>
                <a href="{{ route('customer-fare.create', ['collaborator_id' => $collaborator->id]) }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i>Crear Tarifa
                </a>
            </div>
            <div class="card-body">
                {!! $dataTable->table(['class' => 'table table-striped table-bordered table-hover']) !!}
            </div>
        </div>
        <!--/ Rates DataTable Card -->
    </div>
    <!--/ Collaborator Content -->
</div>

{!! $dataTable->scripts() !!}
@endsection 