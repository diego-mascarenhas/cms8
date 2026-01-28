@extends('layouts/layoutMaster')

@section('title', $prompt->section_label)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Prompts') }}/</span> {{ $prompt->section_label }}</h4>
        <p class="text-muted">{{ __('Instrucciones por módulo para IA') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('update', $prompt)
        <a href="{{ route('prompt.edit', $prompt) }}" class="btn btn-primary waves-effect waves-light"><i class="ti ti-edit me-1"></i>{{ __('Editar prompt') }}</a>
        @endcan
        <a href="{{ route('prompt-list') }}" class="btn btn-label-secondary waves-effect waves-light"><i class="ti ti-arrow-left me-1"></i>{{ __('Volver al listado') }}</a>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ $prompt->section_label }}</h5>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label text-muted">{{ __('Módulo') }}</label>
                <p class="mb-0">{{ $prompt->module?->name ?? '—' }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">{{ __('Clave de sección') }}</label>
                <p class="mb-0"><code>{{ $prompt->section_key }}</code></p>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">{{ __('Orden') }}</label>
                <p class="mb-0">{{ $prompt->order }}</p>
            </div>
            <div class="col-12">
                <label class="form-label text-muted">{{ __('Estado') }}</label>
                <p class="mb-0">
                    @if($prompt->is_active)
                        <span class="badge rounded-pill bg-label-success">{{ __('Activo') }}</span>
                    @else
                        <span class="badge rounded-pill bg-label-secondary">{{ __('Inactivo') }}</span>
                    @endif
                </p>
            </div>
        </div>
        <hr>
        <div class="mb-3">
            <label class="form-label text-muted">{{ __('Instrucción para la IA') }}</label>
            <div class="border rounded p-3 bg-light"><pre class="mb-0 small text-dark" style="white-space: pre-wrap;">{{ $prompt->prompt_instruction }}</pre></div>
        </div>
        @if($prompt->helper_text)
        <div>
            <label class="form-label text-muted">{{ __('Texto de ayuda') }}</label>
            <div class="border rounded p-3 bg-light"><pre class="mb-0 small text-dark" style="white-space: pre-wrap;">{{ $prompt->helper_text }}</pre></div>
        </div>
        @endif
    </div>
</div>
@endsection
