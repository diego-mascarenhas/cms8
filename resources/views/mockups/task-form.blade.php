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
            <a href="{{ route('manual.tasks') }}" class="btn btn-label-primary btn-sm">{{ __('Manual: Tareas') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <x-manual.role-compare section="tasks" />

        <div class="card border">
            <div class="card-header">{{ __('Crear / editar tarea') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Título" required sample="Preparar propuesta" />
                    <x-manual.mock-field label="Categoría" type="select" sample="Comercial" col="col-md-3" />
                    <x-manual.mock-field label="Estado" type="select" required sample="Pendiente" col="col-md-3" />
                    <x-manual.mock-field label="Fecha inicio" type="date" admin-only col="col-md-3" />
                    <x-manual.mock-field label="Fecha finalización" type="date" admin-only col="col-md-3" />
                    <x-manual.mock-field label="Responsable" type="select" required sample="Colaborador asignado" admin-only />
                    <x-manual.mock-field label="Descripción" type="textarea" required col="col-12" sample="Detalle de la tarea…" />
                </div>
                <div class="pt-4 d-flex gap-2">
                    <button type="button" class="btn btn-primary" disabled>{{ __('Guardar') }}</button>
                    <button type="button" class="btn btn-label-secondary" disabled>{{ __('Cancelar') }}</button>
                </div>
                <p class="small text-muted mt-3 mb-0">{{ __('En el formulario real, fechas y responsable aparecen cuando el usuario es admin; el collaborator trabaja sobre título, categoría, estado y descripción.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
