@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', __('Prueba el asistente'))

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}">
<style>
.assistant-content h1,
.assistant-content h2,
.assistant-content h3,
.assistant-content h4,
.assistant-content h5,
.assistant-content h6 {
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.35;
    margin: 0.65rem 0 0.35rem;
}
.assistant-content h1 { font-size: 1.05rem; }
.assistant-content h2 { font-size: 1rem; }
.assistant-content h3,
.assistant-content h4,
.assistant-content h5,
.assistant-content h6 { font-size: 0.95rem; }
.assistant-content h1:first-child,
.assistant-content h2:first-child,
.assistant-content h3:first-child {
    margin-top: 0;
}
.assistant-content p { margin-bottom: 0.5rem; }
.assistant-content p:last-child { margin-bottom: 0; }
.assistant-content ul, .assistant-content ol { padding-left: 1.25rem; margin-bottom: 0.5rem; }
.assistant-content li { margin-bottom: 0.25rem; }
.assistant-content strong { font-weight: 600; }
.assistant-content hr {
    margin: 0.65rem 0;
    opacity: 0.25;
}
/* Two-column layout: full height, log always on the right */
.assistant-demo-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
.assistant-demo-row { flex: 1; display: flex; flex-wrap: nowrap; min-height: 0; }
.assistant-demo-row .col-lg-7 { min-height: 0; display: flex; flex-direction: column; }
.assistant-demo-row .col-lg-5 { min-height: 0; display: flex; flex-direction: column; }
/* Left: chat fills space */
.assistant-demo-left { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.assistant-chat-wrapper { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.assistant-chat-wrapper .card { flex: 1; display: flex; flex-direction: column; min-height: 0; }
.assistant-chat-wrapper .card .card-body { flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.assistant-chat-wrapper .card .card-body > div:first-of-type { flex: 1; min-height: 0; overflow: auto; max-height: none !important; }
/* Right: log panel fills column */
.assistant-demo-right { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.assistant-log-panel { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.assistant-log-panel .card-body { flex: 1; min-height: 0; overflow: auto; }
</style>
@endsection

@section('content')
<div class="assistant-demo-wrapper authentication-bg">
  <div class="row g-0 assistant-demo-row mx-0">
    <!-- Left: assistant chat -->
    <div class="col-12 col-lg-7 p-4 auth-cover-bg auth-cover-bg-color assistant-demo-left">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
        <h4 class="mb-0">{{ __('Asistente') }}</h4>
        <a href="{{ url('/') }}" class="btn btn-sm btn-label-secondary waves-effect">
          <i class="ti ti-arrow-left me-1"></i>{{ __('Volver al home') }}
        </a>
      </div>
      <div class="assistant-chat-wrapper">
        @livewire('assistant-chat', ['promptKey' => $promptKey ?? null])
      </div>
    </div>

    <!-- Right: ejemplos para animar a escribir (solo en desktop) -->
    <div class="col-12 col-lg-5 p-4 bg-body border-start assistant-demo-right d-none d-lg-flex">
      <div class="card assistant-log-panel w-100">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Prueba con estos ejemplos') }}</h5>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">
            {{ __('Escribe en el chat tu necesidad o problema. El asistente te enrutará al flujo más adecuado.') }}
          </p>

          <h6 class="text-body mb-2">{{ __('Problemas de negocio') }}</h6>
          <p class="small text-body mb-3">
            {{ __('¿Falta de tiempo, clientes difíciles, desorden en las tareas o no sabes por dónde empezar? Cuéntale al asistente qué te preocupa: retención de clientes, ventas que no cierran, equipos descoordinados, demasiados correos sin responder o ideas que no se convierten en acción. Él te ayuda a ordenar el problema y a proponer pasos concretos.') }}
          </p>

          <h6 class="text-body mb-2">{{ __('Automatización de procesos') }}</h6>
          <p class="small text-body mb-3">
            {{ __('Puedes pedirle consejos para automatizar lo repetitivo: desde respuestas a preguntas frecuentes y seguimiento de leads hasta recordatorios, flujos de email o checklist para onboarding. Te sugiere qué automatizar primero y cómo estructurarlo sin complicarte.') }}
          </p>

          <h6 class="text-body mb-2">{{ __('Captación de leads') }}</h6>
          <p class="small text-body mb-3">
            {{ __('Si quieres atraer más contactos o calificar mejor a los que ya tienes, pregúntale por ideas de landing, mensajes para campañas, preguntas para formularios o secuencias de contacto. También puede ayudarte a definir criterios para priorizar leads y no perder oportunidades.') }}
          </p>

          <h6 class="text-body mb-2">{{ __('Estrategia y contenido') }}</h6>
          <p class="small text-body mb-3">
            {{ __('Pide ideas para estrategia de contenido, planes de publicaciones en redes, temas para tu blog o newsletter, o un calendario editorial sencillo. Útil para lanzamientos, rebrands o cuando no sabes qué publicar la próxima semana.') }}
          </p>

          <h6 class="text-body mb-2">{{ __('Comunicación y redacción') }}</h6>
          <p class="small text-body mb-3">
            {{ __('Redacta con su ayuda emails a clientes, propuestas comerciales, resúmenes para reuniones o respuestas profesionales a quejas y objeciones. También puede resumir textos largos en puntos clave o adaptar el tono (más formal, más cercano, etc.).') }}
          </p>

          <h6 class="text-body mb-2">{{ __('Proyectos y organización') }}</h6>
          <p class="small text-body mb-3">
            {{ __('Organiza notas sueltas, define pasos de un proyecto, prioriza tareas o diseña un mini plan de acción. Si tienes muchas ideas en la cabeza, el asistente te ayuda a bajarlas a un esquema claro y ejecutable.') }}
          </p>

          <p class="small text-muted mb-0">
            {{ __('Describe en tus palabras qué necesitas; el asistente se adapta y te guía al flujo que mejor te sirva.') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
