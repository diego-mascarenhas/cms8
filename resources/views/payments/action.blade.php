{{-- Action column for Payments DataTable --}}
<div class="d-flex justify-content-center align-items-center">
    @if (auth()->user()->can('payment.show'))
        <a href="#" class="text-body">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
    @endif

    @if (auth()->user()->can('payment.edit'))
        <a href="#" class="text-body">
            <i class="ti ti-edit ti-sm me-2"></i>
        </a>
    @endif

    @if (auth()->user()->can('payment.destroy'))
        <a href="#" class="text-danger" onclick="deletePayment({{ $id }}, this)">
            <i class="ti ti-trash ti-sm"></i>
        </a>
    @endif
</div>

