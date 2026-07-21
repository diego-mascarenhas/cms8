@extends('layouts/layoutMaster')

@section('title', $automation->name)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Automations') }}/</span> {{ $automation->name }}
        </h4>
        <p class="text-muted">{{ __('Detalle del flujo omnichannel') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('update', $automation)
        <a href="{{ route('automation.edit', $automation) }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-edit me-1"></i>{{ __('Editar') }}
        </a>
        @endcan
        <a href="{{ route('automation-list') }}" class="btn btn-label-secondary waves-effect waves-light">
            {{ __('Volver') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Configuración') }}</h5>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Slug') }}</dt>
                    <dd class="col-sm-8"><code>{{ $automation->slug }}</code></dd>

                    <dt class="col-sm-4">{{ __('Prompt') }}</dt>
                    <dd class="col-sm-8">
                        @if($automation->resolvedEntryPromptKey())
                            <code>{{ $automation->resolvedEntryPromptKey() }}</code>
                        @else
                            <span class="text-muted">{{ __('Router automático (general)') }}</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">{{ __('Estado') }}</dt>
                    <dd class="col-sm-8">
                        @if($automation->is_active)
                            <span class="badge bg-label-success">{{ __('Activa') }}</span>
                        @else
                            <span class="badge bg-label-secondary">{{ __('Inactiva') }}</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">{{ __('Canales') }}</dt>
                    <dd class="col-sm-8">
                        @foreach(\App\Models\Automation::CHANNELS as $channel)
                            @if($automation->allowsChannel($channel))
                                <span class="badge bg-label-info me-1">{{ $channel }}</span>
                            @endif
                        @endforeach
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-4">
            <h5 class="card-header">{{ __('Embed / API pública') }}</h5>
            <div class="card-body">
                <label class="form-label">{{ __('Token público') }}</label>
                <input type="text" class="form-control form-control-sm mb-3" readonly value="{{ $automation->public_token }}">

                <p class="small text-muted mb-2">{{ __('Endpoint') }}</p>
                <code class="d-block small mb-3">POST {{ url('/api/embed/automation/'.$automation->public_token.'/chat') }}</code>

                <p class="small text-muted mb-2">{{ __('Widget (ejemplo)') }}</p>
                <pre class="bg-lighter p-3 rounded small mb-0" style="white-space: pre-wrap;">&lt;div data-humano-widget="assistant"&gt;&lt;/div&gt;
&lt;script&gt;
  window.HUMANO_WIDGETS_API_BASE = @json(url('/api/embed/automation/'.$automation->public_token));
&lt;/script&gt;
&lt;script src="@json(url('/js/humano-widgets.js'))" async&gt;&lt;/script&gt;</pre>
            </div>
        </div>

        <div class="card">
            <h5 class="card-header">{{ __('API de equipo') }}</h5>
            <div class="card-body">
                <p class="small text-muted mb-2">{{ __('Con el token de equipo, enviá automation_slug:') }}</p>
                <pre class="bg-lighter p-3 rounded small mb-0" style="white-space: pre-wrap;">POST {{ url('/api/team/assistant/chat') }}
{
  "message": "...",
  "automation_slug": "{{ $automation->slug }}"
}</pre>
            </div>
        </div>
    </div>
</div>
@endsection
