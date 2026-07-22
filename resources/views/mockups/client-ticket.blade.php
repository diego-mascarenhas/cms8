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
            <a href="{{ route('mockups.client-journey') }}" class="btn btn-sm btn-label-success">{{ __('Viaje Client') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-success" role="alert">
            <span class="badge bg-success me-1">Client</span>
            {{ __('Mockup del formulario real de alta de ticket (ticket/create). El Client puede crear; el listado completo de tickets del equipo no es su pantalla principal.') }}
        </div>

        <x-manual.flowchart title="Flujo del ticket" :nodes="[
            ['shape' => 'terminal', 'label' => 'Client necesita soporte'],
            ['shape' => 'process', 'label' => 'Abrir Nuevo ticket', 'role' => 'client'],
            ['shape' => 'process', 'label' => 'Completar formulario', 'role' => 'client'],
            ['shape' => 'process', 'label' => 'Equipo recibe y atiende', 'role' => 'collaborator'],
            ['shape' => 'terminal', 'label' => 'Ticket resuelto / cerrado'],
        ]" />

        <div class="card border border-success mt-4">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="badge bg-success">Client</span>
                {{ __('Formulario de ticket (mockup)') }}
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Asunto" required sample="Incidencia en la web publicada" col="col-12" />
                    <x-manual.mock-field label="Prioridad" type="select" required sample="Media" col="col-12" />
                    <x-manual.mock-field label="Descripción" type="textarea" required sample="Detalle del problema, pasos para reproducirlo…" col="col-12" />
                    <x-manual.mock-field label="Adjuntos" type="text" sample="captura.png, log.pdf" col="col-12" hint="Imágenes, PDF, ZIP, DOC… (máx. 10 MB)" />
                </div>
                <div class="pt-4 d-flex gap-2">
                    <button type="button" class="btn btn-success" disabled>{{ __('Crear ticket') }}</button>
                    <button type="button" class="btn btn-label-secondary" disabled>{{ __('Cancelar') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
