{{-- Action column template for Income DataTable --}}
<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('payment.show'))
    <a href="{{ route('payments.show', $id) }}" class="text-body">
        <i class="ti ti-eye ti-sm me-2"></i>
    </a>
    @endif
</div>
