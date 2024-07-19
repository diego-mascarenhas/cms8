<div class="d-flex justify-content-center align-items-center">
    <a href="{{ route('client.show', $id) }}" class="test-bodyx" target="_blank"><i class="ti ti-eye ti-sm me-2"></i></a>
    @if (auth()->user()->hasRole('admin'))
        <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
    @endif
</div>
