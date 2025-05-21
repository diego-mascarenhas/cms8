<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('contact.show') && isset($responsible_id) && $responsible_id)
        <a href="{{ route('contact.show', $responsible_id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endif
    @if (auth()->user()->can('contact.show') && isset($id) && auth()->user()->currentTeam->id == env('CMS_TEAM_ID'))
        <a href="{{ route('cms7.empresa', $id) }}" class="text-body ms-2" target="_blank">
            <i class="tf-icons ti ti-database ti-sm" title="Ver datos del CMS 7"></i>
        </a>
    @endif
</div>