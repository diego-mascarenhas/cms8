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
            <a href="{{ route('manual.services') }}" class="btn btn-label-primary btn-sm">{{ __('Manual: Servicios') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <x-manual.role-compare section="services" />

        <div class="card border">
            <div class="card-header">{{ __('Servicio del catálogo') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Nombre" required sample="Diseño UI" />
                    <x-manual.mock-field label="Categoría" type="select" sample="Diseño" />
                    <x-manual.mock-field label="Responsable" type="select" sample="Colaborador" />
                    <x-manual.mock-field label="Estado" type="select" sample="Activo" />
                    <x-manual.mock-field label="Descripción" type="textarea" col="col-12" sample="Qué incluye el servicio…" />
                    <x-manual.mock-field label="Tarifa / precio base" type="number" sample="65" admin-only hint="Visible en contextos de billing / admin." />
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
