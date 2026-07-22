@extends('layouts/layoutManual')

@section('title', __('Más funciones'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Más funciones') }}</h4>
    </div>
    <div class="card-body">
        <p>{{ __('Otros módulos según plan y configuración:') }}</p>

        <h5>{{ __('Empresas (Enterprises)') }}</h5>
        <p>{{ __('Compañías u organizaciones asociadas a contactos y clientes.') }}</p>

        <h5 class="mt-4">{{ __('Contenidos, CMS y multimedia') }}</h5>
        <p>{{ __('Bloques reutilizables, entradas/páginas (p. ej. WordPress) y galería de archivos.') }}</p>

        <h5 class="mt-4">{{ __('Prompts, embudos y automatizaciones') }}</h5>
        <p>{{ __('Instrucciones para el asistente IA, flujos conversacionales y acciones automáticas. Gestión avanzada: Admin.') }}</p>

        <h5 class="mt-4">{{ __('Infraestructura') }}</h5>
        <p>{{ __('Servidores y hosting: solo Admin / Root.') }}</p>

        <h5 class="mt-4">{{ __('Suscripción Humano') }}</h5>
        <p>{{ __('Plan de la plataforma y facturación de Humano: Admin.') }}</p>

        <x-manual.role-compare section="more-features" />

        <p class="mb-0">{{ __('Para API, variables de entorno e integraciones consulta') }} <a href="{{ route('help.index') }}">{{ __('Ayuda y documentación') }}</a>.</p>
    </div>
</div>
@endsection
