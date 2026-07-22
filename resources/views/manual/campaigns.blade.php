@extends('layouts/layoutManual')

@section('title', __('Mensajes y plantillas'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Mensajes y plantillas') }}</h4>
    </div>
    <div class="card-body">
        <h5>{{ __('Mensajes (campañas)') }}</h5>
        <p>{{ __('Campañas de email o SMS: elige público, redacta o usa plantilla, programa o lanza, envía prueba y sigue resultados.') }}</p>

        <h5 class="mt-4">{{ __('Plantillas') }}</h5>
        <p>{{ __('Diseños reutilizables (cabecera, pie, bloques) para mantener coherencia en las campañas.') }}</p>

        <x-manual.role-compare section="campaigns" />
    </div>
</div>
@endsection
