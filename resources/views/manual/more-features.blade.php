@extends('layouts/layoutManual')

@section('title', __('Más funciones'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Más funciones') }}</h4>
    </div>
    <div class="card-body">
        <p>{{ __('Funciones complementarias. Para contenidos, automatización y e-commerce usa las secciones dedicadas del menú del manual.') }}</p>

        <h5>{{ __('Notificaciones') }}</h5>
        <p>{{ __('Avisos en la app o por email a usuarios o contactos (individuales o masivos).') }}</p>

        <h5 class="mt-4">{{ __('Infraestructura (servidores y hosting)') }}</h5>
        <p>{{ __('Alta de servidores y hosting y comprobación de estado. Solo Admin / Root (gate de infraestructura).') }}</p>

        <h5 class="mt-4">{{ __('Suscripción al plan Humano') }}</h5>
        <p>{{ __('Gestión del plan de la plataforma (no confundir con el módulo Suscripciones de clientes en Billing).') }}</p>

        <div class="alert alert-info mt-4" role="alert">
            <strong>{{ __('Ver también') }}:</strong>
            <a href="{{ route('manual.automation') }}">{{ __('Automatización') }}</a>,
            <a href="{{ route('manual.website') }}">{{ __('Sitio web') }}</a>,
            <a href="{{ route('manual.tickets') }}">{{ __('Tickets') }}</a>,
            <a href="{{ route('help.index') }}">{{ __('Ayuda técnica') }}</a>.
        </div>

        <x-manual.role-compare section="more-features" />
    </div>
</div>
@endsection
