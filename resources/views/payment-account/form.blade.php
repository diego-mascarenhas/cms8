@extends('layouts/layoutMaster')

@section('title', isset($data->id) ? 'Editar cuenta de pago' : 'Crear cuenta de pago')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">Cuentas de pago/</span>
            {{ isset($data->id) ? 'Editar' : 'Crear' }}
        </h4>
        <p class="text-muted">Define la moneda de la cuenta y qué formas de pago acepta.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('payment-account.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
    </div>
</div>

<div class="card mb-4">
    <form
        class="card-body"
        method="POST"
        action="{{ isset($data->id) ? route('payment-account.update', $data) : route('payment-account.store') }}"
        novalidate
    >
        @csrf
        @if (isset($data->id))
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Nombre (*)</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $data->name ?? '') }}"
                >
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="code" class="form-label">Código (*)</label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    class="form-control @error('code') is-invalid @enderror"
                    value="{{ old('code', $data->code ?? '') }}"
                    maxlength="10"
                >
                @error('code')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="currency_id" class="form-label">Moneda (*)</label>
                <select id="currency_id" name="currency_id" class="form-select select2 @error('currency_id') is-invalid @enderror">
                    <option value="">Selecciona moneda</option>
                    @foreach ($currencies as $currency)
                        <option
                            value="{{ $currency->id }}"
                            {{ (string) old('currency_id', $data->currency_id ?? '') === (string) $currency->id ? 'selected' : '' }}
                        >
                            {{ $currency->code }} - {{ $currency->name }}
                        </option>
                    @endforeach
                </select>
                @error('currency_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-9">
                <label for="payment_type_ids" class="form-label">Formas de pago aceptadas (*)</label>
                <select
                    id="payment_type_ids"
                    name="payment_type_ids[]"
                    class="form-select select2 @error('payment_type_ids') is-invalid @enderror @error('payment_type_ids.*') is-invalid @enderror"
                    multiple
                >
                    @foreach ($paymentTypes as $paymentType)
                        <option
                            value="{{ $paymentType->id }}"
                            {{ in_array((string) $paymentType->id, array_map('strval', $selectedPaymentTypeIds), true) ? 'selected' : '' }}
                        >
                            {{ $paymentType->display_name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Ejemplo: una caja en efectivo solo acepta efectivo; PayPal solo acepta PayPal.</div>
                @error('payment_type_ids')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                @error('payment_type_ids.*')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label">Estado (*)</label>
                <select id="status" name="status" class="form-select select2 @error('status') is-invalid @enderror">
                    <option value="1" {{ (string) old('status', $data->status ?? 1) === '1' ? 'selected' : '' }}>Activa</option>
                    <option value="0" {{ (string) old('status', $data->status ?? 1) === '0' ? 'selected' : '' }}>Inactiva</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
            <a href="{{ route('payment-account.index') }}" class="btn btn-label-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@section('page-script')
<script>
    $(function () {
        $('.select2').each(function () {
            var $this = $(this);
            $this.wrap('<div class="position-relative"></div>');
            $this.select2({
                dropdownParent: $this.parent(),
                width: '100%',
                placeholder: $this.prop('multiple') ? 'Selecciona formas de pago' : 'Selecciona una opción',
                minimumResultsForSearch: $this.is('#status') ? Infinity : 0,
            });
        });
    });
</script>
@endsection
