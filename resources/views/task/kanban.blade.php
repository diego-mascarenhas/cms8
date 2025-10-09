@extends('layouts/layoutMaster')

@section('title', __('Tasks'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/jkanban/jkanban.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/app-kanban.css')}}" />
<style>
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jkanban/jkanban.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
@endsection

@section('page-script')
<script>
    // Pass data from Laravel to JavaScript
    window.kanbanData = {
        statuses: @json($statuses),
        tasksByStatus: @json($tasksByStatus),
        boardId: {{ $board->id }},
        projectId: {{ $project->id ?? 'null' }},
        storeUrl: '{{ route('task.store') }}',
        updateStatusUrl: '{{ route('task.update-status') }}',
        updateOrderUrl: '{{ route('task.update-order') }}',
        csrfToken: '{{ csrf_token() }}',
        currentUserId: {{ auth()->id() }},
        users: @json($users ?? []),
        categories: @json($categories ?? [])
    };
</script>
<script>
    // Ensure offcanvas lives under <body> to avoid transform/overflow clipping
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.querySelector('.kanban-update-item-sidebar');
        if (el && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
    });
    </script>
<script src="{{ asset('assets/js/app-kanban-custom.js') }}"></script>
@endsection

@section('content')
<div class="app-kanban">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">
                @if($project)
                    Tareas/ {{ str_replace('Project: ', '', $board->name) }}
                @else
                    Tareas
                @endif
            </h4>
            <p class="text-muted">
                @if($project)
                    {{ $project->enterprise ? $project->enterprise->name : $project->name }}
                @else
                    Gestiona las tareas de forma visual
                @endif
            </p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
            @if($project)
                <a href="{{ route('project.show', $project->id) }}" class="btn btn-label-secondary waves-effect">
                    <i class="ti ti-arrow-left me-1"></i>Volver al proyecto
                </a>
            @else
                <a href="{{ route('task.index') }}" class="btn btn-label-secondary waves-effect" id="view-list-btn">
                    <i class="ti ti-list me-1"></i>Vista de lista
                </a>
            @endif
        </div>
    </div>

    <!-- Kanban Container & Wrapper -->
    <div class="kanban-container">
        <div class="kanban-wrapper"></div>
    </div>

    <!-- Add New Board (template original) -->
    <form class="kanban-add-new-board d-none">
        <div class="mb-3">
            <input type="text" class="form-control kanban-add-board-input d-none" placeholder="Título del tablero">
        </div>
        <div class="mb-3">
            <button type="submit" class="kanban-title-button btn me-2">+ Agregar</button>
            <button type="button" class="btn btn-label-secondary kanban-add-board-cancel-btn waves-effect waves-light">Cancelar</button>
        </div>
    </form>

    <!-- Edit Task & Activities (Exact Vuexy markup) -->
    <div class="offcanvas offcanvas-end kanban-update-item-sidebar">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">{{ __('Editar Tarea') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav nav-tabs tabs-line">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-update">
                        <i class="ti ti-edit me-2"></i>
                        <span class="align-middle">{{ __('Editar') }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity">
                        <i class="ti ti-trending-up me-2"></i>
                        <span class="align-middle">{{ __('Actividad') }}</span>
                    </button>
                </li>
            </ul>
            <div class="tab-content px-0 pb-0">
                <!-- Update item/tasks -->
                <div class="tab-pane fade show active" id="tab-update" role="tabpanel">
                    <form>
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-success" id="task-start-timer">
                                <i class="ti ti-player-play me-1"></i>{{ __('Start Timer') }}
                            </button>
                            <button type="button" class="btn btn-danger d-none" id="task-stop-timer">
                                <i class="ti ti-player-stop me-1"></i>{{ __('Stop Timer') }}
                            </button>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="title">{{ __('Título') }}</label>
                            <input type="text" id="title" class="form-control" placeholder="{{ __('Ingresa el título') }}" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="due-date">{{ __('Fecha de Entrega') }}</label>
                            <div class="input-group">
                                <input type="text" id="due-date" class="form-control" placeholder="{{ __('Selecciona una fecha') }}" />
                                <button type="button" class="btn btn-icon btn-label-primary" id="due-date-settings" title="Calendario">
                                    <i class="ti ti-calendar"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="label">{{ __('Categoría') }}</label>
                            <select class="select2 select2-label form-select" id="label">
                                <option value="">{{ __('Selecciona una categoría') }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="responsible">{{ __('Responsable') }}</label>
                            <select class="select2 form-select" id="responsible">
                                <option value="">{{ __('Selecciona un responsable') }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="estimated-hours">{{ __('Tiempo Estimado (horas)') }}</label>
                            <input type="number" id="estimated-hours" class="form-control" placeholder="{{ __('Ej: 8.5') }}" step="0.5" min="0" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="attachments">{{ __('Adjunto') }}</label>
                            <input type="file" class="form-control" id="attachments" accept="image/*" />
                            <div id="attachment-preview" class="mt-3" style="display: none;">
                                <img id="preview-image" src="" alt="Vista previa" class="img-fluid rounded" style="max-height: 200px; object-fit: cover;" />
                                <button type="button" class="btn btn-sm btn-danger mt-2" id="remove-attachment">
                                    <i class="ti ti-x me-1"></i>{{ __('Eliminar imagen') }}
                                </button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="description">{{ __('Descripción') }}</label>
                            <textarea id="description" class="form-control" rows="4" placeholder="{{ __('Ingresa una descripción') }}"></textarea>
                        </div>
                        <div class="d-flex flex-wrap">
                            <button type="button" id="offcanvas-save" class="btn btn-primary me-3">{{ __('Guardar') }}</button>
                            <button type="button" id="offcanvas-delete" class="btn btn-label-danger">{{ __('Eliminar') }}</button>
                        </div>
                    </form>
                </div>
                <!-- Activities -->
                <div class="tab-pane fade" id="tab-activity" role="tabpanel">
                    <div id="activity-log-container">
                        <div class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">{{ __('Cargando...') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
