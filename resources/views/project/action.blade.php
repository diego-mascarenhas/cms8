<div class="d-flex justify-content-center align-items-center">
    <a href="{{ route('project.edit', $id) }}" class="test-bodyx"><i class="ti ti-edit ti-sm me-2"></i></a>
    @if (auth()->user()->can('project.destroy'))
        <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
     @endif
</div>
