<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('payment.edit'))
        <a href="{{ route('payment.edit', $id) }}" class="test-bodyx"><i class="ti ti-edit ti-sm me-2"></i></a>
    @endif
</div>
