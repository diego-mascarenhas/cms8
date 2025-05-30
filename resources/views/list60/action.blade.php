<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('contact.show'))
        <a href="{{ route('contact.show', $contact_id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endif
    @if (auth()->user()->can('contact.destroy'))
        <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-x ti-sm"></i></a>
    @endif
</div>
