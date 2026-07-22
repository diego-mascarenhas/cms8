@extends('layouts/layoutMaster')

@section('title', $automation->name)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Embudos') }}/</span> {{ $automation->name }}
        </h4>
        <p class="text-muted">{{ __('Vista del flujo: pasos y qué ocurre con cada respuesta (solo lectura)') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @if($automation->is_active && $automation->allowsChannel(\App\Models\Automation::CHANNEL_HUMANO))
        <button
            type="button"
            class="btn btn-primary waves-effect waves-light"
            id="funnel-try-assistant-btn"
            data-automation-id="{{ $automation->id }}"
            data-automation-slug="{{ $automation->slug }}"
        >
            <i class="ti ti-message-chatbot me-1"></i>{{ __('Probar en asistente') }}
        </button>
        @endif
        @can('update', $automation)
        <a href="{{ route('funnel.flow', $automation) }}" class="btn btn-success waves-effect waves-light">
            <i class="ti ti-sitemap me-1"></i>{{ __('Editar embudo') }}
        </a>
        <a href="{{ route('funnel.edit', $automation) }}" class="btn btn-label-primary waves-effect waves-light">
            <i class="ti ti-settings me-1"></i>{{ __('Configuración') }}
        </a>
        @endcan
        <a href="{{ route('funnel-list') }}" class="btn btn-label-secondary waves-effect waves-light">
            {{ __('Volver') }}
        </a>
    </div>
</div>

@php
    $welcome = is_array($automation->settings) ? ($automation->settings['welcome_message'] ?? null) : null;
@endphp

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @if($automation->is_active)
                        <span class="badge bg-label-success">{{ __('Activo') }}</span>
                    @else
                        <span class="badge bg-label-secondary">{{ __('Inactivo') }}</span>
                    @endif
                    <span class="badge bg-label-info"><code>{{ $automation->slug }}</code></span>
                    @foreach(\App\Models\Automation::CHANNELS as $channel)
                        @if($automation->allowsChannel($channel))
                            <span class="badge bg-label-primary">{{ $channel }}</span>
                        @endif
                    @endforeach
                </div>
                @if($welcome)
                    <p class="mb-0 text-muted"><strong>{{ __('Bienvenida') }}:</strong> {{ $welcome }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">{{ __('Resumen') }}</div>
                <div class="fw-medium">
                    {{ $automation->steps->count() }} {{ __('pasos') }}
                    ·
                    {{ $automation->steps->sum(fn ($s) => $s->transitions->count()) }} {{ __('salidas') }}
                </div>
            </div>
        </div>
    </div>
</div>

@if($automation->steps->isEmpty())
    <div class="alert alert-warning mb-0">
        {{ __('Este embudo aún no tiene pasos. Usá “Editar embudo” para diseñar el flujo.') }}
    </div>
@else
    <div class="row g-4">
        @foreach($automation->steps as $step)
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            @if($step->is_entry)
                                <span class="badge bg-primary">{{ __('Inicio') }}</span>
                            @else
                                <span class="badge bg-label-secondary">{{ __('Paso') }}</span>
                            @endif
                            <h5 class="mb-0">{{ $step->label }}</h5>
                        </div>
                        @if($step->resolvedPromptKey())
                            <code class="small">{{ $step->resolvedPromptKey() }}</code>
                        @endif
                    </div>
                    <div class="card-body">
                        @if(filled($step->instruction))
                            <p class="mb-3">{{ $step->instruction }}</p>
                        @else
                            <p class="mb-3 text-muted">{{ __('Sin instrucción específica en este paso.') }}</p>
                        @endif

                        @if($step->transitions->isEmpty())
                            <div class="alert alert-secondary mb-0 py-2">
                                {{ __('Sin salidas: el flujo puede terminar aquí o continuar según el prompt.') }}
                            </div>
                        @else
                            <h6 class="mb-2">{{ __('Si el usuario responde…') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Selección') }}</th>
                                            <th>{{ __('Tipo') }}</th>
                                            <th>{{ __('Coincide con') }}</th>
                                            <th>{{ __('Siguiente') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($step->transitions as $transition)
                                            <tr>
                                                <td class="fw-medium">{{ $transition->label ?: $transition->reply_type->label() }}</td>
                                                <td><span class="badge bg-label-info">{{ $transition->reply_type->label() }}</span></td>
                                                <td>
                                                    @if(filled($transition->match_value))
                                                        <code>{{ $transition->match_value }}</code>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($transition->toAutomation)
                                                        <span class="badge bg-label-success">
                                                            <i class="ti ti-robot me-1"></i>{{ __('Automatización') }}: {{ $transition->toAutomation->name }}
                                                        </span>
                                                    @elseif($transition->toStep)
                                                        <span class="badge bg-label-primary">
                                                            <i class="ti ti-arrow-right me-1"></i>{{ $transition->toStep->label }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-label-secondary">{{ __('Fin del flujo') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection

@section('page-script')
<script>
document.getElementById('funnel-try-assistant-btn')?.addEventListener('click', function () {
    const automationId = parseInt(this.dataset.automationId || '0', 10);
    const slug = this.dataset.automationSlug || '';
    const offcanvasEl = document.getElementById('assistant-offcanvas');
    if (offcanvasEl && window.bootstrap?.Offcanvas) {
        window.bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();
    }
    if (window.Livewire) {
        Livewire.dispatch('assistant-start-automation', { automationId, slug });
    }
});
</script>
@endsection
