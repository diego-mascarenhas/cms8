<div class="d-flex justify-content-center align-items-center">
    <a href="{{ route('template.view', $id) }}" class="test-bodyx" target="_blank"><i class="ti ti-eye ti-sm me-2"></i></a>
    <a href="{{ route('template.edit', $id) }}" class="test-bodyx" target="_blank"><i class="ti ti-code ti-sm me-2"></i></a>
    <a href="{{ route('template.edit', $id) }}" class="test-bodyx"><i class="ti ti-edit ti-sm me-2"></i></a>
    <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
</div>
