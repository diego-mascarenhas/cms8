@extends('layouts/layoutHelpSimple')

@section('title', __('Tasks API'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <!-- Main Content - Full Width since sidebar is in layout -->
    <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ __('Tasks API Reference') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">{{ __('Complete API reference for managing tasks and time tracking programmatically.') }}</p>

                            <h5 class="mt-4">{{ __('Base URL') }}</h5>
                            <code class="d-block p-3 bg-light">{{ url('/') }}/api/tasks</code>

                            <h5 class="mt-4">{{ __('Authentication') }}</h5>
                            <p>{{ __('All requests require Bearer token authentication. Include the token in the Authorization header:') }}</p>
                            <pre><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                            <h5 class="mt-4">{{ __('Available Endpoints') }}</h5>

                            <!-- Quick Navigation -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="mb-3">{{ __('Quick Navigation') }}</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <ul class="list-unstyled mb-0">
                                                <li><a href="#list-statuses" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('List Task Statuses') }}</a></li>
                                                <li><a href="#list-tasks" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('List Tasks') }}</a></li>
                                                <li><a href="#create-task" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Create Task') }}</a></li>
                                                <li><a href="#get-task" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Get Task Details') }}</a></li>
                                                <li><a href="#update-status" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Update Task Status') }}</a></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <ul class="list-unstyled mb-0">
                                                <li><a href="#start-task" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Start Task Timer') }}</a></li>
                                                <li><a href="#stop-task" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Stop Task Timer') }}</a></li>
                                                <li><a href="#task-time" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Get Task Time') }}</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- List Task Statuses -->
                            <div class="card mt-4" id="list-statuses">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('List Task Statuses') }}
                                        <a href="#list-statuses" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/tasks/statuses</code>
                                    <p>{{ __('Retrieve a list of all available task statuses.') }}</p>

                                    <h6>{{ __('Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "TO_DO",
      "translated_name": "Por hacer",
      "color": "#6c757d"
    },
    {
      "id": 2,
      "name": "IN_PROGRESS",
      "translated_name": "En progreso",
      "color": "#0d6efd"
    },
    {
      "id": 3,
      "name": "REVIEW",
      "translated_name": "Revisión",
      "color": "#ffc107"
    },
    {
      "id": 4,
      "name": "DONE",
      "translated_name": "Completado",
      "color": "#198754"
    }
  ]
}</code></pre>
                                </div>
                            </div>

                            <!-- List Tasks -->
                            <div class="card mt-4" id="list-tasks">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('List Tasks') }}
                                        <a href="#list-tasks" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/tasks</code>
                                    <p>{{ __('Retrieve a list of tasks assigned to the authenticated user.') }}</p>

                                    <h6>{{ __('Query Parameters') }}</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Parameter') }}</th>
                                                <th>{{ __('Type') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>status_id</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Filter by status ID') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>pending_only</code></td>
                                                <td>boolean</td>
                                                <td>{{ __('Show only pending tasks (not DONE or CANCELLED)') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Actualizar sitio web",
      "description": "Actualizar el contenido del sitio",
      "start_date": "2026-02-01",
      "due_date": "2026-02-08",
      "estimated_hours": 5.5,
      "status": {
        "id": 2,
        "name": "IN_PROGRESS",
        "translated_name": "En progreso"
      },
      "category": {
        "id": 1,
        "name": "Desarrollo"
      },
      "project": {
        "id": 3,
        "name": "Sitio Web Corporativo"
      },
      "responsible": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "juan@example.com"
      },
      "active_time": {
        "id": 5,
        "started_at": "2026-02-02T14:30:00Z",
        "elapsed_seconds": 1250
      }
    }
  ]
}</code></pre>
                                </div>
                            </div>

                            <!-- Create Task -->
                            <div class="card mt-4" id="create-task">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-success me-2">POST</span>
                                        {{ __('Create Task') }}
                                        <a href="#create-task" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/tasks</code>
                                    <p>{{ __('Create a new task. Optionally start the timer automatically.') }}</p>

                                    <h6>{{ __('Request Body') }}</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Field') }}</th>
                                                <th>{{ __('Type') }}</th>
                                                <th>{{ __('Required') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>title</code></td>
                                                <td>string</td>
                                                <td><span class="badge bg-danger">Yes</span></td>
                                                <td>{{ __('Task title (max 255 chars)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>description</code></td>
                                                <td>string</td>
                                                <td><span class="badge bg-secondary">No</span></td>
                                                <td>{{ __('Task description') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>start_timer</code></td>
                                                <td>boolean</td>
                                                <td><span class="badge bg-secondary">No</span></td>
                                                <td>{{ __('Start timer immediately (default: false)') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Request Example') }}</h6>
                                    <pre><code class="language-json">{
  "title": "Implementar nueva funcionalidad",
  "description": "Agregar sistema de notificaciones push",
  "start_timer": true
}</code></pre>

                                    <h6>{{ __('Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "success": true,
  "message": "Tarea creada correctamente.",
  "data": {
    "task_id": 15,
    "title": "Implementar nueva funcionalidad",
    "status": {
      "id": 2,
      "name": "IN_PROGRESS",
      "translated_name": "En progreso"
    },
    "time_id": 42,
    "timer_started": true
  }
}</code></pre>

                                    <div class="alert alert-info mt-3">
                                        <i class="ti ti-info-circle me-2"></i>
                                        <strong>{{ __('Note:') }}</strong> {{ __('When start_timer is true, the task status automatically changes to IN_PROGRESS and any other active timer is stopped.') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Get Task Details -->
                            <div class="card mt-4" id="get-task">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('Get Task Details') }}
                                        <a href="#get-task" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/tasks/{id}</code>
                                    <p>{{ __('Get detailed information about a specific task.') }}</p>

                                    <h6>{{ __('URL Parameters') }}</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Parameter') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>id</code></td>
                                                <td>{{ __('Task ID') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Update Task Status -->
                            <div class="card mt-4" id="update-status">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-warning me-2">PUT</span>
                                        {{ __('Update Task Status') }}
                                        <a href="#update-status" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/tasks/{id}/status</code>
                                    <p>{{ __('Update the status of a task.') }}</p>

                                    <h6>{{ __('Request Body') }}</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Field') }}</th>
                                                <th>{{ __('Type') }}</th>
                                                <th>{{ __('Required') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>status_id</code></td>
                                                <td>integer</td>
                                                <td><span class="badge bg-danger">Yes</span></td>
                                                <td>{{ __('New status ID (must exist in task_statuses)') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6>{{ __('Request Example') }}</h6>
                                    <pre><code class="language-json">{
  "status_id": 4
}</code></pre>

                                    <h6>{{ __('Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "success": true,
  "message": "Estado actualizado correctamente.",
  "data": {
    "task_id": 15,
    "status": {
      "id": 4,
      "name": "DONE",
      "translated_name": "Completado"
    }
  }
}</code></pre>
                                </div>
                            </div>

                            <!-- Start Task Timer -->
                            <div class="card mt-4" id="start-task">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-success me-2">POST</span>
                                        {{ __('Start Task Timer') }}
                                        <a href="#start-task" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/tasks/{id}/start</code>
                                    <p>{{ __('Start time tracking for a task. Automatically stops any other active timer and changes task status to IN_PROGRESS.') }}</p>

                                    <h6>{{ __('Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "success": true,
  "message": "Tarea iniciada correctamente.",
  "data": {
    "time_id": 42,
    "task_id": 15,
    "started_at": "2026-02-02T14:30:00Z"
  }
}</code></pre>

                                    <div class="alert alert-warning mt-3">
                                        <i class="ti ti-alert-triangle me-2"></i>
                                        <strong>{{ __('Note:') }}</strong> {{ __('Starting a task automatically stops any other running timer for the user.') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Stop Task Timer -->
                            <div class="card mt-4" id="stop-task">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-danger me-2">POST</span>
                                        {{ __('Stop Task Timer') }}
                                        <a href="#stop-task" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/tasks/{id}/stop</code>
                                    <p>{{ __('Stop the active timer for a task.') }}</p>

                                    <h6>{{ __('Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "success": true,
  "message": "Tarea detenida correctamente.",
  "data": {
    "time_id": 42,
    "task_id": 15,
    "started_at": "2026-02-02T14:30:00Z",
    "ended_at": "2026-02-02T16:45:00Z",
    "duration_seconds": 8100,
    "duration_formatted": "02:15:00"
  }
}</code></pre>
                                </div>
                            </div>

                            <!-- Get Task Time -->
                            <div class="card mt-4" id="task-time">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('Get Task Time') }}
                                        <a href="#task-time" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/tasks/{id}/time</code>
                                    <p>{{ __('Get complete time tracking information for a task, including all time entries and active timer.') }}</p>

                                    <h6>{{ __('Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "success": true,
  "data": {
    "task": {
      "id": 15,
      "title": "Implementar nueva funcionalidad",
      "description": "Agregar sistema de notificaciones push",
      "status": {
        "id": 2,
        "name": "IN_PROGRESS",
        "translated_name": "En progreso"
      },
      "project": {
        "id": 3,
        "name": "Sitio Web Corporativo"
      }
    },
    "total_seconds": 12300,
    "total_formatted": "03:25:00",
    "total_hours": 3.42,
    "active_time": {
      "id": 45,
      "started_at": "2026-02-02T16:00:00Z"
    },
    "entries": [
      {
        "id": 42,
        "user": {
          "id": 1,
          "name": "Juan Pérez"
        },
        "started_at": "2026-02-02T14:30:00Z",
        "ended_at": "2026-02-02T16:45:00Z",
        "duration_seconds": 8100,
        "duration_formatted": "02:15:00"
      }
    ]
  }
}</code></pre>
                                </div>
                            </div>

                            <!-- Error Responses -->
                            <div class="card mt-4 bg-light">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">{{ __('Error Responses') }}</h6>
                                </div>
                                <div class="card-body">
                                    <h6>{{ __('Common Error Codes') }}</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Code') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>400</code></td>
                                                <td>{{ __('Bad Request - Invalid parameters or task already has active timer') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>401</code></td>
                                                <td>{{ __('Unauthorized - Invalid or missing authentication token') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>403</code></td>
                                                <td>{{ __('Forbidden - You don\'t have permission to access this task') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>404</code></td>
                                                <td>{{ __('Not Found - Task does not exist') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>422</code></td>
                                                <td>{{ __('Validation Error - Invalid input data') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6 class="mt-3">{{ __('Error Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "success": false,
  "message": "No tienes permiso para iniciar esta tarea."
}</code></pre>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
    </div>
</div>
@endsection
