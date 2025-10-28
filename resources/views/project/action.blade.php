<div class="d-flex justify-content-center align-items-center">
    @can('project.show')
        <a href="{{ route('project.show', $id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endcan
    @can('project.edit')
        <a href="{{ route('project.edit', $id) }}" class="text-body"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endcan
</div>
