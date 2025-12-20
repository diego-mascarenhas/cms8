<div class="d-flex justify-content-center align-items-center">
    @role('admin|collaborator|developer|editor|technical')
        <a href="{{ route('contact.show', $contact_id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endrole
    @role('admin')
        <a href="javascript:;" class="text-body me-2" onclick="openAssignModal({{ $id }}, {{ $responsible_id ?? 'null' }})" title="Asignar responsable">
            <i class="ti ti-user-edit ti-sm"></i>
        </a>
    @endrole
    @role('admin')
        <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-x ti-sm"></i></a>
    @endrole
</div>
