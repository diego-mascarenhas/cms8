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
        currentUserId: {{ auth()->id() }}
    };
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

    <!-- Edit Task Sidebar (template original) -->
    <div class="offcanvas offcanvas-end kanban-update-item-sidebar" tabindex="-1">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">{{ __('Edit Task') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="mb-3">
                <label class="form-label" for="title">Título</label>
                <input type="text" id="title" class="form-control" placeholder="Título de la tarea" />
            </div>
            <div class="mb-3">
                <label class="form-label" for="due-date">Fecha límite</label>
                <input type="text" id="due-date" class="form-control" placeholder="Selecciona una fecha" />
            </div>
            <div class="mb-3">
                <label class="form-label">Etiquetas</label>
                <select class="select2 form-select" multiple>
                    <option data-color="bg-label-primary">Nuevo</option>
                    <option data-color="bg-label-success">En progreso</option>
                    <option data-color="bg-label-danger">Bloqueado</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Asignados</label>
                <div class="assigned d-flex align-items-center"></div>
            </div>
            <div class="mb-3">
                <label class="form-label">Comentarios</label>
                <div class="comment-toolbar border-bottom pb-1"></div>
                <div class="comment-editor"></div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" id="offcanvas-save">{{ __('Save') }}</button>
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="offcanvas">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection
