@extends('layouts/layoutMaster')

@section('title', __('payment_sync.mercadopago.assign_title'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('payment_sync.mercadopago.assign_title') }}</h4>
        <p class="text-muted">{{ __('payment_sync.mercadopago.assign_subtitle') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('payments.syncs.mercadopago.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('payment_sync.mercadopago.back') }}
        </a>
    </div>
</div>

@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <h6 class="mb-3">{{ __('payment_sync.mercadopago.section_payment') }}</h6>
        <dl class="row mb-4">
            <dt class="col-sm-3">{{ __('payment_sync.mercadopago.columns.date') }}</dt>
            <dd class="col-sm-9">{{ $sync->charge_created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            <dt class="col-sm-3">{{ __('payment_sync.mercadopago.columns.amount') }}</dt>
            <dd class="col-sm-9">{{ number_format($amountMajor, 2, ',', '.') }} {{ strtoupper((string) $sync->currency) }}</dd>
            <dt class="col-sm-3">{{ __('payment_sync.mercadopago.columns.payer') }}</dt>
            <dd class="col-sm-9">
                @if ($sync->displayPayerName())
                    <div>{{ $sync->displayPayerName() }}</div>
                    @if ($sync->settlementPayerIdNumber())
                        <div class="small text-muted">
                            {{ $sync->settlementPayerIdType() ?: __('payment_sync.mercadopago.settlement_payer_id') }}:
                            {{ $sync->settlementPayerIdNumber() }}
                        </div>
                    @endif
                @elseif ($sync->lacksIdentifiablePayer())
                    <span class="text-muted">{{ __('payment_sync.mercadopago.payer_unknown') }}</span>
                    <div class="small text-muted">{{ __('payment_sync.mercadopago.payer_unknown_hint') }}</div>
                @else
                    @if ($sync->customer_id)
                        <code>{{ $sync->customer_id }}</code>
                    @else
                        —
                    @endif
                    @if ($sync->customer_email)
                        <div class="small text-muted">{{ $sync->customer_email }}</div>
                    @endif
                @endif
            </dd>
            <dt class="col-sm-3">{{ __('payment_sync.mercadopago.columns.description') }}</dt>
            <dd class="col-sm-9">{{ $sync->description ?: '—' }}</dd>
            <dt class="col-sm-3">{{ __('payment_sync.mercadopago.columns.external_id') }}</dt>
            <dd class="col-sm-9"><code>{{ $sync->external_id }}</code></dd>
            @if ($sync->identificationCode())
                <dt class="col-sm-3">{{ __('payment_sync.mercadopago.identification_code') }}</dt>
                <dd class="col-sm-9"><code>{{ $sync->identificationCode() }}</code></dd>
            @endif
        </dl>

        <h6 class="mb-3">{{ __('payment_sync.mercadopago.section_client') }}</h6>

        <form method="GET" action="{{ route('payments.syncs.mercadopago.assign', $sync) }}" class="row g-3 mb-3">
            <div class="col-12">
                <label for="enterprise_id_filter" class="form-label">{{ __('payment_sync.mercadopago.enterprise_label') }}</label>
                <div class="d-flex flex-column flex-md-row gap-2 align-items-stretch align-items-md-start">
                    <select name="enterprise_id" id="enterprise_id_filter" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('payment_sync.mercadopago.enterprise_placeholder') }}</option>
                        @foreach ($enterprises as $enterprise)
                            <option value="{{ $enterprise->id }}" @selected((string) $selectedEnterpriseId === (string) $enterprise->id)>
                                {{ $enterprise->name }}
                                @if (filled($enterprise->code)) ({{ $enterprise->code }}) @endif
                                @if (filled($enterprise->email)) — {{ $enterprise->email }} @endif
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-label-secondary flex-shrink-0 text-nowrap">
                        <i class="ti ti-refresh me-1"></i>{{ __('payment_sync.mercadopago.reload_invoices') }}
                    </button>
                </div>
                <div class="form-text">{{ __('payment_sync.mercadopago.enterprise_filter_hint') }}</div>
            </div>
        </form>

        @if ($selectedEnterpriseId > 0 && count($suggestions) > 0)
            <div class="alert alert-info mb-4" role="alert">
                <div class="fw-semibold mb-2">{{ __('payment_sync.mercadopago.suggestions_title') }}</div>
                <div class="d-flex flex-column gap-2">
                    @foreach ($suggestions as $index => $suggestion)
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary text-start mp-apply-suggestion"
                            data-ids='@json($suggestion['invoice_ids'])'
                        >
                            @if ($suggestion['kind'] === 'exact')
                                <span class="badge bg-label-success me-1">{{ __('payment_sync.mercadopago.suggestion_exact') }}</span>
                            @elseif ($suggestion['kind'] === 'paid_link')
                                <span class="badge bg-label-warning me-1">{{ __('payment_sync.mercadopago.suggestion_paid_link') }}</span>
                            @else
                                <span class="badge bg-label-warning me-1">{{ __('payment_sync.mercadopago.suggestion_combo') }}</span>
                            @endif
                            {{ $suggestion['label'] }}
                        </button>
                    @endforeach
                </div>
                <div class="form-text mt-2 mb-0">{{ __('payment_sync.mercadopago.suggestions_hint') }}</div>
            </div>
        @endif

        <form action="{{ route('payments.syncs.mercadopago.import', $sync) }}" method="POST" class="row g-3" id="mp-import-form">
            @csrf
            <input type="hidden" name="enterprise_id" value="{{ $selectedEnterpriseId ?: '' }}">

            <div class="col-12">
                <label class="form-label">{{ __('payment_sync.mercadopago.invoice_label') }}</label>
                @if ($selectedEnterpriseId <= 0)
                    <p class="text-muted mb-0">{{ __('payment_sync.mercadopago.pick_client_first') }}</p>
                @elseif ($invoices->isEmpty() && $paidUnlinkedInvoices->isEmpty())
                    <p class="text-muted mb-0">{{ __('payment_sync.mercadopago.no_open_invoices') }}</p>
                @else
                    @if ($invoices->isNotEmpty())
                        <div class="fw-semibold small mb-2">{{ __('payment_sync.mercadopago.open_invoices_heading') }}</div>
                        <div class="border rounded p-3 mb-3" style="max-height: 240px; overflow: auto;">
                            @foreach ($invoices as $invoice)
                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input mp-invoice-check"
                                        type="checkbox"
                                        name="invoice_ids[]"
                                        value="{{ $invoice->id }}"
                                        id="invoice_{{ $invoice->id }}"
                                        @checked(in_array((int) $invoice->id, $selectedInvoiceIds, true))
                                    >
                                    <label class="form-check-label" for="invoice_{{ $invoice->id }}">
                                        {{ $invoiceOptionFormatter::label($invoice) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text mb-3">{{ __('payment_sync.mercadopago.invoice_hint') }}</div>
                    @endif

                    @if ($paidUnlinkedInvoices->isNotEmpty())
                        <div class="fw-semibold small mb-2">{{ __('payment_sync.mercadopago.paid_unlinked_heading') }}</div>
                        <div class="border rounded border-warning p-3" style="max-height: 240px; overflow: auto;">
                            @foreach ($paidUnlinkedInvoices as $invoice)
                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input mp-invoice-check mp-paid-link-check"
                                        type="checkbox"
                                        name="invoice_ids[]"
                                        value="{{ $invoice->id }}"
                                        id="invoice_paid_{{ $invoice->id }}"
                                        @checked(in_array((int) $invoice->id, $selectedInvoiceIds, true))
                                    >
                                    <label class="form-check-label" for="invoice_paid_{{ $invoice->id }}">
                                        <span class="badge bg-label-warning me-1">{{ __('payment_sync.mercadopago.paid_unlinked_badge') }}</span>
                                        {{ $invoiceOptionFormatter::paidLinkLabel($invoice) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">{{ __('payment_sync.mercadopago.paid_unlinked_hint') }}</div>
                    @endif
                @endif
                @error('invoice_ids')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <div class="form-group">
                    <label class="form-label">{{ __('payment_sync.mercadopago.reference_number') }}</label>
                    <input
                        type="text"
                        class="form-control"
                        value="{{ $sync->identificationCode() ?: $sync->external_id }}"
                        disabled
                        readonly
                    >
                    <div class="form-text">{{ __('payment_sync.mercadopago.reference_number_hint') }}</div>
                </div>
            </div>

            <div class="col-12">
                <div class="form-group">
                    <label for="remarks" class="form-label">{{ __('payment_sync.mercadopago.remarks') }}</label>
                    <textarea
                        id="remarks"
                        name="remarks"
                        class="form-control @error('remarks') is-invalid @enderror"
                        rows="3"
                        maxlength="500"
                        placeholder="{{ __('payment_sync.mercadopago.remarks_placeholder') }}"
                    >{{ old('remarks', '') }}</textarea>
                    <div class="form-text">{{ __('payment_sync.mercadopago.remarks_hint') }}</div>
                    @error('remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" name="link_payer_code" id="link_payer_code"
                        @checked(old('link_payer_code', ! $sync->lacksIdentifiablePayer()))
                        @disabled($sync->lacksIdentifiablePayer())>
                    <label class="form-check-label" for="link_payer_code">
                        {{ __('payment_sync.mercadopago.link_payer_code') }}
                    </label>
                </div>
                @if ($sync->lacksIdentifiablePayer())
                    <div class="form-text">{{ __('payment_sync.mercadopago.link_payer_code_disabled_hint') }}</div>
                    <input type="hidden" name="link_payer_code" value="0">
                @endif
            </div>

            @error('enterprise_id')
                <div class="col-12">
                    <div class="text-danger small">{{ $message }}</div>
                </div>
            @enderror

            <div class="col-12">
                <button type="submit" class="btn btn-primary" @disabled($selectedEnterpriseId <= 0)>
                    <i class="ti ti-download me-1"></i>{{ __('payment_sync.mercadopago.submit') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.querySelectorAll('.mp-apply-suggestion').forEach((button) => {
    button.addEventListener('click', () => {
        const ids = JSON.parse(button.dataset.ids || '[]').map(String);
        document.querySelectorAll('.mp-invoice-check').forEach((checkbox) => {
            checkbox.checked = ids.includes(checkbox.value);
        });
    });
});
</script>
@endsection
