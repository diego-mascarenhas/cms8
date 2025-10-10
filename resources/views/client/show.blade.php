@extends('layouts/layoutMaster')

@section('title', __('app.clients'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Clients') }}/</span> {{ $client->name }}</h4>
            <p class="text-muted">{{ __('Detailed client information') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3">
            @can('client.edit')
                <a href="{{ route('client.edit', $client->id) }}" class="btn btn-primary waves-effect waves-light">
                    <i class="ti ti-edit me-1"></i>{{ __('Edit') }} {{ __('Client') }}
                </a>
            @endcan
            @can('project.create')
                <a href="{{ route('project.create') }}?enterprise_id={{ $client->id }}" class="btn btn-success waves-effect waves-light">
                    <i class="ti ti-folder-plus me-1"></i>{{ __('Create') }} {{ __('Project') }}
                </a>
            @endcan
        </div>
    </div>

        <!-- Projects & Services Section -->
        <div class="col-12">
            <!-- Style Guide Section -->
            @if($client->data && ($client->data->style_guide ?? null))
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Guía de estilo</h5>
                </div>
                <div class="card-body">
                    <p>{{ $client->data->style_guide }}</p>
                </div>
            </div>
            @endif

            <!-- Active Projects -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Proyectos activos</h5>
                    @can('project.create')
                    <a href="{{ route('project.create') }}?enterprise_id={{ $client->id }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-plus me-1"></i>Nuevo proyecto
                    </a>
                    @endcan
                </div>
                <div class="card-body">
                    @if($activeProjects->count() > 0)
                        <div class="row">
                            @foreach($activeProjects as $project)
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 border-success">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-1">
                                                <a href="{{ route('project.show', $project->id) }}" class="text-decoration-none">
                                                    {{ $project->name }}
                                                </a>
                                            </h6>
                                            @if($project->status)
                                                <span class="badge bg-success rounded-pill">
                                                    {{ $project->status->name }}
                                                </span>
                                            @endif
                                        </div>

                                        @if($project->description)
                                        <p class="card-text small text-muted mb-2">
                                            {{ Str::limit($project->description, 80) }}
                                        </p>
                                        @endif

                                        <div class="small text-muted">
                                            @if($project->responsible)
                                                <div class="mb-1">
                                                    <i class="ti ti-user me-1"></i>
                                                    {{ $project->responsible->name }}
                                                </div>
                                            @endif
                                            @if($project->category)
                                                <div class="mb-1">
                                                    <i class="ti ti-category me-1"></i>
                                                    {{ $project->category->name }}
                                                </div>
                                            @endif
                                            @if($project->price)
                                                <div class="mb-1">
                                                    <i class="ti ti-currency-dollar me-1"></i>
                                                    ${{ number_format($project->price, 2) }}
                                                </div>
                                            @endif
                                            @if($project->date_start)
                                                <div class="mb-1">
                                                    <i class="ti ti-calendar me-1"></i>
                                                    {{ Carbon\Carbon::parse($project->date_start)->format('d/m/Y') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="ti ti-folder-off display-4 text-muted"></i>
                            </div>
                            <h6 class="mb-1">Sin proyectos activos</h6>
                            <p class="text-muted mb-3">Este cliente no tiene proyectos activos.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Past Projects -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Proyectos pasados</h5>
                </div>
                <div class="card-body">
                    @if($pastProjects->count() > 0)
                        <div class="row">
                            @foreach($pastProjects->take(6) as $project)
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 border-secondary">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-1">
                                                <a href="{{ route('project.show', $project->id) }}" class="text-decoration-none text-muted">
                                                    {{ $project->name }}
                                                </a>
                                            </h6>
                                            @if($project->status)
                                                <span class="badge bg-secondary rounded-pill">
                                                    {{ $project->status->name }}
                                                </span>
                                            @endif
                                        </div>

                                        @if($project->description)
                                        <p class="card-text small text-muted mb-2">
                                            {{ Str::limit($project->description, 80) }}
                                        </p>
                                        @endif

                                        <div class="small text-muted">
                                            @if($project->responsible)
                                                <div class="mb-1">
                                                    <i class="ti ti-user me-1"></i>
                                                    {{ $project->responsible->name }}
                                                </div>
                                            @endif
                                            @if($project->category)
                                                <div class="mb-1">
                                                    <i class="ti ti-category me-1"></i>
                                                    {{ $project->category->name }}
                                                </div>
                                            @endif
                                            @if($project->date_end)
                                                <div class="mb-1">
                                                    <i class="ti ti-calendar-check me-1"></i>
                                                    {{ Carbon\Carbon::parse($project->date_end)->format('d/m/Y') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @if($pastProjects->count() > 6)
                            <div class="text-center">
                                <small class="text-muted">Mostrando 6 de {{ $pastProjects->count() }} proyectos pasados</small>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="ti ti-folder-check display-4 text-muted"></i>
                            </div>
                            <h6 class="mb-1">Sin proyectos pasados</h6>
                            <p class="text-muted mb-3">Este cliente no tiene proyectos completados aún.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Collaborators Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Colaboradores</h5>
                </div>
                <div class="card-body">
                    @if($collaborators->count() > 0)
                        <div class="row">
                            @foreach($collaborators as $collaborator)
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded-circle bg-label-primary">
                                                {{ strtoupper(substr($collaborator->name, 0, 2)) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-0">
                                            <a href="{{ route('contact.show', $collaborator->id) }}" class="text-decoration-none">
                                                {{ $collaborator->name }}
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            {{ $collaborator->projects->count() }} proyectos
                                        </small>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="ti ti-users display-4 text-muted"></i>
                            </div>
                            <h6 class="mb-1">Sin colaboradores</h6>
                            <p class="text-muted mb-0">Ningún colaborador ha trabajado en proyectos para este cliente aún.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Language Combinations Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Combinaciones de idiomas</h5>
                </div>
                <div class="card-body">
                    @if($languageCombinations->count() > 0)
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($languageCombinations as $combination)
                            <span class="badge rounded-pill bg-label-info">
                                {{ $combination }}
                            </span>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="ti ti-language display-4 text-muted"></i>
                            </div>
                            <h6 class="mb-1">Sin combinaciones de idiomas</h6>
                            <p class="text-muted mb-0">No hay combinaciones de idiomas disponibles para este cliente aún.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Services Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Servicios utilizados</h5>
                </div>
                <div class="card-body">
                    @if($services->count() > 0)
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($services as $service)
                            <span class="badge rounded-pill bg-label-primary">
                                {{ $service->name }}
                            </span>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="ti ti-tools display-4 text-muted"></i>
                            </div>
                            <h6 class="mb-1">Sin servicios</h6>
                            <p class="text-muted mb-0">No se han utilizado servicios para este cliente aún.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
@endsection
