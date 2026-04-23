<div class="d-flex justify-content-center align-items-center">
    {{-- Link to contact --}}
    @if ($contactId)
        @role('admin|collaborator|client')
            <a href="{{ route('contact.show', $contactId) }}" class="text-body" title="{{ __('View Contact') }}">
                <i class="ti ti-user ti-sm me-2"></i>
            </a>
        @endrole
    @endif

    {{-- Detail: same screen for clients and other enterprise types (fiscal entities, etc.) --}}
    @role('admin|collaborator|client')
        <a href="{{ route('client.show', $enterprise->id) }}" class="text-body" title="{{ __('View') }}">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endrole

    @role('admin|collaborator')
        @can('edit', $enterprise)
            <a href="{{ route('client.edit', $enterprise->id) }}" class="text-body" title="{{ __('Edit') }}">
                <i class="ti ti-edit ti-sm me-2"></i>
            </a>
        @endcan
    @endrole
</div>
