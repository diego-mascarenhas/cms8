@extends('layouts/layoutManual')

@section('title', __('Dashboard y Hoy'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Dashboard y Hoy') }}</h4>
    </div>
    <div class="card-body">
        <h5>{{ __('Dashboard') }}</h5>
        <p>{{ __('El dashboard es tu pantalla de resumen. Muestra de un vistazo la actividad reciente del equipo: últimos contactos, tareas pendientes, estado de proyectos y métricas clave. Te ayuda a ver qué requiere atención sin entrar en cada módulo.') }}</p>

        <h5 class="mt-4">{{ __('Vista “Hoy” (Today)') }}</h5>
        <p>{{ __('La vista Hoy se centra en lo que importa para el día actual: tareas con vencimiento hoy, eventos del calendario y accesos rápidos. Es tu lista del día para no perder el foco.') }}</p>

        <x-manual.role-compare section="dashboard" />

        <p class="mb-0">{{ __('Ambas vistas están en el menú principal y se adaptan a tu rol y permisos.') }}</p>
    </div>
</div>
@endsection
