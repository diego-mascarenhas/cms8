@extends('layouts/layoutManual')

@section('title', __($mockup['title']))

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="card-title mb-1">{{ __($mockup['title']) }}</h4>
            <p class="text-muted mb-0">{{ __($mockup['description']) }}</p>
        </div>
        <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
    </div>
    <div class="card-body">
        <x-manual.flowchart :nodes="[
            ['shape' => 'terminal', 'label' => 'Inicio del día'],
            ['shape' => 'process', 'label' => 'Abrir Hoy', 'body' => 'Pendientes y vencimientos', 'role' => 'collaborator'],
            ['shape' => 'process', 'label' => 'Kanban / tareas', 'body' => 'Mover estados', 'role' => 'collaborator'],
            ['shape' => 'decision', 'label' => '¿Hay mensaje entrante?', 'branches' => [
                ['when' => 'Sí', 'label' => 'Atender Chat / WhatsApp', 'role' => 'collaborator'],
                ['when' => 'No', 'label' => 'Seguir con el proyecto asignado', 'role' => 'collaborator'],
            ]],
            ['shape' => 'process', 'label' => 'Registrar tiempo', 'role' => 'collaborator'],
            ['shape' => 'decision', 'label' => '¿Bloqueo o cobro?', 'branches' => [
                ['when' => 'Bloqueo técnico', 'label' => 'Escalar a Admin', 'role' => 'admin'],
                ['when' => 'Facturación', 'label' => 'Solo Admin factura', 'role' => 'admin'],
            ]],
            ['shape' => 'terminal', 'label' => 'Fin del día'],
        ]" />

        <x-manual.role-compare section="tasks" />
    </div>
</div>
@endsection
