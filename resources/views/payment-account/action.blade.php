<div class="d-flex justify-content-center align-items-center">
    @can('view', $account)
        <a href="{{ route('payment-account.show', $account) }}" class="text-body me-2" title="{{ __('Movimientos') }}">
            <i class="ti ti-list-details ti-sm"></i>
        </a>
    @endcan
    @can('update', $account)
        <a href="{{ route('payment-account.edit', $account) }}" class="text-body" title="{{ __('Edit') }}">
            <i class="ti ti-edit ti-sm"></i>
        </a>
    @endcan
</div>
