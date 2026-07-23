@extends('layouts/layoutManual')

@section('title', __('Automatización'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Automatización') }}</h4>
    </div>
    <div class="card-body">
        <p>{{ __('Herramientas para que Humano actúe solo o con asistencia IA: prompts, embudos conversacionales, automatizaciones e integraciones.') }}</p>

        <h5 class="mt-4">{{ __('Prompts') }}</h5>
        <p>{{ __('Instrucciones reutilizables para el asistente (tono, temas, límites). Se usan en chat y flujos.') }}</p>

        <h5 class="mt-4">{{ __('Embudos (funnels)') }}</h5>
        <p>{{ __('Diseñas pasos conversacionales, conectas salidas según la respuesta del usuario y disparas acciones (cita, contacto, tarea…).') }}</p>
        <p class="mb-0">
            <a href="{{ \App\Support\GuidePresentation::url('embudos') }}" class="btn btn-sm btn-label-primary" target="_blank" rel="noopener">
                <i class="ti ti-presentation me-1"></i>{{ __('Ver presentación de embudos') }}
            </a>
        </p>

        <h5 class="mt-4">{{ __('Automatizaciones') }}</h5>
        <p>{{ __('Reglas del tipo “cuando ocurra X, haz Y” (email, notificación, actualización de estado, etc.). Gestión avanzada: Admin.') }}</p>

        <h5 class="mt-4">{{ __('Integraciones') }}</h5>
        <p>{{ __('Conectores con herramientas externas. La configuración técnica (API keys, OAuth) está en') }} <a href="{{ route('help.environment-variables') }}">{{ __('Ayuda → Variables de entorno') }}</a>.</p>

        <x-manual.role-compare section="automation" />
    </div>
</div>
@endsection
