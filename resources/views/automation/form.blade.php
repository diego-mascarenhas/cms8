@extends('layouts/layoutMaster')

@php
    $kind = $kind ?? ($automation->kind ?? \App\Enums\AutomationKind::Action);
    $isFunnel = $kind === \App\Enums\AutomationKind::Funnel;
    $listRoute = $kind->listRouteName();
    $listLabel = $isFunnel ? __('Embudos') : __('Automatizaciones');
@endphp

@section('title', isset($automation) ? __('Editar') : ($isFunnel ? __('Crear embudo') : __('Crear automatización')))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ $listLabel }}/</span>
            {{ isset($automation) ? __('Editar') : __('Crear') }}
        </h4>
        <p class="text-muted">
            {{ $isFunnel
                ? __('Flujo conversacional con salidas a automatizaciones')
                : __('Acción reutilizable (p. ej. crear cita, contacto o tarea)') }}
        </p>
    </div>
    @if(isset($automation))
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('view', $automation)
        <a href="{{ route($kind->showRouteName(), $automation) }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-eye me-1"></i>{{ __('Ver') }}
        </a>
        @endcan
        @if($automation->isFunnel())
        @can('update', $automation)
        <a href="{{ route('funnel.flow', $automation) }}" class="btn btn-success waves-effect waves-light">
            <i class="ti ti-sitemap me-1"></i>{{ __('Editar embudo') }}
        </a>
        @endcan
        @endif
        @can('delete', $automation)
        <form action="{{ route($kind->destroyRouteName(), $automation) }}" method="POST" class="d-inline btn-delete-form">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger waves-effect waves-light btn-delete">
                <i class="ti ti-trash me-1"></i>{{ __('Eliminar') }}
            </button>
        </form>
        @endcan
    </div>
    @endif
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ isset($automation) ? __('Editar') : ($isFunnel ? __('Nuevo embudo') : __('Nueva automatización')) }}</h5>
    <form class="card-body" action="{{ isset($automation) ? route($kind->updateRouteName(), $automation) : route('automation.store') }}" method="POST">
        @csrf
        @if(isset($automation))
            @method('PUT')
        @else
            <input type="hidden" name="kind" value="{{ $kind->value }}">
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">{{ __('Nombre') }} (*)</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                    value="{{ old('name', $automation->name ?? '') }}" maxlength="255" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="slug">{{ __('Slug') }}</label>
                <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug"
                    value="{{ old('slug', $automation->slug ?? '') }}" maxlength="255" placeholder="soporte-web">
                <div class="form-text">{{ __('Identificador para la API. Si lo dejás vacío se genera desde el nombre.') }}</div>
                @error('slug')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-8">
                <label class="form-label" for="entry_prompt_key">{{ __('Prompt de entrada') }}</label>
                <select name="entry_prompt_key" id="entry_prompt_key" class="form-select @error('entry_prompt_key') is-invalid @enderror">
                    <option value="">{{ __('Router automático (general)') }}</option>
                    @foreach($promptOptions as $option)
                        <option value="{{ $option['key'] }}" @selected(old('entry_prompt_key', $automation->entry_prompt_key ?? '') === $option['key'])>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('Vacío = el asistente clasifica el mensaje con el prompt general.') }}</div>
                @error('entry_prompt_key')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                        @checked(old('is_active', $automation->is_active ?? true))>
                    <label class="form-check-label" for="is_active">{{ __('Activa') }}</label>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">{{ __('Canales') }}</label>
                <div class="row g-2">
                    @foreach(\App\Models\Automation::CHANNELS as $channel)
                        @php
                            $checked = old('channels.'.$channel, $channelDefaults[$channel] ?? false);
                        @endphp
                        <div class="col-md-4 col-lg-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="channel_{{ $channel }}"
                                    name="channels[{{ $channel }}]" value="1" @checked($checked)>
                                <label class="form-check-label" for="channel_{{ $channel }}">{{ ucfirst($channel) }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="col-12">
                <label class="form-label" for="welcome_message">{{ __('Mensaje de bienvenida (embed)') }}</label>
                <textarea class="form-control @error('settings.welcome_message') is-invalid @enderror" id="welcome_message"
                    name="settings[welcome_message]" rows="3">{{ old('settings.welcome_message', $automation->settings['welcome_message'] ?? '') }}</textarea>
                @error('settings.welcome_message')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            @if(($kind ?? \App\Enums\AutomationKind::Action) === \App\Enums\AutomationKind::Funnel)
            <div class="col-12">
                <label class="form-label" for="entry_aliases">{{ __('Palabras para disparar (aliases)') }}</label>
                <input type="text" class="form-control @error('settings.entry_aliases') is-invalid @enderror" id="entry_aliases"
                    name="settings[entry_aliases]"
                    value="{{ old('settings.entry_aliases', isset($automation) ? implode(', ', $automation->settings['entry_aliases'] ?? []) : '') }}"
                    placeholder="{{ __('ej: embudo, estrategia, embudo de operaciones') }}">
                <div class="form-text">{{ __('Separá con comas. El usuario puede escribir cualquiera de estas palabras en WhatsApp o en el asistente para entrar a este embudo.') }}</div>
                @error('settings.entry_aliases')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @endif

            @if(isset($automation))
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="regenerate_token" name="regenerate_token" value="1">
                    <label class="form-check-label" for="regenerate_token">{{ __('Regenerar token público de embed') }}</label>
                </div>
                <div class="form-text">
                    {{ __('El token público permite incrustar el chat de esta automatización en una web sin login. Si lo regenerás, el token anterior deja de funcionar y habrá que actualizar el widget o la URL de embed.') }}
                </div>
            </div>
            @endif
        </div>

        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Guardar') }}</button>
                <a href="{{ route($listRoute) }}" class="btn btn-label-secondary">{{ __('Cancelar') }}</a>
            </div>
        </div>
    </form>
</div>
@endsection

@if(isset($automation))
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.btn-delete').forEach(function (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                var form = button.closest('form');
                Swal.fire({
                    title: @json(__('¿Estás seguro?')),
                    text: @json($isFunnel
                        ? __('Se eliminará este embudo y su flujo. Esta acción no se puede deshacer.')
                        : __('Se eliminará esta automatización. Esta acción no se puede deshacer.')),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: @json(__('Sí, eliminar')),
                    cancelButtonText: @json(__('Cancelar')),
                    customClass: {
                        confirmButton: 'btn btn-primary me-3',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                }).then(function (result) {
                    if (result.isConfirmed || result.value) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush
@endif
