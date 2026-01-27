<div class="d-flex justify-content-center align-items-center">
    {{-- Link to contact --}}
    @if ($contactId)
        @role('admin|collaborator|client')
            <a href="{{ route('contact.show', $contactId) }}" class="text-body" title="{{ __('View Contact') }}">
                <i class="ti ti-user ti-sm me-2"></i>
            </a>
        @endrole
    @endif

    {{-- View enterprise details --}}
    @role('admin|collaborator|client')
        <a href="{{ route('client.show', $id) }}" class="text-body">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endrole

    {{-- Edit enterprise --}}
    @role('admin|collaborator')
        <a href="{{ route('client.edit', $id) }}" class="text-body">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endrole
</div>
