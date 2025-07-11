<div class="d-flex justify-content-center align-items-center">
    {{-- View client details --}}
    @if (auth()->user()->can('client.show'))
        <a href="{{ route('client.show', $id) }}" class="text-body">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endif

    {{-- Edit client --}}
    @if (auth()->user()->can('client.edit'))
        <a href="{{ route('client.edit', $id) }}" class="text-body">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endif

    {{-- CMS 7 integration (if available) --}}
    @if (auth()->user()->can('contact.show') && isset($id) && auth()->user()->currentTeam->id == env('CMS_TEAM_ID'))
        <a href="{{ route('cms7.empresa', $id) }}" class="text-body ms-2" target="_blank">
            <i class="tf-icons ti ti-database ti-sm" title="Ver datos del CMS 7"></i>
        </a>
    @endif
</div>