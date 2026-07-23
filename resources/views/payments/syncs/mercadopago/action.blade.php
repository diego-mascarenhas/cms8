<div class="d-flex justify-content-center align-items-center">
    @if (! empty($linkedInvoiceId))
        <a href="{{ route('invoice.show', $linkedInvoiceId) }}" class="text-body" title="{{ __('payment_sync.mercadopago.view_invoice') }}">
            <i class="ti ti-file-invoice ti-sm me-1"></i>{{ $linkedInvoiceNumber ?: __('payment_sync.mercadopago.view_invoice') }}
        </a>
    @elseif (! empty($isStripeLinked))
        <a href="{{ route('payments.syncs.mercadopago.linked-invoice', $id) }}" class="text-body" title="{{ __('payment_sync.mercadopago.view_invoice') }}">
            <i class="ti ti-file-invoice ti-sm me-1"></i>{{ $linkedInvoiceNumber ?: __('payment_sync.mercadopago.view_invoice') }}
        </a>
    @else
        <a href="{{ route('payments.syncs.mercadopago.assign', $id) }}" class="btn btn-sm btn-primary" title="{{ __('payment_sync.mercadopago.assign_action') }}">
            <i class="ti ti-link ti-sm me-1"></i>{{ __('payment_sync.mercadopago.assign_action') }}
        </a>
    @endif
</div>
