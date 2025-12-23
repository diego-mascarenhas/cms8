<div class="d-flex justify-content-center align-items-center">
    {{-- View client details --}}
    @role('admin|collaborator|client')
        <a href="{{ route('client.show', $id) }}" class="text-body">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endrole

    {{-- Edit client --}}
    @role('admin|collaborator')
        <a href="{{ route('client.edit', $id) }}" class="text-body">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endrole

    {{-- CMS 7 integration (if available) --}}
    @if (auth()->user()->hasRole(['admin', 'developer']) && isset($id) && auth()->user()->currentTeam->id == env('CMS_TEAM_ID'))
        <a href="{{ route('cms7.empresa', $id) }}" class="text-body ms-2" target="_blank">
            <i class="tf-icons ti ti-database ti-sm" title="Ver datos del CMS 7"></i>
        </a>
    @endif
</div>