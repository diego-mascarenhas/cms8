@extends('layouts/layoutManual')

@section('title', __('Facturas y pagos'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">{{ __('Facturas y pagos') }}</h4>
        <a href="{{ route('mockups.invoice-flow') }}" class="btn btn-sm btn-label-primary">
            <i class="ti ti-chart-arrows me-1"></i>{{ __('Mockup del flujo') }}
        </a>
    </div>
    <div class="card-body">
        <div class="alert alert-primary" role="alert">
            <span class="badge bg-primary me-1">Admin</span>
            {{ __('Toda esta sección del menú requiere el permiso de facturación (solo Admin / Root).') }}
        </div>

        <h5>{{ __('Facturas') }}</h5>
        <p>{{ __('Crea y gestiona facturas: líneas, cliente, IVA, PDF y envío por email.') }}</p>

        <h5 class="mt-4">{{ __('Pagos') }}</h5>
        <p>{{ __('Registra cobros vinculados a facturas o sincroniza pasarelas (Stripe, MercadoPago, etc.).') }}</p>

        <h5 class="mt-4">{{ __('Ingresos, gastos, tarifas y finanzas') }}</h5>
        <p>{{ __('Tesorería, tarifas de precio y panel financiero con totales y tendencias.') }}</p>

        <x-manual.role-compare section="billing" />
    </div>
</div>
@endsection
