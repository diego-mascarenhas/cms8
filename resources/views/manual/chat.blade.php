@extends('layouts/layoutManual')

@section('title', __('Chat y WhatsApp'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Chat y WhatsApp') }}</h4>
            </div>
            <div class="card-body">
                <h5>{{ __('Chat integrado') }}</h5>
                <p>{{ __('El módulo de chat permite mantener conversaciones con tus contactos desde Humano. Puedes:') }}</p>
                <ul>
                    <li>{{ __('Ver la lista de conversaciones y abrir la que te interese.') }}</li>
                    <li>{{ __('Enviar y recibir mensajes en tiempo real dentro de la plataforma.') }}</li>
                    <li>{{ __('Usar plantillas de mensaje para respuestas rápidas o mensajes recurrentes.') }}</li>
                </ul>

                <h5 class="mt-4">{{ __('Integración con WhatsApp') }}</h5>
                <p>{{ __('Si WhatsApp está configurado para tu equipo, puedes hablar con los contactos por WhatsApp sin salir de Humano. La primera vez puede ser necesario escanear un código QR o completar la configuración desde el panel. Desde la misma pantalla puedes comprobar el estado de la conexión y renovar el QR si hace falta. Los mensajes de WhatsApp y los del chat quedan unificados en la conversación del contacto.') }}</p>

                <h5 class="mt-4">{{ __('Asistente y preferencias') }}</h5>
                <p>{{ __('Puedes activar un asistente con IA que te ayude en las conversaciones (por ejemplo sugerir respuestas o resumir). También puedes activar o desactivar respuestas automáticas y elegir cómo quieres recibir notificaciones del chat (sonido, avisos en navegador, etc.).') }}</p>

                <p class="mb-0">{{ __('El chat sirve para atención al cliente, seguimiento comercial o cualquier comunicación que quieras centralizar en Humano.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
