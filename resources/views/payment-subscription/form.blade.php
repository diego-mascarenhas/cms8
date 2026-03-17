@extends('layouts/layoutMaster')

@section('title', $data ? __('Editar forma de pago') : __('Crear forma de pago'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Formas de pago') }}/</span> {{ $data ? __('Editar') : __('Crear') }}</h4>
        <p class="text-muted">{{ __('Stripe, PayPal, Mercado Pago o facturación local') }}</p>
    </div>
    @if ($data)
    <div class="d-flex align-content-center flex-wrap gap-3">
        <form action="{{ route('payment-subscription.destroy', $data->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('¿Eliminar esta forma de pago?') }}');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger waves-effect waves-light"><i class="ti ti-trash me-1"></i>{{ __('Eliminar') }}</button>
        </form>
    </div>
    @endif
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ $data ? __('Editar forma de pago') : __('Nueva forma de pago') }}</h5>
    <form class="card-body" action="{{ $data ? route('payment-subscription.update', $data->id) : route('payment-subscription.store') }}" method="POST">
        @csrf
        @if ($data)
            @method('PUT')
        @endif
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">{{ __('Nombre') }} (*)</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $data->name ?? '') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="provider" class="form-label">{{ __('Proveedor') }} (*)</label>
                <select id="provider" name="provider" class="form-select @error('provider') is-invalid @enderror" required>
                    @foreach ($providers as $value => $label)
                        <option value="{{ $value }}" {{ old('provider', $data->provider ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('provider')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="status" class="form-label">{{ __('Estado') }} (*)</label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" {{ old('status', $data->status ?? 'active') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="external_id" class="form-label">{{ __('ID externo') }}</label>
                <input type="text" id="external_id" name="external_id" class="form-control @error('external_id') is-invalid @enderror" value="{{ old('external_id', $data->external_id ?? '') }}" placeholder="{{ __('Opcional') }}">
                @error('external_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">{{ __('Ej. ID de suscripción en Stripe') }}</small>
            </div>
        </div>
        <div class="pt-4">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Guardar') }}</button>
            <a href="{{ route('payment-subscription.index') }}" class="btn btn-label-secondary">{{ __('Cancelar') }}</a>
        </div>
    </form>
</div>
@endsection
