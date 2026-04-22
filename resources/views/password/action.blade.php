<div class="d-flex justify-content-center align-items-center">
    @can('update', $teamPassword)
        <a href="{{ route('passwords.edit', $teamPassword) }}" class="text-body" title="{{ __('Edit') }}">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endcan

    @can('view', $teamPassword)
        <a href="javascript:;" class="text-body" onclick="revealPassword({{ $teamPassword->id }})" title="{{ __('Reveal and copy') }}">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
        <a href="javascript:;" class="text-body" onclick="createShare({{ $teamPassword->id }})" title="{{ __('Create public share URL') }}">
            <i class="ti ti-link ti-sm me-2"></i>
        </a>
    @endcan

    @can('delete', $teamPassword)
        <form method="POST" action="{{ route('passwords.destroy', $teamPassword) }}" onsubmit="return confirm('{{ __('Delete this password?') }}')" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn p-0 text-danger border-0 bg-transparent" title="{{ __('Delete') }}">
                <i class="ti ti-trash ti-sm"></i>
            </button>
        </form>
    @endcan
</div>
