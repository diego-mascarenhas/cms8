{{-- Actions column template for campaigns DataTable — see .cursor/rules/datatables-action.mdc --}}
<div class="d-flex justify-content-center align-items-center">
    <a
        href="{{ route('campaigns.edit', $campaign) }}"
        class="text-body"
        aria-label="{{ __('Editar') }}"
        title="{{ __('Editar') }}"
    >
        <i class="ti ti-edit ti-sm me-2"></i>
    </a>
    <a
        href="{{ route('campaigns.show', $campaign) }}"
        class="text-body"
        aria-label="{{ __('Estadísticas') }}"
        title="{{ __('Estadísticas') }}"
    >
        <i class="ti ti-chart-bar ti-sm me-2"></i>
    </a>
    <a href="javascript:;" class="text-body" aria-label="{{ __('Duplicar') }}" title="{{ __('Duplicar') }}">
        <i class="ti ti-copy ti-sm"></i>
    </a>
</div>
