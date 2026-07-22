<div class="d-flex justify-content-center align-items-center">
    @can('update', $account)
        <a href="{{ route('payment-account.edit', $account) }}" class="text-body" title="{{ __('Edit') }}">
            <i class="ti ti-edit ti-sm"></i>
        </a>
    @endcan
</div>
