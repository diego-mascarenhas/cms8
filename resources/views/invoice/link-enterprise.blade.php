@extends('layouts/layoutMaster')

@section('title', __('invoice_enterprise.link.title'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('invoice_enterprise.link.title') }}</h4>
        <p class="text-muted">{{ __('invoice_enterprise.link.subtitle') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('invoice.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('invoice_enterprise.link.back') }}
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
            <dt class="col-sm-3">{{ __('invoice_enterprise.link.invoice_number') }}</dt>
            <dd class="col-sm-9">{{ $invoice->number ?? '—' }}</dd>
            <dt class="col-sm-3">{{ __('invoice_enterprise.link.invoice_date') }}</dt>
            <dd class="col-sm-9">@if($invoice->date){{ \Illuminate\Support\Carbon::parse($invoice->date)->format('d/m/Y') }}@else—@endif</dd>
        </dl>

        @if ($enterprises->isEmpty())
            <p class="text-muted mb-0">{{ __('invoice_enterprise.link.no_clients') }}</p>
        @else
            <form action="{{ route('invoice.link-enterprise.store', $invoice) }}" method="POST" class="row g-3">
                @csrf
                <div class="col-12 col-md-10">
                    <label for="enterprise_id" class="form-label">{{ __('invoice_enterprise.link.client_label') }}</label>
                    <select name="enterprise_id" id="enterprise_id" class="form-select @error('enterprise_id') is-invalid @enderror" required>
                        <option value="">{{ __('invoice_enterprise.link.client_placeholder') }}</option>
                        @foreach ($enterprises as $enterprise)
                            <option value="{{ $enterprise->id }}" @selected((string) old('enterprise_id') === (string) $enterprise->id)>
                                {{ $enterprise->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('enterprise_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <p class="text-muted small mb-0">{{ __('invoice_enterprise.link.hint') }}</p>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-link me-1"></i>{{ __('invoice_enterprise.link.submit') }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
