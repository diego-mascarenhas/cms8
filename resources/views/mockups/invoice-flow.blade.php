@extends('layouts/layoutManual')

@section('title', __($mockup['title']))

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="card-title mb-1">{{ __($mockup['title']) }}</h4>
            <p class="text-muted mb-0">{{ __($mockup['description']) }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('manual.billing') }}" class="btn btn-label-primary btn-sm">{{ __('Manual: Facturas') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-primary" role="alert">
            <span class="badge bg-primary me-1">Admin</span>
            {{ __('Solo administradores (gate access-billing-modules).') }}
        </div>

        <x-manual.flowchart :nodes="[
            ['shape' => 'terminal', 'label' => 'Inicio'],
            ['shape' => 'process', 'label' => 'Cliente / empresa con datos fiscales', 'role' => 'admin'],
            ['shape' => 'process', 'label' => 'Proyecto o pedido con importe', 'role' => 'admin'],
            ['shape' => 'process', 'label' => 'Crear factura (líneas, IVA, PDF)', 'role' => 'admin'],
            ['shape' => 'decision', 'label' => '¿Cómo se cobra?', 'branches' => [
                ['when' => 'Manual', 'label' => 'Registrar pago en Pagos', 'role' => 'admin'],
                ['when' => 'Pasarela', 'label' => 'Sync Stripe / MercadoPago', 'role' => 'admin'],
            ]],
            ['shape' => 'process', 'label' => 'Conciliar en Finanzas', 'role' => 'admin'],
            ['shape' => 'terminal', 'label' => 'Cobro registrado'],
        ]" />

        <x-manual.role-compare section="billing" />

        <div class="card border border-primary">
            <div class="card-header">{{ __('Campos típicos de una factura') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Cliente / empresa" type="select" required sample="Acme SL" admin-only />
                    <x-manual.mock-field label="Fecha" type="date" admin-only />
                    <x-manual.mock-field label="Concepto línea" sample="Desarrollo web — fase 1" admin-only col="col-md-6" />
                    <x-manual.mock-field label="Cantidad" type="number" sample="1" admin-only col="col-md-2" />
                    <x-manual.mock-field label="Precio" type="number" sample="5000" admin-only col="col-md-2" />
                    <x-manual.mock-field label="IVA %" type="number" sample="21" admin-only col="col-md-2" />
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
