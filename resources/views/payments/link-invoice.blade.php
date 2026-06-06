@extends('layouts/layoutMaster')

@section('title', __('payment_invoice.link.title'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('payment_invoice.link.title') }}</h4>
        <p class="text-muted">{{ __('payment_invoice.link.subtitle') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('payments.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('payment_invoice.link.back') }}
        </a>
    </div>
</div>

@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <dl class="row mb-4">
            <dt class="col-sm-3">{{ __('payment_invoice.link.payment_date') }}</dt>
            <dd class="col-sm-9">{{ $payment->date?->format('d/m/Y') ?? '—' }}</dd>
            <dt class="col-sm-3">{{ __('payment_invoice.link.amount') }}</dt>
            <dd class="col-sm-9">{{ number_format((float) $payment->amount, 2, ',', '.') }}</dd>
            @if ($payment->enterprise)
                <dt class="col-sm-3">{{ __('payment_invoice.link.enterprise') }}</dt>
                <dd class="col-sm-9">{{ $payment->enterprise->name }}</dd>
            @endif
        </dl>

        @if ($invoices->isEmpty())
            <p class="text-muted mb-0">{{ __('payment_invoice.link.no_invoices') }}</p>
        @else
            <form action="{{ route('payments.link-invoice.store', $payment) }}" method="POST" class="row g-3">
                @csrf
                <div class="col-12 col-md-10">
                    <label for="invoice_id" class="form-label">{{ __('payment_invoice.link.invoice_label') }}</label>
                    <select name="invoice_id" id="invoice_id" class="form-select @error('invoice_id') is-invalid @enderror" required>
                        <option value="">{{ __('payment_invoice.link.invoice_placeholder') }}</option>
                        @foreach ($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected((string) old('invoice_id') === (string) $invoice->id)>
                                {{ \App\Support\PaymentInvoiceLinkOptionFormatter::label($invoice) }}
                            </option>
                        @endforeach
                    </select>
                    @error('invoice_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <p class="text-muted small mb-0">{{ __('payment_invoice.link.hint') }}</p>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-link me-1"></i>{{ __('payment_invoice.link.submit') }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
