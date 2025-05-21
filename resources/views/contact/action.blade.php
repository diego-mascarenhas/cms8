<div class="d-flex justify-content-center align-items-center">
    <a href="javascript:;" class="text-body edit-sentiment" data-id="{{ $contact->id }}"><i class="ti ti-mood-happy ti-sm me-2"></i></a>
    {{-- <a href="#" class="text-body"><i class="ti ti-message-chatbot ti-sm me-2"></i></a> --}}
    @if (auth()->user()->can('contact.edit'))
        @if ($contact->isInList60())
            <a class="text-success"><i class="ti ti-list-check ti-sm me-2"></i></a>
        @else
            <a href="#" class="text-body" onclick="addToList({{ $contact->id }}, this)"><i class="ti ti-list-check ti-sm me-2"></i></a>
        @endif
    @endif
    @if (auth()->user()->can('contact.show'))
        <a href="{{ route('contact.show', $contact->id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endif
    @if (auth()->user()->can('contact.destroy'))
        <a href="#" class="text-danger" onclick="deleteRecord({{ $contact->id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
    @endif
</div>
