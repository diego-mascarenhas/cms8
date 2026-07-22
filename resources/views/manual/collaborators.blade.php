@extends('layouts/layoutManual')

@section('title', __('Colaboradores'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Colaboradores') }}</h4>
    </div>
    <div class="card-body">
        <h5>{{ __('Perfiles de colaboradores') }}</h5>
        <p>{{ __('Los colaboradores son profesionales (internos o externos) que participan en proyectos. Puedes mantener perfiles con habilidades, disponibilidad, portafolio y datos de contacto.') }}</p>

        <h5 class="mt-4">{{ __('Tarifas y asignación') }}</h5>
        <p>{{ __('El administrador define tarifas y asigna colaboradores a proyectos. En la ficha del proyecto se ven los miembros asignados y, para admin, información de coste.') }}</p>

        <x-manual.role-compare section="collaborators" />

        <p class="mb-0">{{ __('No confundas el rol “Collaborator” (usuario que inicia sesión) con el módulo “Colaboradores” (ficha de profesional del catálogo).') }}</p>
    </div>
</div>
@endsection
