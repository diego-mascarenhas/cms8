@extends('layouts/layoutManual')

@section('title', __('Tareas y tiempo'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Tareas y tiempo') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Tareas') }}</h5>
                <p>{{ __('Las tareas son acciones concretas que hay que hacer; pueden estar ligadas a un proyecto o ser independientes. Con las tareas puedes:') }}</p>
                <ul>
                    <li>{{ __('Crear, editar y eliminar tareas con título, descripción, categoría, estado y fecha de vencimiento.') }}</li>
                    <li>{{ __('Asignar un responsable (usuario del equipo).') }}</li>
                    <li>{{ __('Cambiar el estado (pendiente, en curso, completada, etc.) y reordenar tareas.') }}</li>
                    <li>{{ __('Enviar comunicaciones vinculadas a la tarea (por ejemplo solicitar feedback al cliente) y ver las respuestas.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('Tablero de tareas (Kanban)') }}</h5>
                <p>{{ __('El tablero Kanban muestra las tareas en columnas según su estado. Puedes arrastrar las tarjetas de una columna a otra para actualizar el progreso de forma visual. Es útil para ver de un vistazo qué está pendiente, en curso o terminado.') }}</p>

                <h5 class="mt-4">{{ __('Registro de tiempo') }}</h5>
                <p>{{ __('Puedes registrar el tiempo dedicado a cada tarea: iniciar y parar un cronómetro o añadir entradas manuales (fecha, horas, descripción). El tiempo se puede consultar por tarea y usarse para facturación o informes.') }}</p>

                <h5 class="mt-4">{{ __('Asistencia') }}</h5>
                <p>{{ __('El módulo de asistencia sirve para marcar entrada, pausa, reanudación y salida del día laboral. Útil para control de presencia y horas trabajadas del equipo.') }}</p>

                <p class="mb-0">{{ __('Tareas, tiempo y asistencia te permiten saber qué se ha hecho, quién y cuánto tiempo ha llevado.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
