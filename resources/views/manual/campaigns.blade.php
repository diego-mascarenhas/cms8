@extends('layouts/layoutManual')

@section('title', __('Marketing'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Marketing') }}</h4>
    </div>
    <div class="card-body">
        <h5>{{ __('Campañas') }}</h5>
        <p>{{ __('Campañas de email o SMS: elige público, redacta o usa plantilla, programa o lanza, envía prueba y sigue resultados.') }}</p>

        <h5 class="mt-4">{{ __('Mensajes y plantillas') }}</h5>
        <p>{{ __('Mensajes individuales o masivos y diseños reutilizables (cabecera, pie, bloques) para mantener coherencia.') }}</p>

        <h5 class="mt-4">{{ __('Publicidad de pago (Paid Ads)') }}</h5>
        <p>{{ __('Gestiona campañas en Google Ads, Meta, LinkedIn, TikTok y X. Las credenciales se configuran por equipo en Team Settings.') }}</p>
        <p class="mb-0">
            <a href="{{ route('help.paid-ads-setup') }}" class="btn btn-sm btn-label-primary">{{ __('Setup Paid Ads (Ayuda)') }}</a>
            <a href="{{ route('help.team-social-networks') }}" class="btn btn-sm btn-label-secondary">{{ __('Redes sociales del equipo') }}</a>
        </p>

        <x-manual.role-compare section="campaigns" />
    </div>
</div>
@endsection
