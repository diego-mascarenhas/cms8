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
        <p class="mb-4">{{ __('Tres carriles en paralelo: el mismo producto, caminos distintos.') }}</p>

        <x-manual.swimlane-flow :lanes="[
            [
                'role' => 'admin',
                'title' => 'Administrador',
                'steps' => [
                    ['label' => 'Configurar equipo y módulos'],
                    ['label' => 'Invitar Collaborators y Clients'],
                    ['label' => 'Vincular contacto → usuario Client'],
                    ['label' => 'CRM completo + proyectos'],
                    ['label' => 'Facturar y registrar pagos'],
                    ['label' => 'Automatizaciones / WhatsApp'],
                ],
                'blocked' => [],
            ],
            [
                'role' => 'collaborator',
                'title' => 'Colaborador',
                'steps' => [
                    ['label' => 'Entrar al equipo invitado'],
                    ['label' => 'Ver Hoy / tareas asignadas'],
                    ['label' => 'Gestionar contactos y proyectos propios'],
                    ['label' => 'Atender chat / WhatsApp'],
                    ['label' => 'Registrar tiempo'],
                ],
                'blocked' => ['Facturación', 'Usuarios', 'Infraestructura'],
            ],
            [
                'role' => 'client',
                'title' => 'Cliente final',
                'steps' => [
                    ['label' => 'Recibir invitación / enlace de acceso'],
                    ['label' => 'Login (sin cambiar de equipo)'],
                    ['label' => 'Ver proyectos / servicios propios'],
                    ['label' => 'Abrir ticket de soporte'],
                    ['label' => 'Consultar presupuesto por enlace'],
                ],
                'blocked' => ['Billing', 'Kanban interno', 'Campañas', 'Usuarios'],
            ],
        ]" />

        <div class="alert alert-success" role="alert">
            <strong>Client</strong> —
            {{ __('No es un portal aparte: es el mismo Humano con políticas que limitan la vista a lo suyo (contacto → empresas → proyectos/servicios).') }}
        </div>

        <a href="{{ route('mockups.client-journey') }}" class="btn btn-success">
            <i class="ti ti-git-fork me-1"></i>{{ __('Ver diagrama del viaje Client') }}
        </a>
    </div>
</div>
@endsection
