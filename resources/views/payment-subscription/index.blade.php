@extends('layouts/layoutMaster')

@section('title', __('Formas de pago'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Formas de pago') }}</h4>
        <p class="text-muted">{{ __('Stripe, PayPal, Mercado Pago o facturación local. Se asocian a los servicios.') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('payment-subscription.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>{{ __('Crear forma de pago') }}</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        @if ($items->isEmpty())
            <p class="text-muted mb-0">{{ __('No hay formas de pago.') }} <a href="{{ route('payment-subscription.create') }}">{{ __('Crear la primera') }}</a>.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Nombre') }}</th>
                            <th>{{ __('Proveedor') }}</th>
                            <th>{{ __('Estado') }}</th>
                            <th>{{ __('ID externo') }}</th>
                            <th class="text-end">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $providers[$item->provider] ?? $item->provider }}</td>
                                <td><span class="badge bg-label-{{ $item->status === 'active' ? 'success' : ($item->status === 'canceled' || $item->status === 'expired' ? 'secondary' : 'info') }}">{{ $statuses[$item->status] ?? $item->status }}</span></td>
                                <td><span class="text-muted">{{ $item->external_id ?: '—' }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('payment-subscription.edit', $item->id) }}" class="btn btn-sm btn-icon btn-text-secondary" title="{{ __('Editar') }}"><i class="ti ti-edit ti-sm"></i></a>
                                    <form action="{{ route('payment-subscription.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('¿Eliminar esta forma de pago?') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="{{ __('Eliminar') }}"><i class="ti ti-trash ti-sm"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
