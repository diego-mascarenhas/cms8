<div class="d-flex justify-content-center align-items-center">
    @can('view', $prompt)
    <a href="{{ route('prompt.show', $prompt) }}" class="text-body" title="{{ __('Ver') }}">
        <i class="ti ti-eye ti-sm me-2"></i>
    </a>
    @endcan
    @can('update', $prompt)
    <a href="{{ route('prompt.edit', $prompt) }}" class="text-body" title="{{ __('Editar') }}">
        <i class="ti ti-edit ti-sm me-2"></i>
    </a>
    @endcan
</div>
