@extends('layouts/layoutMaster')

@section('title', __('Asistente'))

@section('page-style')
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
</style>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Asistente') }}</h4>
        <p class="text-muted mb-0">{{ __('Escribe tu necesidad; te enrutaré al flujo más adecuado (estrategia, email, notas, proyecto, etc.).') }}</p>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        @livewire('assistant-chat')
    </div>
</div>
@endsection
