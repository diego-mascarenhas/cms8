<div class="d-inline-block">
    <a href="{{ route('collaborator.show', $contact) }}" class="btn btn-sm btn-icon">
        <i class="ti ti-eye"></i>
    </a>
    <a href="{{ route('collaborator.edit', $contact) }}" class="btn btn-sm btn-icon">
        <i class="ti ti-edit"></i>
    </a>
    <form action="{{ route('collaborator.destroy', $contact) }}" method="POST" class="d-inline">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-icon" onclick="return confirm('{{ __('Are you sure?') }}')">
            <i class="ti ti-trash"></i>
        </button>
    </form>
</div> 