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
            <a href="{{ route('manual.projects') }}" class="btn btn-label-primary btn-sm">{{ __('Manual: Proyectos') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <x-manual.role-compare section="projects" />

        <div class="card border mb-4">
            <div class="card-header">{{ __('Datos generales') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Nombre interno" required sample="Web corporativa Q3" />
                    <x-manual.mock-field label="Nombre real / comercial" sample="Rediseño web Acme" />
                    <x-manual.mock-field label="Estado" type="select" sample="En curso" />
                    <x-manual.mock-field label="Categoría" type="select" sample="Desarrollo web" />
                    <x-manual.mock-field label="Cliente" type="select" required sample="Acme SL" />
                    <x-manual.mock-field label="Asesor / responsable" type="select" required sample="Admin del equipo" />
                    <x-manual.mock-field label="Fecha inicio" type="date" />
                    <x-manual.mock-field label="Fecha entrega material" type="date" />
                    <x-manual.mock-field label="Fecha entrega final" type="date" />
                    <x-manual.mock-field label="Descripción" type="textarea" col="col-12" sample="Alcance del proyecto…" />
                </div>
            </div>
        </div>

        <div class="card border border-primary mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="badge bg-primary">Admin</span>
                <span>{{ __('Presupuesto y precios (ocultos al collaborator)') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Precio" type="number" sample="12000" admin-only />
                    <x-manual.mock-field label="Descuento %" type="number" sample="5" admin-only />
                    <x-manual.mock-field label="Coste" type="number" sample="7800" admin-only />
                    <x-manual.mock-field label="Presupuesto recibido" type="textarea" col="col-12" sample="Texto del presupuesto del cliente…" admin-only />
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-primary" disabled>{{ __('Guardar') }}</button>
            <button type="button" class="btn btn-label-secondary" disabled>{{ __('Cancelar') }}</button>
            <span class="badge bg-label-danger align-self-center">{{ __('Eliminar proyecto: solo Admin') }}</span>
        </div>
    </div>
</div>
@endsection
