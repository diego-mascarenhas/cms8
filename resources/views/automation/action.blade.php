<div class="d-flex justify-content-center align-items-center">
    @can('view', $automation)
    @if($automation->isFunnel())
    <a href="{{ route('funnel.show', $automation) }}" class="text-body" title="{{ __('Ver embudo') }}">
        <i class="ti ti-eye ti-sm me-2"></i>
    </a>
    @else
    <a href="{{ route('automation.show', $automation) }}" class="text-body" title="{{ __('Ver') }}">
        <i class="ti ti-eye ti-sm me-2"></i>
    </a>
    @endif
    @endcan
    @can('update', $automation)
    @if($automation->isFunnel())
    <a href="{{ route('funnel.flow', $automation) }}" class="text-body" title="{{ __('Editar embudo') }}">
        <i class="ti ti-sitemap ti-sm me-2"></i>
    </a>
    @else
    <a href="{{ route('automation.edit', $automation) }}" class="text-body" title="{{ __('Editar') }}">
        <i class="ti ti-edit ti-sm me-2"></i>
    </a>
    @endif
    @endcan
</div>
