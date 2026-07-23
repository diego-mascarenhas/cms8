@extends('layouts/layoutManual')

@section('title', __('Equipo'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">{{ __('Equipo') }}</h4>
        <a href="{{ route('mockups.admin-setup') }}" class="btn btn-sm btn-label-primary">
            <i class="ti ti-settings me-1"></i>{{ __('Mockup de arranque') }}
        </a>
    </div>
    <div class="card-body">
        <h5>{{ __('Gestión de usuarios') }}</h5>
        <p>{{ __('Los administradores invitan usuarios, asignan roles (admin, collaborator, employee) y activan o desactivan el acceso.') }}</p>

        <h5 class="mt-4">{{ __('Departamentos') }}</h5>
        <p>{{ __('Organizan el equipo (Ventas, Desarrollo, etc.) para filtrar informes y trabajo.') }}</p>

        <x-manual.role-compare section="team" />

        <p class="mb-0">{{ __('La configuración del equipo (datos fiscales, correo, tokens) está en ajustes; detalles técnicos en Ayuda.') }}</p>
    </div>
</div>
@endsection
