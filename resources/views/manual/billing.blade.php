@extends('layouts/layoutManual')

@section('title', __('Facturas y pagos'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">{{ __('Facturas y pagos') }}</h4>
        <a href="{{ route('mockups.invoice-flow') }}" class="btn btn-sm btn-label-primary">
            <i class="ti ti-git-fork me-1"></i>{{ __('Diagrama de flujo') }}
        </a>
    </div>
    <div class="card-body">
        <div class="alert alert-primary" role="alert">
            <span class="badge bg-primary me-1">Admin</span>
            {{ __('Toda esta sección del menú requiere el permiso de facturación (solo Admin / Root).') }}
        </div>

        <h5>{{ __('Facturas') }}</h5>
        <p>{{ __('Crea facturas con líneas, cliente, IVA, PDF y envío por email.') }}</p>

        <h5 class="mt-4">{{ __('Pagos') }}</h5>
        <p>{{ __('Registra cobros o sincroniza pasarelas (Stripe, MercadoPago, etc.).') }}</p>
        <p class="mb-0">
            <a href="{{ \App\Support\GuidePresentation::url('facturacion') }}" class="btn btn-sm btn-label-primary" target="_blank" rel="noopener">
                <i class="ti ti-presentation me-1"></i>{{ __('Presentación facturación') }}
            </a>
            <a href="{{ route('help.stripe-webhook') }}" class="btn btn-sm btn-label-secondary">{{ __('Webhooks Stripe') }}</a>
        </p>

        <h5 class="mt-4">{{ __('Ingresos, gastos, tarifas y finanzas') }}</h5>
        <p>{{ __('Tesorería, tarifas de precio y panel financiero con totales y tendencias.') }}</p>

        <h5 class="mt-4">{{ __('Suscripciones') }}</h5>
        <p>{{ __('Gestión de suscripciones de clientes (módulo Billing del menú), distinta de la suscripción al plan de Humano.') }}</p>

        <h5 class="mt-4">{{ __('Afiliados') }}</h5>
        <p>{{ __('Programa de referidos: comparte enlace o invita por email y cobra comisión por suscripciones asociadas a tu código.') }}</p>
        <p class="mb-0">
            <a href="{{ \App\Support\GuidePresentation::url('afiliados') }}" class="btn btn-sm btn-label-primary" target="_blank" rel="noopener">
                <i class="ti ti-presentation me-1"></i>{{ __('Presentación afiliados') }}
            </a>
        </p>

        <h5 class="mt-4">{{ __('Empresas (billing)') }}</h5>
        <p>{{ __('Fichas de empresa usadas en facturación (datos fiscales). Relacionadas con clientes/contactos del CRM.') }}</p>

        <x-manual.role-compare section="billing" />
    </div>
</div>
@endsection
