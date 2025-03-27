<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('contact.show'))
        <a href="{{ route('contact.show', $responsible_id) }}" class="text-body"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endif
    @if (auth()->user()->can('project.create'))
        <a href="{{ route('project.create', ['client_id' => $id]) }}" class="text-body">
            <i class="tf-icons ti ti-folder-plus ti-sm" title="Create Project"></i>
        </a>
    @endif
</div>