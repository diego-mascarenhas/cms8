<div class="d-flex justify-content-center align-items-center">
    @role('admin|collaborator|developer|editor|technical|client')
        <a href="{{ route('opportunity.show', $id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endrole
    @role('admin|collaborator|developer|technical')
        <a href="{{ route('opportunity.edit', $id) }}" class="text-body"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endrole
</div>
