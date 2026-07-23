@extends('layouts/layoutManual')

@section('title', __('Dashboard, Hoy y Calendario'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Dashboard, Hoy y Calendario') }}</h4>
    </div>
    <div class="card-body">
        <h5>{{ __('Dashboard') }}</h5>
        <p>{{ __('Pantalla de resumen: actividad reciente, tareas pendientes, proyectos y métricas clave del equipo.') }}</p>
        <p class="mb-0">
            <a href="{{ \App\Support\GuidePresentation::url('insight-diario') }}" class="btn btn-sm btn-label-primary" target="_blank" rel="noopener">
                <i class="ti ti-presentation me-1"></i>{{ __('Presentación Insight diario') }}
            </a>
        </p>

        <h5 class="mt-4">{{ __('Vista Hoy (Today)') }}</h5>
        <p>{{ __('Enfoque del día: tareas con vencimiento, accesos rápidos y eventos próximos. Ideal para no perder el foco.') }}</p>

        <h5 class="mt-4">{{ __('Calendario') }}</h5>
        <p>{{ __('Agenda mensual/semanal con citas, recordatorios y eventos del equipo. Puede sincronizarse con Google Calendar (configuración técnica en Ayuda).') }}</p>
        <p class="mb-0">
            <a href="{{ \App\Support\GuidePresentation::url('calendario') }}" class="btn btn-sm btn-label-primary" target="_blank" rel="noopener">
                <i class="ti ti-presentation me-1"></i>{{ __('Presentación Calendario') }}
            </a>
            <a href="{{ route('help.environment-variables.google-people-calendar') }}" class="btn btn-sm btn-label-secondary">{{ __('Sync Google People/Calendar') }}</a>
        </p>

        <x-manual.role-compare section="dashboard" />
    </div>
</div>
@endsection
