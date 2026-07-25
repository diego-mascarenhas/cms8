@php
    /** @var \App\Models\PaymentSync $sync */
    /** @var array<string, mixed>|null $suggestion */
    /** @var \App\Models\Payment|null $payment */
    /** @var \App\Models\BankStatementLine|null $statementLine */
@endphp
<div class="d-flex justify-content-center align-items-center flex-wrap gap-1">
    @if ($status === 'suggestion' && is_array($suggestion))
        <form method="POST" action="{{ route('payments.reconcile.accept') }}" class="d-inline">
            @csrf
            <input type="hidden" name="sync_id" value="{{ $sync->id }}">
            <input type="hidden" name="enterprise_id" value="{{ $suggestion['enterprise_id'] ?? '' }}">
            @foreach (($suggestion['invoice_ids'] ?? []) as $invoiceId)
                <input type="hidden" name="invoice_ids[]" value="{{ $invoiceId }}">
            @endforeach
            <button type="submit" class="btn btn-sm btn-primary" title="{{ __('payment_sync.reconcile.accept') }}">
                <i class="ti ti-check ti-xs"></i>
            </button>
        </form>
    @endif

    @if ($status === 'mismatch')
        <form method="POST" action="{{ route('payments.reconcile.dismiss') }}" class="d-inline">
            @csrf
            <input type="hidden" name="sync_id" value="{{ $sync->id }}">
            <input type="hidden" name="statement_line_id" value="{{ $statementLine?->id }}">
            <button type="submit" class="btn btn-sm btn-success" title="{{ __('payment_sync.reconcile.dismiss') }}">
                <i class="ti ti-check ti-xs"></i>
            </button>
        </form>
        <form method="POST" action="{{ route('payments.reconcile.undo') }}" class="d-inline">
            @csrf
            <input type="hidden" name="sync_id" value="{{ $sync->id }}">
            <button type="submit" class="btn btn-sm btn-danger" title="{{ __('payment_sync.reconcile.undo') }}">
                <i class="ti ti-arrow-back-up ti-xs"></i>
            </button>
        </form>
    @endif

    @if (in_array($status, ['pending', 'suggestion', 'mismatch'], true))
        <a
            href="{{ route('payments.syncs.mercadopago.assign', ['sync' => $sync->id, 'enterprise_id' => $suggestion['enterprise_id'] ?? $payment?->enterprise_id]) }}"
            class="btn btn-sm btn-label-primary"
            title="{{ __('payment_sync.reconcile.open_manual') }}"
        >
            <i class="ti ti-edit ti-xs"></i>
        </a>
    @endif

    @if ($payment?->invoice_id)
        <a
            href="{{ route('invoice.show', $payment->invoice_id) }}"
            class="btn btn-sm btn-label-info"
            title="{{ __('payment_sync.mercadopago.auto_assign.view_invoice') }}"
        >
            <i class="ti ti-file-invoice ti-xs"></i>
        </a>
    @endif
</div>
