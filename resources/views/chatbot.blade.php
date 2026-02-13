@extends('layouts/layoutMaster')

@section('title', __('Asistente'))

@section('page-style')
<style>
.assistant-content p { margin-bottom: 0.5rem; }
.assistant-content p:last-child { margin-bottom: 0; }
.assistant-content ul, .assistant-content ol { padding-left: 1.25rem; margin-bottom: 0.5rem; }
.assistant-content li { margin-bottom: 0.25rem; }
.assistant-content strong { font-weight: 600; }
.assistant-content h2, .assistant-content h3 { font-size: 1rem; margin: 0.75rem 0 0.5rem; }
</style>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Asistente') }}</h4>
        <p class="text-muted">{{ __('Escribe tu necesidad; te enrutaré al flujo más adecuado (estrategia, email, notas, proyecto, etc.).') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('chat.index') }}" class="btn btn-label-secondary">
            <i class="ti ti-message-chatbot me-1"></i>{{ __('Chat WhatsApp') }}
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        @livewire('assistant-chat')
    </div>
</div>
@endsection
