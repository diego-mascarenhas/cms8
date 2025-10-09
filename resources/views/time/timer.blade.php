@extends('layouts/layoutMaster')

@section('title', __('Time Tracker'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    let timerInterval;
    let startTime;
    let isRunning = false;

    // Initialize Select2
    $('#project_id, #task_id').select2({
        placeholder: 'Selecciona una opción',
        allowClear: true
    });

    // Load tasks when project changes
    $('#project_id').on('change', function() {
        const projectId = $(this).val();
        loadTasks(projectId);
    });

    // Load initial tasks
    loadTasks();

    function loadTasks(projectId = null) {
        const url = '{{ route("time.tasks") }}';
        const params = projectId ? { project_id: projectId } : {};

        $.get(url, params, function(tasks) {
            const taskSelect = $('#task_id');
            taskSelect.empty();
            taskSelect.append('<option value="">Selecciona una tarea</option>');

            tasks.forEach(function(task) {
                taskSelect.append(`<option value="${task.id}">${task.title}</option>`);
            });
        });
    }

    // Start timer
    $('#start-timer').on('click', function() {
        const taskId = $('#task_id').val();
        const description = $('#description').val();

        // Validate required fields
        if (!taskId) {
            alert('{{ __("Por favor selecciona una tarea") }}');
            return;
        }

        $.post('{{ route("time.start") }}', {
            task_id: taskId,
            description: description,
            _token: '{{ csrf_token() }}'
        }, function(response) {
            if (response.success) {
                startTimer();
                $('#start-timer').prop('disabled', true);
                $('#stop-timer').prop('disabled', false);
                $('#project_id, #task_id, #description').prop('disabled', true);
            } else {
                alert(response.message);
            }
        });
    });

    // Stop timer
    $('#stop-timer').on('click', function() {
        $.get('{{ route("time.running") }}', function(response) {
            if (response.running && response.time) {
                $.post(`{{ url('time') }}/${response.time.id}/stop`, {
                    _token: '{{ csrf_token() }}'
                }, function(stopResponse) {
                    if (stopResponse.success) {
                        stopTimer();
                        $('#start-timer').prop('disabled', false);
                        $('#stop-timer').prop('disabled', true);
                        $('#project_id, #task_id, #description').prop('disabled', false);
                        alert(`Timer detenido. Duración: ${stopResponse.duration}`);
                    }
                });
            }
        });
    });

    function startTimer() {
        isRunning = true;
        startTime = new Date();
        timerInterval = setInterval(updateTimer, 1000);
    }

    function stopTimer() {
        isRunning = false;
        clearInterval(timerInterval);
        $('#timer-display').text('00:00:00');
    }

    function updateTimer() {
        if (isRunning) {
            const now = new Date();
            const diff = now - startTime;
            const hours = Math.floor(diff / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);

            const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            $('#timer-display').text(timeString);
        }
    }

    // Check for running timer on page load
    $.get('{{ route("time.running") }}', function(response) {
        if (response.running && response.time) {
            // Timer is already running, show it
            $('#start-timer').prop('disabled', true);
            $('#stop-timer').prop('disabled', false);
            $('#project_id, #task_id, #description').prop('disabled', true);

            // Load the running timer data
            if (response.time.task_id) {
                loadTasks(response.time.task.project_id);
                $('#task_id').val(response.time.task_id).trigger('change');
            }
            if (response.time.description) {
                $('#description').val(response.time.description);
            }

            // Start the visual timer
            startTimer();
        }
    });
});
</script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ __('Time Tracker') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Timer Display -->
                        <div class="col-md-6">
                            <div class="text-center mb-4">
                                <div class="display-1 fw-bold text-primary" id="timer-display">00:00:00</div>
                                <p class="text-muted">{{ __('Timer') }}</p>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="project_id">{{ __('Proyecto (Opcional)') }}</label>
                                <select class="form-select" id="project_id" name="project_id">
                                    <option value="">{{ __('Selecciona un proyecto') }}</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="task_id">{{ __('Tarea') }} <span class="text-danger">*</span></label>
                                <select class="form-select" id="task_id" name="task_id" required>
                                    <option value="">{{ __('Selecciona una tarea') }}</option>
                                </select>
                                <div class="form-text">{{ __('Solo se muestran tareas pendientes y en progreso') }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="description">{{ __('¿En qué estás trabajando?') }}</label>
                                <input type="text" class="form-control" id="description" name="description" placeholder="{{ __('Descripción de la tarea') }}">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success btn-lg" id="start-timer">
                                    <i class="ti ti-play me-2"></i>{{ __('Iniciar Timer') }}
                                </button>
                                <button type="button" class="btn btn-danger btn-lg" id="stop-timer" disabled>
                                    <i class="ti ti-stop me-2"></i>{{ __('Detener Timer') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
