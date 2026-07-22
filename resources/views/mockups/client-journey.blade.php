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
            <a href="{{ route('mockups.client-home') }}" class="btn btn-sm btn-label-success">{{ __('Home Client') }}</a>
            <a href="{{ route('mockups.client-ticket') }}" class="btn btn-sm btn-success">{{ __('Ticket') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-success" role="alert">
            <span class="badge bg-success me-1">Client</span>
            {{ __('Usuario final del equipo: entra a Humano para seguir su trabajo con el proveedor, no para administrar el CRM.') }}
        </div>

        <h5 class="mb-3">{{ __('1. Cómo se crea el acceso') }}</h5>
        <x-manual.flowchart :nodes="[
            ['shape' => 'terminal', 'label' => 'Admin tiene un contacto'],
            ['shape' => 'process', 'label' => 'Vincular / invitar usuario', 'body' => 'Rol Client (Jetstream + Spatie)', 'role' => 'admin'],
            ['shape' => 'process', 'label' => 'Contacto.user_id = usuario Client', 'role' => 'admin'],
            ['shape' => 'note', 'label' => 'Si el usuario está ligado a un contacto, el rol Client no se puede cambiar a otro'],
            ['shape' => 'terminal', 'label' => 'Client puede iniciar sesión'],
        ]" />

        <h5 class="mb-3 mt-4">{{ __('2. Sesión del Client') }}</h5>
        <x-manual.flowchart :nodes="[
            ['shape' => 'terminal', 'label' => 'Login'],
            ['shape' => 'process', 'label' => 'Dashboard client', 'body' => 'Vista restringida (misma app)', 'role' => 'client'],
            ['shape' => 'decision', 'label' => '¿Qué necesita?', 'branches' => [
                ['when' => 'Seguimiento', 'label' => 'Ver proyectos / servicios propios', 'role' => 'client'],
                ['when' => 'Soporte', 'label' => 'Crear ticket', 'role' => 'client'],
                ['when' => 'Presupuesto', 'label' => 'Abrir /p/budget/{token}', 'body' => 'Enlace público o compartido', 'role' => 'client'],
            ]],
            ['shape' => 'process', 'label' => 'Sin menú Billing ni Infra', 'role' => 'client'],
            ['shape' => 'terminal', 'label' => 'Fin de la sesión'],
        ]" />

        <h5 class="mb-3 mt-4">{{ __('3. Relación con el equipo interno') }}</h5>
        <x-manual.flowchart :nodes="[
            ['shape' => 'process', 'label' => 'Client abre ticket o consulta proyecto', 'role' => 'client'],
            ['shape' => 'process', 'label' => 'Collaborator / Admin responde', 'role' => 'collaborator'],
            ['shape' => 'decision', 'label' => '¿Hay cobro?', 'branches' => [
                ['when' => 'Sí', 'label' => 'Admin emite factura / registra pago', 'role' => 'admin'],
                ['when' => 'No', 'label' => 'Continúa el trabajo operativo', 'role' => 'collaborator'],
            ]],
        ]" />

        <x-manual.role-compare section="getting-started" />
    </div>
</div>
@endsection
