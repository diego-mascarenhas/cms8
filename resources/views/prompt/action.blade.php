<div class="d-flex justify-content-center align-items-center">
    @can('update', $prompt)
    <a href="{{ route('prompt.edit', $prompt) }}" class="btn btn-sm btn-icon btn-text-body me-1" title="{{ __('Editar') }}">
        <i class="ti ti-edit ti-sm"></i>
    </a>
    @endcan
    @can('delete', $prompt)
    <form action="{{ route('prompt.destroy', $prompt) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('¿Eliminar este prompt?') }}');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="{{ __('Eliminar') }}">
            <i class="ti ti-trash ti-sm"></i>
        </button>
    </form>
    @endcan
</div>
