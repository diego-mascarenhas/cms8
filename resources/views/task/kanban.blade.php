@extends('layouts/layoutMaster')

@section('title', __('Kanban'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sortablejs/sortable.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<style>
    .kanban-board {
        display: flex;
        overflow-x: auto;
        padding: 1rem 0.5rem;
        width: 100%;
        min-height: calc(100vh - 250px);
    }

    .kanban-column {
        flex: 0 0 300px;
        margin-right: 1rem;
        background-color: rgba(0, 0, 0, 0.05);
        border-radius: 0.375rem;
        display: flex;
        flex-direction: column;
    }

    .kanban-column-header {
        padding: 0.75rem;
        font-weight: bold;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .kanban-column-content {
        flex: 1;
        padding: 0.5rem;
        overflow-y: auto;
        min-height: 200px;
    }

    .kanban-task {
        background-color: white;
        border-radius: 0.375rem;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        padding: 0.75rem;
        margin-bottom: 0.75rem;
        cursor: grab;
        transition: transform 0.2s, box-shadow 0.2s;
        border-left: 4px solid #ccc;
    }

    .kanban-task.is-dragging {
        transform: rotate(1deg) scale(1.02);
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.15);
        opacity: 0.9;
    }

    .kanban-task-title {
        font-weight: bold;
        margin-bottom: 0.5rem;
    }

    .kanban-task-info {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 0.5rem;
    }

    .kanban-task-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 0.75rem;
    }

    .kanban-add-task {
        background-color: rgba(0, 0, 0, 0.05);
        border: 2px dashed rgba(0, 0, 0, 0.1);
        border-radius: 0.375rem;
        padding: 0.5rem;
        text-align: center;
        cursor: pointer;
        margin-bottom: 0.5rem;
    }

    .ghost {
        opacity: 0.5;
    }

    .board-selector {
        width: 200px;
        margin-right: 1rem;
    }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Kanban Board') }}</h4>
        <p class="text-muted">{{ __('Manage tasks visually') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <label for="board-selector" class="me-2">{{ __('Board') }}:</label>
            <select id="board-selector" class="form-select board-selector">
                @foreach($boards as $boardOption)
                    <option value="{{ $boardOption->id }}" {{ $boardOption->id == $board->id ? 'selected' : '' }}>
                        {{ $boardOption->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('task.create', ['board_id' => $board->id, 'view' => 'kanban']) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>{{ __('Add Task') }}
        </a>
        <a href="{{ route('task.index') }}" class="btn btn-secondary">
            <i class="ti ti-list me-1"></i>{{ __('List View') }}
        </a>
        @can('task-board.edit')
        <a href="{{ route('task-board.index') }}" class="btn btn-outline-primary">
            <i class="ti ti-layout-kanban me-1"></i>{{ __('Manage Boards') }}
        </a>
        @endcan
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="kanban-board">
            @foreach($statuses as $status)
                <div class="kanban-column" data-status-id="{{ $status->id }}">
                    <div class="kanban-column-header" style="color: {{ $status->color }}">
                        {{ $status->translated_name }}
                        <span class="badge bg-label-{{ str_replace('bg-label-', '', $status->label_class) }} rounded-pill">{{ count($tasksByStatus[$status->id]) }}</span>
                    </div>
                    <div class="kanban-column-content" id="kanban-{{ $status->id }}">
                        @foreach($tasksByStatus[$status->id] as $task)
                            <div class="kanban-task" 
                                data-task-id="{{ $task->id }}"
                                data-status-id="{{ $status->id }}"
                                style="border-left-color: {{ $status->color }}">
                                <div class="kanban-task-title">{{ $task->title }}</div>
                                <div>
                                    @if($task->due_date)
                                    <small class="text-muted">{{ __('Due') }}: {{ $task->due_date->format('d/m/Y') }}</small>
                                    @endif
                                </div>
                                <div class="kanban-task-footer">
                                    <div>
                                        @if($task->responsible)
                                        <span class="avatar avatar-xs" data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top" title="{{ $task->responsible->name }}">
                                            <span class="avatar-initial rounded-circle bg-label-info">{{ substr($task->responsible->name, 0, 1) }}</span>
                                        </span>
                                        @endif
                                    </div>
                                    <div>
                                        <a href="{{ route('task.edit', $task->id) }}" class="btn btn-sm btn-icon btn-text-secondary" title="{{ __('Edit') }}">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div class="kanban-add-task" data-status-id="{{ $status->id }}">
                            <i class="ti ti-plus"></i> {{ __('Add Task') }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@endsection

@section('page-script')
<script>
    $(function() {
        // Initialize Select2 for board selector
        $('#board-selector').select2({
            minimumResultsForSearch: 5
        }).on('change', function() {
            window.location = "{{ route('task.index') }}?view=kanban&board_id=" + $(this).val();
        });
        
        // Initialize Sortable for each kanban column
        let sortableColumns = document.querySelectorAll('.kanban-column-content');
        
        sortableColumns.forEach(column => {
            Sortable.create(column, {
                group: 'shared',
                animation: 150,
                ghostClass: 'ghost',
                dragClass: "is-dragging",
                onEnd: function(evt) {
                    const taskId = evt.item.getAttribute('data-task-id');
                    const newStatusId = evt.to.closest('.kanban-column').getAttribute('data-status-id');
                    const oldStatusId = evt.from.closest('.kanban-column').getAttribute('data-status-id');
                    
                    if (newStatusId !== oldStatusId) {
                        // Status has changed, update in database
                        updateTaskStatus(taskId, newStatusId, Array.from(evt.to.children).indexOf(evt.item));
                        
                        // Update the task styling to match new column
                        const newColumnHeader = evt.to.closest('.kanban-column').querySelector('.kanban-column-header');
                        const color = window.getComputedStyle(newColumnHeader).color;
                        evt.item.style.borderLeftColor = color;
                    } else {
                        // Just the order changed
                        updateTaskOrder(evt.to);
                    }
                    
                    // Update the task counts
                    updateTaskCounts();
                }
            });
        });
        
        // Function to update task counts in column headers
        function updateTaskCounts() {
            document.querySelectorAll('.kanban-column').forEach(column => {
                const statusId = column.getAttribute('data-status-id');
                const taskCount = column.querySelectorAll('.kanban-task').length;
                column.querySelector('.badge').textContent = taskCount;
            });
        }
        
        // Function to update task status via AJAX
        function updateTaskStatus(taskId, statusId, order) {
            $.ajax({
                url: '{{ route("task.update-status") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    task_id: taskId,
                    status_id: statusId,
                    order: order
                },
                success: function(response) {
                    // Toast notification could be added here
                },
                error: function(xhr) {
                    console.error('Error updating task status:', xhr.responseText);
                    // Could add error handling here
                }
            });
        }
        
        // Function to update task order within a column
        function updateTaskOrder(column) {
            const tasks = [];
            column.querySelectorAll('.kanban-task').forEach((task, index) => {
                tasks.push({
                    id: task.getAttribute('data-task-id'),
                    order: index
                });
            });
            
            $.ajax({
                url: '{{ route("task.update-order") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    tasks: tasks
                },
                error: function(xhr) {
                    console.error('Error updating task order:', xhr.responseText);
                }
            });
        }
        
        // Handle "Add Task" buttons in each column
        $('.kanban-add-task').on('click', function() {
            const statusId = $(this).data('status-id');
            window.location = "{{ route('task.create') }}?board_id={{ $board->id }}&status_id=" + statusId + "&view=kanban";
        });
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>
@endsection