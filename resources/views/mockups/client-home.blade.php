@extends('layouts/layoutManual')

@section('title', __($mockup['title']))

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="card-title mb-1">{{ __($mockup['title']) }}</h4>
            <p class="text-muted mb-0">{{ __($mockup['description']) }}</p>
        </div>
        <a href="{{ route('mockups.client-journey') }}" class="btn btn-sm btn-label-success">{{ __('Viaje Client') }}</a>
    </div>
    <div class="card-body">
        <x-manual.flowchart title="Entrada del Client" :nodes="[
            ['shape' => 'terminal', 'label' => 'Login'],
            ['shape' => 'process', 'label' => 'Dashboard /client', 'body' => 'Misma app, menú filtrado', 'role' => 'client'],
            ['shape' => 'decision', 'label' => '¿Qué hay en el menú?', 'branches' => [
                ['when' => 'Sí ve', 'label' => 'Proyectos / servicios propios', 'role' => 'client'],
                ['when' => 'No ve', 'label' => 'Billing, usuarios, infra, campañas', 'role' => 'client'],
            ]],
        ]" />

        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <div class="card border-success h-100">
                    <div class="card-body">
                        <h6 class="text-success">{{ __('Mis proyectos') }}</h6>
                        <p class="small text-muted mb-0">{{ __('Solo los vinculados a sus empresas.') }}</p>
                        <div class="border rounded p-2 mt-2 small bg-label-success">Web corporativa — En curso</div>
                        <div class="border rounded p-2 mt-2 small">App móvil — Entrega</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success h-100">
                    <div class="card-body">
                        <h6 class="text-success">{{ __('Soporte') }}</h6>
                        <p class="small text-muted mb-2">{{ __('Abrir un ticket cuando necesita ayuda.') }}</p>
                        <a href="{{ route('mockups.client-ticket') }}" class="btn btn-sm btn-success">{{ __('Mockup ticket') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border h-100">
                    <div class="card-body">
                        <h6>{{ __('Presupuesto') }}</h6>
                        <p class="small text-muted mb-0">{{ __('Enlace /p/budget/{token} compartido por el equipo (vista pública/controlada).') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-secondary mt-4 mb-0" role="alert">
            {{ __('Nota: el dashboard client reutiliza la shell de la app; no es un producto portal separado. Las políticas (contacto → enterprise) definen qué registros ve.') }}
        </div>
    </div>
</div>
@endsection
