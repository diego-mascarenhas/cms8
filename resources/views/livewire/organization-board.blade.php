<div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">Organización</h4>
            <p class="text-muted">Organización por departamentos</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('organization.create') }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-plus me-1"></i> Create New Task
            </a>
            @can('viewAny', \App\Models\EnterpriseDepartment::class)
            <a href="{{ route('department.index') }}" class="btn btn-outline-primary waves-effect waves-light">
                <i class="ti ti-users-group me-1"></i> Definir departamentos
            </a>
            @endcan
        </div>
    </div>

    @foreach ($departmentPostits as $department)
        <div class="card mb-4" wire:key="department-{{ $department['id'] }}">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ $department['name'] }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap organization-postits-container" id="department-postits-{{ $department['id'] }}" data-department-id="{{ $department['id'] }}">
                    @foreach ($department['postits'] as $postit)
                        <div class="post-it organization-post-it" style="background-color: {{ $postit['color'] }};" data-id="{{ $postit['id'] }}" wire:key="postit-{{ $postit['id'] }}">
                            <div class="post-it-header">{{ $postit['header'] }}</div>
                            <div class="post-it-date">{{ $postit['author'] }}</div>
                            <div class="post-it-content" onclick="openContentModal('{{ addslashes($postit['header']) }}', `{{ addslashes(str_replace(["\r\n", "\r", "\n"], "\\n", $postit['content'])) }}`)">
                                {{ Str::limit($postit['content'], 100, '...') }}
                            </div>
                            <div class="post-it-tag">
                                {{ $postit['time_allocation'] }}
                                @if (!empty($postit['availability']))
                                    ({{ $postit['availability'] }})
                                @endif
                            </div>
                            <div class="post-it-actions d-flex align-items-center gap-1">
                                <a href="javascript:;" class="text-body" onclick="openContentModal('{{ addslashes($postit['header']) }}', `{{ addslashes(str_replace(["\r\n", "\r", "\n"], "\\n", $postit['content'])) }}`)" title="{{ __('View') }}">
                                    <i class="ti ti-eye ti-sm me-1"></i>
                                </a>
                                <a href="{{ route('organization.edit', $postit['id']) }}" class="text-body" title="{{ __('Edit') }}">
                                    <i class="ti ti-pencil ti-sm me-1"></i>
                                </a>
                                <form action="{{ route('organization.destroy', $postit['id']) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <a href="#" class="text-danger btn-delete" title="{{ __('Delete') }}">
                                        <i class="ti ti-trash ti-sm"></i>
                                    </a>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
