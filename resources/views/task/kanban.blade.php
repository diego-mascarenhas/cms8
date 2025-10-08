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
                    {{ $project->name }} - Tareas Kanban
                @else
                    Tareas Kanban
                @endif
            </h4>
            <p class="text-muted">
                @if($project)
                    Tablero: {{ $board->name }}
                @else
                    Gestiona las tareas de forma visual
                @endif
            </p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
            @can('task.edit')
                <a href="{{ route('task.create', array_merge(['board_id' => $board->id, 'view' => 'kanban'], $project ? ['project_id' => $project->id] : [])) }}" class="btn btn-primary waves-effect">
                    <i class="ti ti-plus me-1"></i>Añadir tarea
                </a>
            @endcan
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
            <button type="submit" class="btn btn-primary me-2 waves-effect waves-light">Añadir</button>
            <button type="button" class="btn btn-label-secondary kanban-add-board-cancel-btn waves-effect waves-light">Cancelar</button>
        </div>
    </form>

    <!-- Edit Task & Activities (Exact Vuexy markup) -->
    <div class="offcanvas offcanvas-end kanban-update-item-sidebar">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">Edit Task</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav nav-tabs tabs-line">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-update">
                        <i class="ti ti-edit me-2"></i>
                        <span class="align-middle">Edit</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity">
                        <i class="ti ti-trending-up me-2"></i>
                        <span class="align-middle">Activity</span>
                    </button>
                </li>
            </ul>
            <div class="tab-content px-0 pb-0">
                <!-- Update item/tasks -->
                <div class="tab-pane fade show active" id="tab-update" role="tabpanel">
                    <form>
                        <div class="mb-3">
                            <label class="form-label" for="title">{{ __('Título') }}</label>
                            <input type="text" id="title" class="form-control" placeholder="{{ __('Ingresa el título') }}" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="due-date">{{ __('Fecha de Entrega') }}</label>
                            <div class="input-group">
                                <input type="text" id="due-date" class="form-control" placeholder="{{ __('Selecciona una fecha') }}" />
                                <button type="button" class="btn btn-icon btn-label-primary" id="due-date-settings" title="Settings">
                                    <i class="ti ti-settings"></i>
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
                            <label class="form-label">{{ __('Asignado') }}</label>
                            <div class="assigned d-flex flex-wrap"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="attachments">{{ __('Adjuntos') }}</label>
                            <input type="file" class="form-control" id="attachments" />
                        </div>
                        <div class="mb-4">
                            <label class="form-label">{{ __('Comentario') }}</label>
                            <div class="comment-editor border-bottom-0"></div>
                            <div class="d-flex justify-content-end">
                                <div class="comment-toolbar">
                                    <span class="ql-formats me-0">
                                        <button class="ql-bold"></button>
                                        <button class="ql-italic"></button>
                                        <button class="ql-underline"></button>
                                        <button class="ql-link"></button>
                                        <button class="ql-image"></button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap">
                            <button type="button" id="offcanvas-save" class="btn btn-primary me-3">{{ __('Guardar') }}</button>
                            <button type="button" class="btn btn-label-danger" data-bs-dismiss="offcanvas">{{ __('Eliminar') }}</button>
                        </div>
                    </form>
                </div>
                <!-- Activities -->
                <div class="tab-pane fade" id="tab-activity" role="tabpanel">
                    <div class="media mb-4 d-flex align-items-start">
                        <div class="avatar me-2 flex-shrink-0 mt-1">
                            <span class="avatar-initial bg-label-success rounded-circle">HJ</span>
                        </div>
                        <div class="media-body">
                            <p class="mb-0"><span class="fw-medium">Jordan</span> Left the board.</p>
                            <small class="text-muted">Today 11:00 AM</small>
                        </div>
                    </div>
                    <!-- Additional sample activities intentionally kept from Vuexy template -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
