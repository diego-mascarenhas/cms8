@extends('layouts/layoutMaster')

@section('title', __('Invoice Detail'))

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/app-invoice.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('content')
@php
    $currencyCode = $invoice->currency_code;
    $formatAmount = fn (float|int|null $amount): string => \App\Helpers\Helpers::formatDecimal($amount).' '.$currencyCode;
    $formatPaymentAmount = fn (float $amount, string $code): string => \App\Helpers\Helpers::formatDecimal($amount).' '.strtoupper($code);
    $invoicePrintUrl = $invoice->stripeHostedInvoiceUrl();
    $invoiceDownloadUrl = $invoice->stripeInvoicePdfUrl();
@endphp
<div class="row invoice-preview">
  <!-- Invoice -->
  <div class="col-xl-9 col-md-8 col-12 mb-md-0 mb-4">
    <div class="card invoice-preview-card">
      <div class="card-body">
        <div class="d-flex justify-content-between flex-xl-row flex-md-column flex-sm-row flex-column m-sm-3 m-0">
          <div class="mb-xl-0 mb-4">
            <div class="d-flex svg-illustration mb-4 gap-2 align-items-center">
              <span class="app-brand-logo demo">@include('_partials.macros',["height"=>22,"withbg"=>''])</span>
              <span class="app-brand-text fw-bold fs-4">{{ config('app.name') }}</span>
            </div>
            <p class="mb-2">{{ auth()->user()->currentTeam->name }}</p>
            <p class="mb-0">{{ auth()->user()->email }}</p>
          </div>
          <div>
            <h4 class="fw-medium mb-2">{{ __('Invoice') }} #{{ $invoice->number }}</h4>
            <div class="mb-2 pt-1">
              <span>{{ __('Date') }}:</span>
              <span class="fw-medium">{{ \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') }}</span>
            </div>
            @if($invoice->due_date)
            <div class="mb-2 pt-1">
              <span>{{ __('Due Date') }}:</span>
              <span class="fw-medium">{{ \Carbon\Carbon::parse($invoice->due_date)->format('d-m-Y') }}</span>
            </div>
            @endif
            <div class="pt-1">
              <span>{{ __('Operation') }}:</span>
              <span class="fw-medium">{{ $invoice->operation === 'sell' ? __('Sale') : __('Purchase') }}</span>
            </div>
          </div>
        </div>

        <hr class="my-4 mx-n4" />

        <div class="row p-sm-3 p-0">
          <div class="col-12 mb-xl-0 mb-md-4 mb-sm-0 mb-4">
            <h6 class="mb-3">{{ __('Invoice To') }}:</h6>
            @if($invoice->enterprise)
            <p class="mb-1 fw-medium">{{ $invoice->enterprise->name }}</p>
            @if($invoice->enterprise->address)
            <p class="mb-1">{{ $invoice->enterprise->address }}</p>
            @if($invoice->enterprise->locality || $invoice->enterprise->postal_code)
            <p class="mb-1">{{ $invoice->enterprise->locality }} {{ $invoice->enterprise->postal_code }}</p>
            @endif
            @endif
            @if($invoice->enterprise->phone)
            <p class="mb-1">{{ $invoice->enterprise->phone }}</p>
            @endif
            @if($invoice->enterprise->email)
            <p class="mb-0">{{ $invoice->enterprise->email }}</p>
            @endif
            @else
            <p class="mb-0 text-muted">{{ __('No enterprise assigned') }}</p>
            @endif
          </div>
        </div>

        <hr class="my-4 mx-n4" />

        @if($displayLineItems->isNotEmpty())
        <div class="table-responsive border-top">
          <table class="table m-0">
            <thead>
              <tr>
                <th>{{ __('Description') }}</th>
                <th class="text-center">{{ __('Quantity') }}</th>
                <th class="text-end">{{ __('Price') }}</th>
                <th class="text-end">{{ __('Discount') }}</th>
                <th class="text-end">{{ __('Total') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($displayLineItems as $lineItem)
              <tr>
                <td>{{ $lineItem['description'] }}</td>
                <td class="text-center">{{ (float) $lineItem['quantity'] == (int) $lineItem['quantity'] ? (int) $lineItem['quantity'] : \App\Helpers\Helpers::formatDecimal($lineItem['quantity']) }}</td>
                <td class="text-end">{{ $formatAmount($lineItem['unit_price']) }}</td>
                <td class="text-end">{{ $formatAmount($lineItem['discount']) }}</td>
                <td class="text-end">{{ $formatAmount($lineItem['total']) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div class="alert alert-info mb-0">
          <div class="alert-body">
            {{ __('No items found for this invoice') }}
          </div>
        </div>
        @endif

        <div class="row">
          <div class="col-12">
            <hr class="mt-4 mb-3" />
            <div class="row">
              <div class="col-lg-9 col-md-8"></div>
              <div class="col-lg-3 col-md-4">
                <table class="w-100">
                  <tbody>
                    <tr>
                      <td class="pe-3">{{ __('Subtotal') }}:</td>
                      <td class="text-end fw-medium">{{ $formatAmount($invoice->gross_amount) }}</td>
                    </tr>
                    @if($invoice->discount > 0)
                    <tr>
                      <td class="pe-3">{{ __('Discount') }}:</td>
                      <td class="text-end text-danger">-{{ $formatAmount($invoice->discount) }}</td>
                    </tr>
                    @endif
                    <tr>
                      <td class="pe-3">{{ __('Balance') }}:</td>
                      <td @class(['text-end fw-medium', 'text-danger' => (float) $invoice->balance > 0])>{{ $formatAmount($invoice->balance) }}</td>
                    </tr>
                    <tr>
                      <td class="border-top-0 pe-3">
                        <h6 class="mb-0">{{ __('Total') }}:</h6>
                      </td>
                      <td class="border-top-0 text-end">
                        <h6 class="mb-0 fw-medium">{{ $formatAmount($invoice->total_amount) }}</h6>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <hr class="my-4" />

        <div class="row">
          <div class="col-12">
            <span class="fw-medium">{{ __('Note') }}:</span>
            <span>{{ __('Invoice auto-generated from imported data') }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Invoice -->

  <!-- Invoice Actions -->
  <div class="col-xl-3 col-md-4 col-12 invoice-actions">
    @if (session('success'))
      <div class="alert alert-success alert-dismissible mb-3" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
    <div class="card">
      <div class="card-body">
        <a class="btn btn-primary d-grid w-100 mb-2" href="{{ route('invoice.index') }}">
          <i class="ti ti-arrow-left ti-xs me-2"></i>
          {{ __('Back to List') }}
        </a>
        @if ($invoicePrintUrl)
        <a class="btn btn-label-secondary d-grid w-100 mb-2" target="_blank" rel="noopener noreferrer" href="{{ $invoicePrintUrl }}">
          <i class="ti ti-printer ti-xs me-2"></i>
          {{ __('Print') }}
        </a>
        @endif
        @if ($invoiceDownloadUrl)
        <a class="btn btn-label-secondary d-grid w-100 mb-2" target="_blank" rel="noopener noreferrer" href="{{ $invoiceDownloadUrl }}">
          <i class="ti ti-download ti-xs me-2"></i>
          {{ __('Download') }}
        </a>
        @endif
        @if ($canShowCreditNoteForm)
        <button
          type="button"
          class="btn btn-label-info d-grid w-100 mb-2"
          data-bs-toggle="modal"
          data-bs-target="#creditNoteModal"
        >
          <i class="ti ti-receipt-refund ti-xs me-2"></i>
          {{ __('invoice_credit_note.issue_title') }}
        </button>
        @endif
        @can('invoice.edit')
        <a class="btn btn-label-secondary d-grid w-100 mb-2" href="#">
          <i class="ti ti-edit ti-xs me-2"></i>
          {{ __('Edit Invoice') }}
        </a>
        @endcan
        @can('invoice.destroy')
        <button class="btn btn-danger d-grid w-100" onclick="deleteInvoice({{ $invoice->id }})">
          <i class="ti ti-trash ti-xs me-2"></i>
          {{ __('Delete Invoice') }}
        </button>
        @endcan
      </div>
    </div>
    <div class="card mt-3">
      <div class="card-body">
        <h6>{{ __('Status') }}</h6>
        <div class="d-flex justify-content-between align-items-center">
          {!! $invoice->status_badge !!}
        </div>
      </div>
    </div>
    <div class="card mt-3">
      <div class="card-body">
        <h6 class="mb-3">{{ __('Payments') }}</h6>
        @if (session('success'))
          <div class="alert alert-success alert-dismissible mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif
        @forelse($paymentDetails as $paymentDetail)
          <div @class(['mb-0' => $loop->last, 'mb-3 pb-3 border-bottom' => ! $loop->last])>
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <span class="fw-medium">{{ \Carbon\Carbon::parse($paymentDetail['date'])->format('d-m-Y') }}</span>
              @if($paymentDetail['status_html'])
                {!! $paymentDetail['status_html'] !!}
              @endif
            </div>
            <p @class(['mb-2 fw-medium', 'text-success' => $paymentDetail['is_income'], 'text-danger' => ! $paymentDetail['is_income']])>
              {{ $paymentDetail['is_income'] ? '+' : '-' }}{{ $formatPaymentAmount($paymentDetail['amount'], $paymentDetail['currency_code']) }}
            </p>
            @if($paymentDetail['method'])
            <p class="mb-1">
              <span class="text-muted">{{ __('Type') }}:</span>
              <span class="fw-medium">{{ $paymentDetail['method'] }}</span>
            </p>
            @endif
            @if($paymentDetail['account'])
            <p class="mb-0">
              <span class="text-muted">{{ __('Account') }}:</span>
              <span class="fw-medium">{{ $paymentDetail['account'] }}</span>
            </p>
            @endif
          </div>
        @empty
          <p class="mb-0 text-muted">{{ __('No payments linked to this invoice') }}</p>
        @endforelse
      </div>
    </div>
    @if ($canRegisterPayment && $paymentFormDefaults)
    <div class="card mt-3">
      <div class="card-body">
        <h6 class="mb-3">{{ __('invoice_payment.register_title') }}</h6>
        @if (empty($paymentFormDefaults['accounts']))
          <p class="mb-0 text-muted">{{ __('invoice_payment.no_accounts', ['currency' => $paymentFormDefaults['currency_code']]) }}</p>
        @else
          <form action="{{ route('invoice.payments.store', $invoice) }}" method="POST" class="row g-3">
            @csrf
            <div class="col-12">
              <label for="amount" class="form-label">{{ __('invoice_payment.amount') }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  max="{{ $paymentFormDefaults['amount'] }}"
                  name="amount"
                  id="amount"
                  class="form-control @error('amount') is-invalid @enderror"
                  value="{{ old('amount', $paymentFormDefaults['amount']) }}"
                  required
                >
                <span class="input-group-text">{{ $paymentFormDefaults['currency_code'] }}</span>
              </div>
              @error('amount')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-12">
              <x-input-date
                id="date"
                label="{{ __('invoice_payment.date') }} (*)"
                value="{{ old('date', $paymentFormDefaults['date']) }}"
              />
            </div>
            <div class="col-12">
              <x-input-select
                id="account_id"
                label="{{ __('invoice_payment.account') }}"
                :options="$paymentFormDefaults['accounts']"
                value="{{ old('account_id', $paymentFormDefaults['account_id']) }}"
                placeholder="{{ __('Select') }}"
                required
              />
            </div>
            <div class="col-12">
              <x-input-select
                id="type_id"
                label="{{ __('invoice_payment.type') }}"
                :options="$paymentFormDefaults['payment_types']"
                value="{{ old('type_id', $paymentFormDefaults['type_id']) }}"
                placeholder="{{ __('Select') }}"
                required
              />
            </div>
            <div class="col-12">
              <x-input-textarea
                id="remarks"
                label="{{ __('invoice_payment.remarks') }}"
                value="{{ old('remarks', '') }}"
              />
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary w-100">
                <i class="ti ti-cash me-1"></i>{{ __('invoice_payment.submit') }}
              </button>
            </div>
          </form>
        @endif
      </div>
    </div>
    @endif
  </div>
  <!-- /Invoice Actions -->
</div>

@if ($canShowCreditNoteForm)
<div class="modal fade" id="creditNoteModal" tabindex="-1" aria-labelledby="creditNoteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form
        action="{{ route('invoice.credit-notes.store', $invoice) }}"
        method="POST"
        onsubmit="return confirm(@json(__('invoice_credit_note.confirm')))"
      >
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="creditNoteModalLabel">
            <i class="ti ti-receipt-refund me-2"></i>{{ __('invoice_credit_note.issue_title') }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="modal-body">
          @unless ($canIssueCreditNote)
            <div class="alert alert-warning mb-3" role="alert">
              {{ __('invoice_credit_note.errors.stripe_not_configured') }}
            </div>
          @endunless
          <label for="credit_note_reason" class="form-label">{{ __('invoice_credit_note.reason') }}</label>
          <select
            id="credit_note_reason"
            name="reason"
            class="form-select @error('reason') is-invalid @enderror"
            @disabled(! $canIssueCreditNote)
            required
          >
            @foreach ($creditNoteReasons as $reason)
              <option value="{{ $reason }}" @selected(old('reason', $defaultCreditNoteReason) === $reason)>
                {{ __('invoice_credit_note.reasons.'.$reason) }}
              </option>
            @endforeach
          </select>
          @error('reason')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
            {{ __('Cancel') }}
          </button>
          <button type="submit" class="btn btn-primary" @disabled(! $canIssueCreditNote)>
            <i class="ti ti-receipt-refund me-1"></i>{{ __('invoice_credit_note.issue_button') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

@section('page-script')
<script>
function deleteInvoice(id) {
    if (confirm('{{ __("Are you sure you want to delete this invoice?") }}')) {
        fetch(`/invoice/destroy/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            }
        }).then(response => {
            if (response.ok) {
                window.location.href = '{{ route("invoice.index") }}';
            }
        });
    }
}

@if ($errors->has('reason') && ($canShowCreditNoteForm ?? false))
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('creditNoteModal');

    if (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
@endif
</script>
@endsection
@endsection
