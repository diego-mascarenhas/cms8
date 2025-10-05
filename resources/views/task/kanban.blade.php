@extends('layouts/layoutMaster')

@section('title', __('Tasks') . ' - Kanban')

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
'use strict';

(function() {
    let kanbanSidebar, kanban;
    const statuses = @json($statuses);
    const tasksByStatus = @json($tasksByStatus);

    // Single board view (no selector)

    // Initialize Kanban
    if (typeof jKanban !== 'undefined') {
        const kanbanWrapper = document.querySelector('.kanban-wrapper');

        // Prepare boards data for jKanban
        const kanbanBoards = statuses.map(status => {
            const tasks = tasksByStatus[status.id] || [];
            return {
                id: 'status_' + status.id,
                title: status.name,
                item: tasks.map(task => ({
                    id: task.id.toString(),
                    title: task.title,
                    badge: task.category ? [{
                        text: task.category.name,
                        class: 'bg-label-primary'
                    }] : [],
                    dueDate: task.due_date,
                    assigned: task.responsible ? task.responsible.name : ''
                }))
            };
        });

        kanban = new jKanban({
            element: '.kanban-wrapper',
            gutter: '15px',
            widthBoard: '250px',
            dragItems: true,
            boards: kanbanBoards,
            dragBoards: false,
            addItemButton: true,
            buttonContent: '+ {{ __("Add Task") }}',
            itemAddOptions: {
                enabled: true,
                content: '+ {{ __("Add New Task") }}',
                footer: false
            },
            click: function(el) {
                const taskId = el.getAttribute('data-eid');
                const taskUrl = '{{ route("task.show", ":id") }}'.replace(':id', taskId);
                window.location.href = taskUrl;
            },
            dropEl: function(el, target, source, sibling) {
                const taskId = el.getAttribute('data-eid');
                const newStatusId = target.parentElement.getAttribute('data-id').replace('status_', '');

                // Update task status via AJAX
                fetch('{{ route("task.update-status") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        status_id: newStatusId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success notification
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: '{{ __("Updated!") }}',
                                text: '{{ __("Task status updated successfully") }}',
                                showConfirmButton: false,
                                timer: 1500
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Revert the move if there was an error
                    source.appendChild(el);
                });
            }
        });
    }

    // View Toggle
    $('#view-list-btn').on('click', function(e) {
        e.preventDefault();
        window.location.href = '{{ route("task.index") }}';
    });

    // Add Task Button
    $('#add-task-btn').on('click', function(e) {
        e.preventDefault();
        window.location.href = '{{ route('task.create') }}';
    });
})();
</script>
@endsection

@section('content')
<div class="app-kanban">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Tasks') }} - {{ __('Kanban Board') }}</h4>
            <p class="text-muted">{{ __('Manage tasks visually') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
            <button type="button" class="btn btn-primary" id="add-task-btn">
                <i class="ti ti-plus me-1"></i>{{ __('Add Task') }}
            </button>
            <button type="button" class="btn btn-label-secondary" id="view-list-btn">
                <i class="ti ti-list me-1"></i>{{ __('List View') }}
            </button>
        </div>
    </div>

    

    <!-- Kanban Wrapper -->
    <div class="kanban-wrapper"></div>

    <!-- Edit Task Sidebar -->
    <div class="offcanvas offcanvas-end kanban-update-item-sidebar">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title">{{ __('Edit Task') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <!-- Task edit form would go here -->
            <p class="text-muted">{{ __('Click on a task card to edit') }}</p>
        </div>
    </div>
</div>
@endsection
