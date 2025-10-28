<div class="d-flex justify-content-center align-items-center">
    @can('service.show')
        <a href="{{ route('service.show', $id) }}" class="text-body me-2"><i class="ti ti-eye ti-sm"></i></a>
    @endcan
    @can('service.edit')
        <a href="{{ route('service.edit', $id) }}" class="text-body me-2"><i class="ti ti-edit ti-sm"></i></a>
    @endcan
    {{-- @can('service.destroy')
    <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
    @endcan --}}
</div>
