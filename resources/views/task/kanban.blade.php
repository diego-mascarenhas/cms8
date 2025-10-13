@extends('layouts/layoutMaster')

@section('title', __('Tasks'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/jkanban/jkanban.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/app-kanban.css')}}" />
<style>
#project-selector {
	width: 200px !important;
}
.select2-container {
	width: 200px !important;
}
/* Compact tabs in sidebar */
.kanban-update-item-sidebar .nav-tabs .nav-link {
	padding: 0.5rem 0.75rem !important;
	font-size: 0.875rem;
}
.kanban-update-item-sidebar .nav-tabs .nav-item {
	margin-right: 0 !important;
}
.kanban-update-item-sidebar .nav-tabs {
	gap: 0.25rem;
}
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/jkanban/jkanban.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
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

        // Initialize Select2 on project selector
        const $projectSelector = $('#project-selector');
        if ($projectSelector.length && $.fn.select2) {
            $projectSelector.select2({
                width: 'resolve',
                minimumResultsForSearch: 5,
                dropdownAutoWidth: true
            });

            // Handle project selector change
            $projectSelector.on('change', function() {
                const projectId = $(this).val();
                if (projectId) {
                    window.location.href = '{{ route('task.index') }}?view=kanban&project_id=' + projectId;
                } else {
                    window.location.href = '{{ route('task.index') }}?view=kanban';
                }
            });
        }
    });
    </script>
    <script src="{{ mix('assets/js/app-kanban-custom.js') }}"></script>
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
        <div class="d-flex align-items-center flex-wrap gap-3 mt-3 mt-md-0">
            <!-- Project Selector -->
            <div class="w-auto">
                <select class="form-select select2" id="project-selector">
                    <option value="">Tablero general</option>
                    @foreach($projects as $proj)
                        <option value="{{ $proj->id }}" {{ $project && $project->id == $proj->id ? 'selected' : '' }}>
                            {{ $proj->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Go to Project Button (only shown when project is selected) -->
            @if($project)
                <a href="{{ route('project.show', $project->id) }}" class="btn btn-label-primary waves-effect">
                    <i class="ti ti-external-link me-1"></i>Ir al proyecto
                </a>
            @endif

            <!-- View List Button -->
            <a href="{{ route('task.index') }}" class="btn btn-label-secondary waves-effect" id="view-list-btn">
                <i class="ti ti-list me-1"></i>Vista de lista
            </a>
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
            <ul class="nav nav-tabs tabs-line flex-nowrap">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-update">
                        <i class="ti ti-edit me-md-1 me-0"></i>
                        <span class="align-middle d-none d-md-inline">{{ __('Editar') }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-communication">
                        <i class="ti ti-message-circle me-md-1 me-0"></i>
                        <span class="align-middle d-none d-md-inline">{{ __('Comunicación') }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity">
                        <i class="ti ti-trending-up me-md-1 me-0"></i>
                        <span class="align-middle d-none d-md-inline">{{ __('Actividad') }}</span>
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
                            <label class="form-label">{{ __('Tiempo Estimado') }}</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <select id="estimated-hours" class="form-select">
                                        <option value="0">0 horas</option>
                                        @for ($i = 1; $i <= 40; $i++)
                                        <option value="{{ $i }}">{{ $i }} {{ $i == 1 ? 'hora' : 'horas' }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select id="estimated-minutes" class="form-select">
                                        <option value="0">0 min</option>
                                        <option value="15">15 min</option>
                                        <option value="30">30 min</option>
                                        <option value="45">45 min</option>
                                    </select>
                                </div>
                            </div>
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
                <!-- Communication -->
                <div class="tab-pane fade" id="tab-communication" role="tabpanel">
                    <div class="mt-3">
                        <form id="communication-form">
                        <!-- Recipients Selection -->
                        <div class="mb-3">
                            <label class="form-label">{{ __('Destinatarios') }}</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="recipient-responsible" value="responsible" checked disabled>
                                    <label class="form-check-label" for="recipient-responsible">
                                        <i class="ti ti-user me-1"></i>
                                        <span id="responsible-name">{{ __('Responsable') }}</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="recipient-client" value="client">
                                    <label class="form-check-label" for="recipient-client">
                                        <i class="ti ti-building me-1"></i>
                                        <span id="client-name">{{ __('Cliente') }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                            <!-- Subject (readonly, predefined) -->
                            <input type="hidden" id="communication-subject" value="Consulta sobre tarea">

                            <!-- Message -->
                            <div class="mb-3">
                                <label class="form-label" for="communication-message">{{ __('Mensaje') }}</label>
                                <textarea id="communication-message" class="form-control" rows="6" placeholder="{{ __('Escribe tu mensaje o consulta...') }}"></textarea>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" id="send-communication">
                                    <i class="ti ti-send me-1"></i>{{ __('Enviar') }}
                                </button>
                                <button type="button" class="btn btn-label-secondary" id="clear-communication">
                                    <i class="ti ti-x me-1"></i>{{ __('Limpiar') }}
                                </button>
                            </div>
                        </form>

                        <!-- Communication History -->
                        <div class="mt-4">
                            <h6 class="mb-3">{{ __('Historial de comunicaciones') }}</h6>
                            <div id="communication-history">
                                <div class="text-center py-3 text-muted">
                                    <i class="ti ti-message-off mb-2" style="font-size: 2rem;"></i>
                                    <p class="mb-0">{{ __('No hay comunicaciones previas') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
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
