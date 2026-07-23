@extends('layouts/layoutManual')

@section('title', __('Chat y WhatsApp'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Chat y WhatsApp') }}</h4>
    </div>
    <div class="card-body">
        <h5>{{ __('Chat integrado') }}</h5>
        <p>{{ __('Mant?n conversaciones con tus contactos desde Humano: lista de hilos, mensajes en tiempo real y plantillas r?pidas.') }}</p>

        <h5 class="mt-4">{{ __('Integraci?n con WhatsApp') }}</h5>
        <p>{{ __('Si WhatsApp est? configurado para tu equipo, hablas con contactos sin salir de Humano. La primera vez puede hacer falta escanear un c?digo QR. Los mensajes quedan unificados en la conversaci?n del contacto.') }}</p>

        <h5 class="mt-4">{{ __('Asistente y preferencias') }}</h5>
        <p>{{ __('Puedes activar un asistente con IA (sugerencias, res?menes), respuestas autom?ticas y notificaciones del chat.') }}</p>
        <p class="mb-0">
            <a href="{{ \App\Support\GuidePresentation::url('chat-contactos-modulos') }}" class="btn btn-sm btn-label-primary" target="_blank" rel="noopener">
                <i class="ti ti-presentation me-1"></i>{{ __('Presentaci?n Chat y contactos') }}
            </a>
            <a href="{{ route('help.chat-assistant') }}" class="btn btn-sm btn-label-secondary">{{ __('Ayuda t?cnica del asistente') }}</a>
        </p>

        <x-manual.role-compare section="chat" />
    </div>
</div>
@endsection
