@extends('layouts/layoutMaster')

@section('title', __('Task Boards'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
@endsection

@section('page-script')
<script>
$(function() {
    // Initialize Sortable
    const boardsList = document.getElementById('boards-list');
    
    if (boardsList) {
        Sortable.create(boardsList, {
            animation: 150,
            ghostClass: 'bg-light',
            handle: '.handle',
            onEnd: function() {
                const boards = [];
                
                document.querySelectorAll('#boards-list .board-item').forEach((item, index) => {
                    boards.push({
                        id: item.getAttribute('data-id'),
                        order: index
                    });
                });
                
                // Update order via AJAX
                $.ajax({
                    url: '{{ route("task-board.update-order") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        boards: boards
                    },
                    error: function(xhr) {
                        console.error('Error updating board order:', xhr.responseText);
                    }
                });
            }
        });
    }
    
    // Delete confirmation
    $('.delete-board').on('click', function(e) {
        e.preventDefault();
        const deleteUrl = $(this).attr('href');
        
        Swal.fire({
            title: '{{ __("Are you sure?") }}',
            text: '{{ __("You will not be able to recover this board!") }}',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '{{ __("Yes, delete it!") }}',
            cancelButtonText: '{{ __("No, cancel") }}'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = deleteUrl;
            }
        });
    });
});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Task Boards') }}</h4>
        <p class="text-muted">{{ __('Manage your Kanban boards') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('task-board.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>{{ __('New Board') }}
        </a>
        <a href="{{ route('task.index', ['view' => 'kanban']) }}" class="btn btn-secondary">
            <i class="ti ti-layout-kanban me-1"></i>{{ __('Back to Kanban') }}
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert alert-danger">
    {{ session('error') }}
</div>
@endif

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="50"></th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Default') }}</th>
                        <th width="150">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody id="boards-list">
                    @foreach($boards as $board)
                    <tr class="board-item" data-id="{{ $board->id }}">
                        <td class="handle text-center" style="cursor: move">
                            <i class="ti ti-grip-vertical"></i>
                        </td>
                        <td>{{ $board->name }}</td>
                        <td>{{ $board->description }}</td>
                        <td>
                            @if($board->is_default)
                            <span class="badge bg-primary">{{ __('Default') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex justify-content-center align-items-center">
                                <a href="{{ route('task-board.edit', $board->id) }}" class="text-body me-2">
                                    <i class="ti ti-edit ti-sm"></i>
                                </a>
                                <a href="{{ route('task-board.destroy', $board->id) }}" class="text-danger delete-board">
                                    <i class="ti ti-trash ti-sm"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    
                    @if($boards->isEmpty())
                    <tr>
                        <td colspan="5" class="text-center py-3">{{ __('No boards found') }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection