<div class="d-flex justify-content-center align-items-center">
    @can('view', $survey)
    <a href="{{ route('survey.show', $survey) }}" class="text-body">
        <i class="ti ti-chart-bar ti-sm me-2"></i>
    </a>
    @endcan
    @can('update', $survey)
    <a href="{{ route('survey.edit', $survey) }}" class="text-body">
        <i class="ti ti-edit ti-sm me-2"></i>
    </a>
    @endcan
    @can('delete', $survey)
    <form action="{{ route('survey.destroy', $survey) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('¿Eliminar esta encuesta?') }}')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-link text-danger p-0">
            <i class="ti ti-trash ti-sm"></i>
        </button>
    </form>
    @endcan
</div>
