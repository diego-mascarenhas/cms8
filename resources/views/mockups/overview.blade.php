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
            <a href="{{ route('mockups.roles-flow') }}" class="btn btn-sm btn-label-primary">{{ __('Carriles por rol') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <p class="mb-4">{{ __('Diagrama de decisión: cómo entra cada persona a Humano y qué camino sigue.') }}</p>

        <x-manual.flowchart :nodes="[
            ['shape' => 'terminal', 'label' => 'Inicio: acceso a Humano'],
            ['shape' => 'process', 'label' => 'Login / invitación al equipo', 'body' => 'Misma aplicación para todos los roles'],
            ['shape' => 'decision', 'label' => '¿Qué rol tiene?', 'edge' => 'según Spatie / Jetstream', 'branches' => [
                ['when' => 'Admin', 'label' => 'Panel completo', 'body' => 'CRM + billing + usuarios', 'role' => 'admin'],
                ['when' => 'Collaborator', 'label' => 'Operación', 'body' => 'CRM / tareas / chat', 'role' => 'collaborator'],
                ['when' => 'Client', 'label' => 'Portal restringido', 'body' => 'Sus proyectos y tickets', 'role' => 'client'],
            ]],
        ]" />

        <hr class="my-4">

        <h5 class="mb-3">{{ __('Flujo de valor (de lead a cobro)') }}</h5>
        <x-manual.flowchart title="Cadena principal" :nodes="[
            ['shape' => 'terminal', 'label' => 'Lead / contacto'],
            ['shape' => 'process', 'label' => 'Cliente (empresa) en CRM', 'role' => 'admin'],
            ['shape' => 'process', 'label' => 'Proyecto + servicios', 'role' => 'collaborator'],
            ['shape' => 'process', 'label' => 'Tareas y tiempo', 'role' => 'collaborator'],
            ['shape' => 'decision', 'label' => '¿Facturar?', 'branches' => [
                ['when' => 'Sí (Admin)', 'label' => 'Factura → pago → finanzas', 'role' => 'admin'],
                ['when' => 'Cliente final', 'label' => 'Ve avance / abre ticket / presupuesto link', 'role' => 'client'],
            ]],
            ['shape' => 'terminal', 'label' => 'Fin del ciclo'],
        ]" />

        <x-manual.role-compare section="getting-started" />

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('mockups.client-journey') }}" class="btn btn-success btn-sm">
                <i class="ti ti-user-heart me-1"></i>{{ __('Viaje del Client') }}
            </a>
            <a href="{{ route('mockups.collaborator-day') }}" class="btn btn-label-secondary btn-sm">{{ __('Día Collaborator') }}</a>
            <a href="{{ route('mockups.admin-setup') }}" class="btn btn-primary btn-sm">{{ __('Arranque Admin') }}</a>
        </div>
    </div>
</div>
@endsection
