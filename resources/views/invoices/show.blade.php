@extends('layouts/layoutMaster')

@section('title', __('Invoice Detail'))

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/app-invoice.css')}}" />
@endsection

@section('content')
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
          <div class="col-xl-6 col-md-12 col-sm-5 col-12 mb-xl-0 mb-md-4 mb-sm-0 mb-4">
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
          <div class="col-xl-6 col-md-12 col-sm-7 col-12">
            <h6 class="mb-4">{{ __('Payment Details') }}:</h6>
            <table>
              <tbody>
                <tr>
                  <td class="pe-4">{{ __('Total Amount') }}:</td>
                  <td class="fw-medium">${{ number_format($invoice->total_amount, 2) }}</td>
                </tr>
                <tr>
                  <td class="pe-4">{{ __('Gross Amount') }}:</td>
                  <td>${{ number_format($invoice->gross_amount, 2) }}</td>
                </tr>
                @if($invoice->discount > 0)
                <tr>
                  <td class="pe-4">{{ __('Discount') }}:</td>
                  <td>${{ number_format($invoice->discount, 2) }}</td>
                </tr>
                @endif
                <tr>
                  <td class="pe-4">{{ __('Balance') }}:</td>
                  <td class="fw-medium text-danger">${{ number_format($invoice->balance, 2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <hr class="my-4 mx-n4" />

        @if($invoice->items && $invoice->items->count() > 0)
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
              @foreach($invoice->items as $item)
              <tr>
                <td>{{ $item->description ?? ($item->category?->name ?? '-') }}</td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-end">${{ number_format($item->unit_price, 2) }}</td>
                <td class="text-end">${{ number_format($item->discount, 2) }}</td>
                <td class="text-end">${{ number_format(($item->unit_price * $item->quantity) - $item->discount, 2) }}</td>
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
                      <td class="text-end fw-medium">${{ number_format($invoice->gross_amount, 2) }}</td>
                    </tr>
                    @if($invoice->discount > 0)
                    <tr>
                      <td class="pe-3">{{ __('Discount') }}:</td>
                      <td class="text-end text-danger">-${{ number_format($invoice->discount, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                      <td class="border-top-0 pe-3">
                        <h6 class="mb-0">{{ __('Total') }}:</h6>
                      </td>
                      <td class="border-top-0 text-end">
                        <h6 class="mb-0 fw-medium">${{ number_format($invoice->total_amount, 2) }}</h6>
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
    <div class="card">
      <div class="card-body">
        <a class="btn btn-primary d-grid w-100 mb-2" href="{{ route('invoices.index') }}">
          <i class="ti ti-arrow-left ti-xs me-2"></i>
          {{ __('Back to List') }}
        </a>
        <a class="btn btn-label-secondary d-grid w-100 mb-2" target="_blank" href="#">
          <i class="ti ti-printer ti-xs me-2"></i>
          {{ __('Print') }}
        </a>
        <a class="btn btn-label-secondary d-grid w-100 mb-2" target="_blank" href="#">
          <i class="ti ti-download ti-xs me-2"></i>
          {{ __('Download') }}
        </a>
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
          @php
            $statusColor = match($invoice->status) {
              1 => 'bg-label-primary',
              2 => 'bg-label-warning',
              3 => 'bg-label-danger',
              4 => 'bg-label-info',
              5 => 'bg-label-success',
              6 => 'bg-label-success',
              7 => 'bg-label-danger',
              8 => 'bg-label-warning',
              default => 'bg-label-secondary',
            };
          @endphp
          <span class="badge {{ $statusColor }} rounded-pill">
            {{ $invoice->status_label }}
          </span>
        </div>
      </div>
    </div>
  </div>
  <!-- /Invoice Actions -->
</div>

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
                window.location.href = '{{ route("invoices.index") }}';
            }
        });
    }
}
</script>
@endsection
@endsection
