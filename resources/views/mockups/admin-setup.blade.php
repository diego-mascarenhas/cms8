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
            <a href="{{ route('manual.team') }}" class="btn btn-label-primary btn-sm">{{ __('Manual: Equipo') }}</a>
            <a href="{{ route('mockups.index') }}" class="btn btn-label-secondary btn-sm">{{ __('Catálogo') }}</a>
        </div>
    </div>
    <div class="card-body">
        <x-manual.flowchart :nodes="[
            ['shape' => 'terminal', 'label' => 'Nuevo equipo / cuenta'],
            ['shape' => 'process', 'label' => 'Datos del negocio y módulos', 'role' => 'admin'],
            ['shape' => 'process', 'label' => 'Invitar Collaborators', 'role' => 'admin'],
            ['shape' => 'process', 'label' => 'Crear contactos / clientes CRM', 'role' => 'admin'],
            ['shape' => 'decision', 'label' => '¿Dar acceso al cliente final?', 'branches' => [
                ['when' => 'Sí', 'label' => 'Vincular contacto → usuario Client', 'role' => 'admin'],
                ['when' => 'No', 'label' => 'Seguir solo con equipo interno', 'role' => 'admin'],
            ]],
            ['shape' => 'process', 'label' => 'WhatsApp / canales', 'role' => 'admin'],
            ['shape' => 'process', 'label' => 'Servicios, tarifas, prueba de factura', 'role' => 'admin'],
            ['shape' => 'terminal', 'label' => 'Equipo listo'],
        ]" />

        <x-manual.role-compare section="team" />

        <div class="card border mt-3">
            <div class="card-header">{{ __('Invitar / vincular (mockup)') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <x-manual.mock-field label="Nombre" required sample="Ana" admin-only />
                    <x-manual.mock-field label="Email" type="email" required sample="ana@cliente.example" admin-only />
                    <x-manual.mock-field label="Rol" type="select" required sample="client" admin-only hint="admin | collaborator | client" />
                    <x-manual.mock-field label="Contacto vinculado" type="select" sample="Ana Pérez (contacto CRM)" admin-only />
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
