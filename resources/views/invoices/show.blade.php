@extends('layouts/layoutMaster')

@section('title', __('Invoice Detail'))

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/app-invoice.css')}}" />
<style>
  .payment-status-trigger {
    cursor: pointer;
    line-height: 1;
  }

  .payment-status-trigger:hover .badge {
    opacity: 0.85;
  }
</style>
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
    $showPendingBalance = round((float) $invoice->balance, 2) > 0
        && round((float) $invoice->balance, 2) < round((float) $invoice->total_amount, 2);
    $originalInvoice = $originalInvoice ?? null;
    $existingCreditNote = $existingCreditNote ?? null;
@endphp
<div class="row invoice-preview">
  <!-- Invoice -->
  <div class="col-xl-9 col-md-8 col-12 mb-md-0 mb-4">
    <div class="card invoice-preview-card">
      <div class="card-body">
        <div class="d-flex justify-content-between flex-xl-row flex-md-column flex-sm-row flex-column m-sm-3 m-0">
          <div class="mb-xl-0 mb-4">
            <div class="mb-4">
              <img
                src="{{ Helper::logoAsset('dark') }}?v={{ config('variables.templateVersion', '1') }}"
                alt="{{ config('app.name') }}"
                class="d-block"
                style="max-height: 3.25rem; width: auto; height: auto; max-width: 220px; object-fit: contain; object-position: left center;"
              >
            </div>
            <p class="mb-2">{{ auth()->user()->currentTeam->name }}</p>
            <p class="mb-0">{{ auth()->user()->email }}</p>
          </div>
          <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <h4 class="fw-medium mb-0">{{ __('Invoice') }} #{{ $invoice->number }}</h4>
              {!! $invoice->status_badge !!}
            </div>
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
              @php
                $lineGrossAmount = round((float) $lineItem['unit_price'] * (float) $lineItem['quantity'], 2);
              @endphp
              <tr>
                <td>
                  <span>{{ $lineItem['description'] }}</span>
                  @if(filled($lineItem['category'] ?? null))
                    <small class="text-muted d-block">{{ $lineItem['category'] }}</small>
                  @endif
                </td>
                <td class="text-center">{{ (float) $lineItem['quantity'] == (int) $lineItem['quantity'] ? (int) $lineItem['quantity'] : \App\Helpers\Helpers::formatDecimal($lineItem['quantity']) }}</td>
                <td class="text-end text-nowrap">{{ $formatAmount($lineGrossAmount) }}</td>
                <td class="text-end text-nowrap">
                  @if((float) $lineItem['discount'] > 0)
                    <span class="text-danger">-{{ $formatAmount($lineItem['discount']) }}</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="text-end text-nowrap fw-medium">{{ $formatAmount($lineItem['total']) }}</td>
              </tr>
              @endforeach
              <tr>
                <td colspan="2" class="border-0 text-end pe-3 pt-3">{{ __('Subtotal') }}:</td>
                <td class="text-end border-0 pt-3 fw-medium text-nowrap">{{ $formatAmount($invoice->gross_amount) }}</td>
                <td class="border-0 pt-3"></td>
                <td class="border-0 pt-3"></td>
              </tr>
              @if($invoice->discount > 0)
              <tr>
                <td colspan="2" class="border-0 text-end pe-3">{{ __('Discount') }}:</td>
                <td class="border-0"></td>
                <td class="text-end border-0 text-danger fw-medium text-nowrap">-{{ $formatAmount($invoice->discount) }}</td>
                <td class="border-0"></td>
              </tr>
              @endif
              @if($showPendingBalance)
              <tr>
                <td colspan="2" class="border-0 text-end pe-3">{{ __('Balance') }}:</td>
                <td class="border-0"></td>
                <td class="border-0"></td>
                <td class="text-end border-0 text-danger fw-medium text-nowrap">{{ $formatAmount($invoice->balance) }}</td>
              </tr>
              @endif
              <tr>
                <td colspan="2" class="border-0 text-end pe-3 pb-2 fw-medium">{{ __('Total') }}:</td>
                <td class="border-0 pb-2"></td>
                <td class="border-0 pb-2"></td>
                <td class="text-end border-0 pb-2 h6 fw-medium text-nowrap">{{ $formatAmount($invoice->total_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        @else
        <div class="alert alert-info mb-0">
          <div class="alert-body">
            {{ __('No items found for this invoice') }}
          </div>
        </div>
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
              <tr>
                <td colspan="2" class="border-0 text-end pe-3 pt-3">{{ __('Subtotal') }}:</td>
                <td class="text-end border-0 pt-3 fw-medium text-nowrap">{{ $formatAmount($invoice->gross_amount) }}</td>
                <td class="border-0 pt-3"></td>
                <td class="border-0 pt-3"></td>
              </tr>
              @if($invoice->discount > 0)
              <tr>
                <td colspan="2" class="border-0 text-end pe-3">{{ __('Discount') }}:</td>
                <td class="border-0"></td>
                <td class="text-end border-0 text-danger fw-medium text-nowrap">-{{ $formatAmount($invoice->discount) }}</td>
                <td class="border-0"></td>
              </tr>
              @endif
              @if($showPendingBalance)
              <tr>
                <td colspan="2" class="border-0 text-end pe-3">{{ __('Balance') }}:</td>
                <td class="border-0"></td>
                <td class="border-0"></td>
                <td class="text-end border-0 text-danger fw-medium text-nowrap">{{ $formatAmount($invoice->balance) }}</td>
              </tr>
              @endif
              <tr>
                <td colspan="2" class="border-0 text-end pe-3 pb-2 fw-medium">{{ __('Total') }}:</td>
                <td class="border-0 pb-2"></td>
                <td class="border-0 pb-2"></td>
                <td class="text-end border-0 pb-2 h6 fw-medium text-nowrap">{{ $formatAmount($invoice->total_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        @endif

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
    @if (session('error'))
      <div class="alert alert-warning alert-dismissible mb-3" role="alert">
        {{ session('error') }}
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
        @if ($originalInvoice)
        <a class="btn btn-label-primary d-grid w-100 mb-2" href="{{ route('invoice.show', $originalInvoice->id) }}">
          <i class="ti ti-file-invoice ti-xs me-2"></i>
          {{ __('invoice_credit_note.view_original') }}
          <span class="small fw-normal">#{{ $originalInvoice->number }}</span>
        </a>
        @endif
        @if ($existingCreditNote)
        <a class="btn btn-label-info d-grid w-100 mb-2" href="{{ route('invoice.show', $existingCreditNote->id) }}">
          <i class="ti ti-receipt-refund ti-xs me-2"></i>
          {{ __('invoice_credit_note.view_existing') }}
          <span class="small fw-normal">#{{ $existingCreditNote->number }}</span>
        </a>
        @elseif ($canShowCreditNoteForm)
        <button
          type="button"
          class="btn btn-label-info d-grid w-100 mb-2"
          data-bs-toggle="modal"
          data-bs-target="#creditNoteModal"
        >
          <i class="ti ti-receipt-refund ti-xs me-2"></i>
          {{ __('invoice_credit_note.create_title') }}
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

    @if ($fiscalPlatform)
    @php
      $fiscalPlatformLabel = ['cuentica' => 'Cuéntica', 'arca' => 'ARCA'][$fiscalPlatform] ?? ucfirst($fiscalPlatform);
      $fiscalExported = $fiscalExport && in_array($fiscalExport->status, ['exported', 'rectified'], true);
    @endphp
    <div class="card mt-3">
      <div class="card-body">
        <h6 class="mb-3">{{ __('Fiscal export') }}</h6>
        @if ($fiscalExported)
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-label-success">{{ __('Exported') }}</span>
            <span class="fw-medium">{{ $fiscalPlatformLabel }}</span>
          </div>
          @if ($fiscalExport->external_number)
          <p class="mb-1">
            <span class="text-muted">{{ __('Number') }}:</span>
            <span class="fw-medium">{{ $fiscalExport->external_number }}</span>
          </p>
          @endif
          @if ($fiscalExport->exported_at)
          <p class="mb-0 text-muted small">{{ \Carbon\Carbon::parse($fiscalExport->exported_at)->format('d-m-Y H:i') }}</p>
          @endif
        @else
          @if ($fiscalExport && $fiscalExport->status === 'failed' && $fiscalExport->error_message)
            <div class="alert alert-warning py-2 px-3 small mb-2" role="alert">{{ $fiscalExport->error_message }}</div>
          @endif
          @if ($canExportFiscal)
            <form method="POST" action="{{ route('invoice.fiscal-export', $invoice->id) }}">
              @csrf
              <button type="submit" class="btn btn-label-primary d-grid w-100">
                <i class="ti ti-file-export ti-xs me-2"></i>
                {{ __('Export to :platform', ['platform' => $fiscalPlatformLabel]) }}
              </button>
            </form>
          @else
            <p class="mb-0 text-muted small">{{ __('This invoice is not ready for fiscal export yet.') }}</p>
          @endif
        @endif
      </div>
    </div>
    @endif
    @unless ($invoice->isCreditNote())
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
              @if($canUpdatePaymentStatus && ! empty($paymentDetail['id']))
                <button
                  type="button"
                  class="btn btn-sm p-0 border-0 bg-transparent payment-status-trigger"
                  data-bs-toggle="modal"
                  data-bs-target="#paymentStatusModal"
                  data-payment-id="{{ $paymentDetail['id'] }}"
                  data-payment-status="{{ $paymentDetail['status'] }}"
                  title="{{ __('payment_status.change_title') }}"
                >
                  {!! $paymentDetail['status_html'] !!}
                </button>
              @elseif($paymentDetail['status_html'])
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
    @if ($canRegisterPayment && $paymentFormDefaults && ! empty($paymentFormDefaults['accounts']))
    <div class="card mt-3">
      <div class="card-body">
        <h6 class="mb-3">{{ __('invoice_payment.register_title') }}</h6>
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
      </div>
    </div>
    @endif
    @endunless
  </div>
  <!-- /Invoice Actions -->
</div>

@if ($canUpdatePaymentStatus)
<div class="modal fade" id="paymentStatusModal" tabindex="-1" aria-labelledby="paymentStatusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="paymentStatusForm" method="POST">
        @csrf
        @method('PATCH')
        <div class="modal-header">
          <h5 class="modal-title" id="paymentStatusModalLabel">
            <i class="ti ti-status-change me-2"></i>{{ __('payment_status.modal_title') }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="modal-body">
          <label for="payment_status" class="form-label">{{ __('payment_status.status') }}</label>
          <select
            id="payment_status"
            name="status"
            class="form-select @error('status') is-invalid @enderror"
            required
          >
            @foreach ($paymentStatusOptions as $statusValue => $statusLabel)
              <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
            @endforeach
          </select>
          @error('status')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
            {{ __('Cancel') }}
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-check me-1"></i>{{ __('payment_status.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

@if ($canShowCreditNoteForm && ! $existingCreditNote)
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
            <i class="ti ti-receipt-refund me-2"></i>{{ __('invoice_credit_note.create_title') }}
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

@if ($canUpdatePaymentStatus ?? false)
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('paymentStatusModal');
    const form = document.getElementById('paymentStatusForm');
    const statusSelect = document.getElementById('payment_status');
    const updateStatusUrlTemplate = @json(route('payments.update-status', ['payment' => '__PAYMENT__']));

    if (! modalElement || ! form || ! statusSelect) {
        return;
    }

    document.querySelectorAll('.payment-status-trigger').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            const paymentId = trigger.getAttribute('data-payment-id');
            const paymentStatus = trigger.getAttribute('data-payment-status');

            form.action = updateStatusUrlTemplate.replace('__PAYMENT__', paymentId);
            statusSelect.value = paymentStatus;
        });
    });

    @if ($errors->has('status'))
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
    @endif
});
@endif
</script>
@endsection
@endsection
