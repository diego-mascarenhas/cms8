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
            <a href="{{ route('manual.clients') }}" class="btn btn-label-primary btn-sm">{{ __('Manual: Clientes') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <x-manual.role-compare section="clients" />

        <div class="card border">
            <div class="card-header">{{ __('Ficha de cliente (mockup)') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Nombre / razón social" required sample="Cliente Demo SL" />
                    <x-manual.mock-field label="CIF / NIF" sample="B87654321" />
                    <x-manual.mock-field label="Email" type="email" sample="facturacion@cliente.example" />
                    <x-manual.mock-field label="Teléfono" type="tel" sample="+34 910 222 333" />
                    <x-manual.mock-field label="Contacto principal" type="select" sample="María García" />
                    <x-manual.mock-field label="Estado" type="select" sample="Activo" />
                    <x-manual.mock-field label="Dirección" sample="Av. Principal 10" col="col-md-8" />
                    <x-manual.mock-field label="Código postal" sample="08001" col="col-md-4" />
                    <x-manual.mock-field label="Notas" type="textarea" col="col-12" sample="Condiciones comerciales, SLA…" />
                </div>
                <div class="pt-4 d-flex gap-2">
                    <button type="button" class="btn btn-primary" disabled>{{ __('Guardar') }}</button>
                    <button type="button" class="btn btn-label-secondary" disabled>{{ __('Cancelar') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
