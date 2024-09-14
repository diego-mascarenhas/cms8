<div class="d-flex justify-content-center align-items-center">
    <a href="#" class="test-bodyx"><i class="ti ti-message-chatbot ti-sm me-2"></i></a>
    @if (auth()->user()->can('client.edit'))
        <a href="{{ route('client.edit', $id) }}" class="test-bodyx"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endif
    @if (auth()->user()->can('client.destroy'))
        <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
    @endif
</div>
