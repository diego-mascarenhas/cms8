<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('collaborator.edit'))
        <a href="{{ route('collaborator.edit', $contact->id) }}" class="text-body me-2"><i class="ti ti-edit ti-sm"></i></a>
    @endif
    
    @if (auth()->user()->can('collaborator.show'))
        <a href="{{ route('collaborator.show', $contact->id) }}" class="text-body me-2"><i class="ti ti-eye ti-sm"></i></a>
    @endif
    
    @if (auth()->user()->can('collaborator.destroy'))
        <a href="javascript:;" class="text-danger btn-delete" data-id="{{ $contact->id }}"><i class="ti ti-trash ti-sm"></i></a>
    @endif
</div> 