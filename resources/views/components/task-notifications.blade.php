@if(auth()->user()->currentTeam && auth()->user()->currentTeam->hasModule('tasks'))
<li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1">
    <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown"
        data-bs-auto-close="outside" aria-expanded="false">
        <i class="ti ti-bell ti-md"></i>
        @if($pendingTasks->count() > 0)
            <span class="badge bg-danger rounded-pill badge-notifications">{{ $pendingTasks->count() }}</span>
        @endif
    </a>
    <ul class="dropdown-menu dropdown-menu-end py-0">
        <li class="dropdown-menu-header border-bottom">
            <div class="dropdown-header d-flex align-items-center py-3">
                <h5 class="text-body mb-0 me-auto">{{ __('My Tasks') }}</h5>
                {{-- <a href="{{ route('task.create') }}" class="text-body">
                    <i class="ti ti-plus fs-4"></i>
                </a> --}}
            </div>
        </li>
        <li class="dropdown-notifications-list scrollable-container">
            <ul class="list-group list-group-flush">
                @forelse($pendingTasks as $task)
                    <li class="list-group-item list-group-item-action dropdown-notifications-item">
                        <div class="d-flex">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar">
                                    <span class="avatar-initial rounded-circle {{ $task->status->color }}">
                                        <i class="ti ti-calendar-due"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="flex-grow-1 d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">{{ Str::limit($task->title, 30) }}</h6>
                                    <p class="mb-0">{{ __('Due date') }}: {{ Carbon\Carbon::parse($task->due_date)->format('d-m-Y') }}</p>
                                    <div class="mt-1">
                                        {!! $task->status_label !!}
                                    </div>
                                </div>
                                <div class="ms-2">
                                    <a href="{{ route('task.edit', $task->id) }}" class="test-bodyx">
                                        <i class="ti ti-edit ti-sm"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item list-group-item-action dropdown-notifications-item">
                        <div class="d-flex">
                            <div class="flex-grow-1 text-center py-3">
                                <p class="mb-0">{{ __('No pending tasks for me') }}</p>
                            </div>
                        </div>
                    </li>
                @endforelse
            </ul>
        </li>
        <li class="dropdown-menu-footer border-top">
            <div class="d-flex justify-content-between p-2">
                <a href="{{ route('task.create') }}" class="dropdown-item d-flex align-items-center px-3">
                    <i class="ti ti-plus me-2"></i>
                    {{ __('Add task') }}
                </a>
                <a href="{{ route('task.index') }}" class="dropdown-item d-flex align-items-center px-3">
                    <i class="ti ti-list me-2"></i>
                    {{ __('View all') }}
                </a>
            </div>
        </li>
    </ul>
</li>
@endif
