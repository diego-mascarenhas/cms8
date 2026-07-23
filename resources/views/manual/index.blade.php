@extends('layouts/layoutManual')

@section('title', __('Manual de usuario'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Manual de usuario de Humano') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('Este manual describe todo lo que puedes hacer en Humano. Está pensado para usuarios finales (Admin y Collaborator) y se centra en las funciones y el uso diario, no en detalles técnicos.') }}</p>

                <p>{{ __('Puedes usar el menú lateral para ir a cualquier sección. Los mockups muestran cómo se ven los formularios y los flujos. Para documentación técnica (API, variables de entorno, integraciones), entra en') }} <a href="{{ route('help.index') }}">{{ __('Ayuda y documentación') }}</a>.</p>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-primary h-100">
                            <div class="card-body">
                                <span class="badge bg-primary mb-2">Admin</span>
                                <h5>{{ __('Administrador') }}</h5>
                                <p class="mb-2">{{ __('Configura el equipo, usuarios, facturación y da acceso a Clients.') }}</p>
                                <a href="{{ route('mockups.admin-setup') }}" class="btn btn-sm btn-primary">{{ __('Diagrama arranque') }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-secondary h-100">
                            <div class="card-body">
                                <span class="badge bg-label-secondary mb-2">Collaborator</span>
                                <h5>{{ __('Colaborador') }}</h5>
                                <p class="mb-2">{{ __('Opera CRM, tareas, chat y tiempo. Sin billing ni usuarios.') }}</p>
                                <a href="{{ route('mockups.collaborator-day') }}" class="btn btn-sm btn-label-secondary">{{ __('Diagrama del día') }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-success h-100">
                            <div class="card-body">
                                <span class="badge bg-success mb-2">Client</span>
                                <h5>{{ __('Cliente final') }}</h5>
                                <p class="mb-2">{{ __('Usuario portal: ve sus proyectos, abre tickets y consulta presupuestos.') }}</p>
                                <a href="{{ route('mockups.client-journey') }}" class="btn btn-sm btn-success">{{ __('Diagrama del viaje') }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="mt-2">{{ __('Qué encontrarás aquí') }}</h5>
                <ul>
                    @foreach (\App\Http\Controllers\ManualController::guideSections() as $section)
                        <li>
                            <a href="{{ route($section['route']) }}"><strong>{{ $section['title'] }}</strong></a>
                            — {{ $section['description'] }}
                        </li>
                    @endforeach
                </ul>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="{{ route('manual.getting-started') }}" class="btn btn-primary">
                        <i class="ti ti-rocket me-1"></i>{{ __('Primeros pasos') }}
                    </a>
                    <a href="{{ route('mockups.overview') }}" class="btn btn-label-primary">
                        <i class="ti ti-git-fork me-1"></i>{{ __('Diagramas de flujo') }}
                    </a>
                    <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary">
                        <i class="ti ti-layout-board me-1"></i>{{ __('Catálogo de mockups') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
