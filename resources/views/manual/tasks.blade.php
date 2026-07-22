@extends('layouts/layoutManual')

@section('title', __('Tareas y tiempo'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="card-title mb-0">{{ __('Tareas y tiempo') }}</h4>
        <a href="{{ route('mockups.task-form') }}" class="btn btn-sm btn-label-primary">
            <i class="ti ti-forms me-1"></i>{{ __('Mockup del formulario') }}
        </a>
    </div>
    <div class="card-body">
        <h5>{{ __('Tareas') }}</h5>
        <p>{{ __('Las tareas son acciones concretas; pueden estar ligadas a un proyecto o ser independientes.') }}</p>
        <ul>
            <li>{{ __('Crear y editar con título, descripción, categoría, estado y fechas.') }}</li>
            <li>{{ __('Asignar responsable (Admin en el formulario estándar).') }}</li>
            <li>{{ __('Cambiar estado y reordenar; comunicar feedback vinculado a la tarea.') }}</li>
        </ul>

        <h5 class="mt-4">{{ __('Tablero Kanban') }}</h5>
        <p>{{ __('Columnas por estado: arrastra tarjetas para actualizar el progreso de forma visual.') }}</p>

        <h5 class="mt-4">{{ __('Registro de tiempo y asistencia') }}</h5>
        <p>{{ __('Cronómetro o entradas manuales por tarea. La asistencia marca entrada, pausa y salida del día laboral.') }}</p>

        <x-manual.role-compare section="tasks" />

        <a href="{{ route('mockups.collaborator-day') }}" class="btn btn-sm btn-label-secondary">{{ __('Ver flujo del día collaborator') }}</a>
    </div>
</div>
@endsection
