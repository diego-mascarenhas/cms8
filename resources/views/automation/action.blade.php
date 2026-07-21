<div class="d-flex justify-content-center align-items-center">
    @can('view', $automation)
    <a href="{{ route('automation.show', $automation) }}" class="text-body" title="{{ __('Ver') }}">
        <i class="ti ti-eye ti-sm me-2"></i>
    </a>
    @endcan
    @can('update', $automation)
    <a href="{{ route('automation.edit', $automation) }}" class="text-body" title="{{ __('Editar') }}">
        <i class="ti ti-edit ti-sm me-2"></i>
    </a>
    @endcan
</div>
