<div class="d-flex justify-content-center align-items-center">
    <a href="javascript:;" class="text-body edit-sentiment" data-id="{{ $id }}"><i class="ti ti-mood-happy ti-sm me-2"></i></a>
    {{-- <a href="#" class="text-body"><i class="ti ti-message-chatbot ti-sm me-2"></i></a> --}}
    @if (auth()->user()->can('contact.show'))
        <a href="{{ route('contact.show', $id) }}" class="text-body"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endif
    @if (auth()->user()->can('contact.destroy'))
        <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
    @endif
</div>
