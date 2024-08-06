<div class="d-flex justify-content-center align-items-center">
    <a href="{{ route('prompt.edit', $id) }}" class="test-bodyx"><i class="ti ti-edit ti-sm me-2"></i></a>
    <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
</div>
