<div class="d-flex justify-content-center align-items-center">
    @role('admin|collaborator|client')
        <a href="{{ route('service.show', $id) }}" class="text-body me-2"><i class="ti ti-eye ti-sm"></i></a>
    @endrole
    @role('admin|collaborator')
        <a href="{{ route('service.edit', $id) }}" class="text-body me-2"><i class="ti ti-edit ti-sm"></i></a>
    @endrole
    {{-- @role('admin')
    <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
    @endrole --}}
</div>
