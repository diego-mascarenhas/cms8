<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('stylebook.show'))
        <a href="{{ route('stylebook.show', $stylebook->id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endif
    @if (auth()->user()->can('stylebook.edit'))
        <a href="{{ route('stylebook.edit', $stylebook->id) }}" class="text-body"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endif
    @if (auth()->user()->can('stylebook.destroy'))
        <a href="#" class="text-danger" onclick="deleteRecord({{ $stylebook->id }}, this)"><i class="ti ti-trash ti-sm"></i></a>
    @endif
</div> 