@extends('layouts/layoutManual')

@section('title', __('Proyectos'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">{{ __('Proyectos') }}</h4>
        <a href="{{ route('mockups.project-form') }}" class="btn btn-sm btn-label-primary">
            <i class="ti ti-forms me-1"></i>{{ __('Mockup del formulario') }}
        </a>
    </div>
    <div class="card-body">
        <h5>{{ __('Crear y gestionar proyectos') }}</h5>
        <p>{{ __('Un proyecto agrupa el trabajo para un cliente u objetivo: presupuesto, servicios, colaboradores y tareas. Puedes:') }}</p>
        <ul>
            <li>{{ __('Crear proyectos y vincularlos a un cliente o contacto.') }}</li>
            <li>{{ __('Definir presupuesto, servicios, cantidades y totales (Admin).') }}</li>
            <li>{{ __('Asignar colaboradores y notificarles cambios.') }}</li>
            <li>{{ __('Seguir tareas y avance desde la ficha del proyecto.') }}</li>
        </ul>

        <x-manual.role-compare section="projects" />

        <h5 class="mt-2">{{ __('Vista detalle') }}</h5>
        <p class="mb-0">{{ __('En la ficha ves presupuesto (si eres Admin), servicios, colaboradores y tareas. Los campos de precio y coste están detrás del permiso de facturación.') }}</p>
    </div>
</div>
@endsection
