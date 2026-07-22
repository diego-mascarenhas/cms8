@extends('layouts/layoutManual')

@section('title', __('Chat y WhatsApp'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Chat y WhatsApp') }}</h4>
    </div>
    <div class="card-body">
        <h5>{{ __('Chat integrado') }}</h5>
        <p>{{ __('El m?dulo de chat permite mantener conversaciones con tus contactos desde Humano. Puedes:') }}</p>
        <ul>
            <li>{{ __('Ver la lista de conversaciones y abrir la que te interese.') }}</li>
            <li>{{ __('Enviar y recibir mensajes en tiempo real dentro de la plataforma.') }}</li>
            <li>{{ __('Usar plantillas de mensaje para respuestas r?pidas o mensajes recurrentes.') }}</li>
        </ul>

        <h5 class="mt-4">{{ __('Integraci?n con WhatsApp') }}</h5>
        <p>{{ __('Si WhatsApp est? configurado para tu equipo, puedes hablar con los contactos por WhatsApp sin salir de Humano. La primera vez puede ser necesario escanear un c?digo QR. Los mensajes de WhatsApp y los del chat quedan unificados en la conversaci?n del contacto.') }}</p>

        <h5 class="mt-4">{{ __('Asistente y preferencias') }}</h5>
        <p>{{ __('Puedes activar un asistente con IA que sugiera respuestas o resuma. Tambi?n puedes activar o desactivar respuestas autom?ticas y elegir notificaciones del chat.') }}</p>

        <x-manual.role-compare section="chat" />

        <p class="mb-0">{{ __('El chat sirve para atenci?n al cliente, seguimiento comercial o cualquier comunicaci?n centralizada en Humano.') }}</p>
    </div>
</div>
@endsection
