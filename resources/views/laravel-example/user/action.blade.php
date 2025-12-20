{{-- Action column template for User DataTables --}}
<div class="d-flex justify-content-center align-items-center">
    {{-- View/Edit User --}}
    @role('admin|developer')
        <a href="{{ route('user.show', $id) }}" class="text-body">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endrole

    {{-- Edit User --}}
    @role('admin|developer')
        <a href="javascript:;" class="text-body edit-user" data-id="{{ $id }}">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endrole

    {{-- Delete User --}}
    @role('admin')
        <a href="#" class="text-danger" onclick="deleteUser({{ $id }}, this)">
            <i class="ti ti-trash ti-sm"></i>
        </a>
    @endrole
</div>
