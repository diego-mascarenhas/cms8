@extends('layouts/layoutManual')

@section('title', __('Tickets'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">{{ __('Tickets de soporte') }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('mockups.client-ticket') }}" class="btn btn-sm btn-success">{{ __('Mockup Client') }}</a>
            <a href="{{ route('mockups.client-journey') }}" class="btn btn-sm btn-label-success">{{ __('Viaje Client') }}</a>
        </div>
    </div>
    <div class="card-body">
        <p>{{ __('Los tickets centralizan incidencias y peticiones de soporte. El equipo interno gestiona la cola; el usuario Client puede abrir tickets desde su acceso.') }}</p>

        <h5 class="mt-4">{{ __('Equipo (Admin / Collaborator)') }}</h5>
        <ul>
            <li>{{ __('Listar tickets, filtrar por estado y prioridad.') }}</li>
            <li>{{ __('Asignar responsables y responder con contexto del contacto/proyecto.') }}</li>
            <li>{{ __('Cerrar o reabrir cuando corresponda.') }}</li>
        </ul>

        <h5 class="mt-4">{{ __('Usuario Client') }}</h5>
        <ul>
            <li>{{ __('Crear ticket: asunto, prioridad, descripción y adjuntos.') }}</li>
            <li>{{ __('Es el canal de ayuda del portal del cliente final (misma app, rol Client).') }}</li>
        </ul>

        <x-manual.flowchart :nodes="[
            ['shape' => 'terminal', 'label' => 'Problema o petición'],
            ['shape' => 'process', 'label' => 'Client crea ticket', 'role' => 'client'],
            ['shape' => 'process', 'label' => 'Equipo atiende', 'role' => 'collaborator'],
            ['shape' => 'decision', 'label' => '¿Resuelto?', 'branches' => [
                ['when' => 'Sí', 'label' => 'Cerrar ticket', 'role' => 'admin'],
                ['when' => 'No', 'label' => 'Seguir conversación', 'role' => 'collaborator'],
            ]],
        ]" />

        <x-manual.role-compare section="tickets" />
    </div>
</div>
@endsection
