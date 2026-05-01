@extends('layouts/layoutMaster')

@section('title', __('Editar campaña'))

@php
    $timezones = [
        'UTC' => '(GMT+0:00) UTC',
        'Europe/Madrid' => '(GMT+2:00) Madrid',
        'Europe/London' => '(GMT+1:00) London',
        'America/New_York' => '(GMT-4:00) America/New_York',
        'America/Chicago' => '(GMT-5:00) America/Chicago',
        'America/Los_Angeles' => '(GMT-7:00) America/Los_Angeles',
    ];
@endphp

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Cerrar') }}"></button>
    </div>
@endif
<form action="{{ route('campaigns.update', $campaign) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Configuración de secuencia de correo') }}</h4>
            <p class="text-muted">{{ __('Edita y configura la secuencia de campaña seleccionada.') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('campaigns.index') }}" class="btn btn-label-secondary waves-effect waves-light">
                <i class="ti ti-arrow-left me-1"></i>{{ __('Volver') }}
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Detalles de la secuencia') }}</h5>
            <p class="text-muted mb-0">{{ __('Edita los detalles de la secuencia de correos.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="internal-title">{{ __('Título interno') }}</label>
                    <input
                        id="internal-title"
                        name="title"
                        type="text"
                        class="form-control mb-2 @error('title') is-invalid @enderror"
                        value="{{ old('title', $campaign->name) }}"
                    />
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">
                        {{ __('Este título es interno para reportes y no se muestra a los destinatarios.') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Exclusiones de la secuencia') }}</h5>
            <p class="text-muted mb-0">{{ __('Deja de enviar correos cuando se cumpla una de estas reglas.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="exclude-offers">{{ __('No enviar correos a suscriptores que compraron estas ofertas') }}</label>
                        <select id="exclude-offers" class="form-select" multiple>
                            <option>{{ __('Plan anual') }}</option>
                            <option>{{ __('Curso premium') }}</option>
                            <option>{{ __('Paquete de coaching') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="exclude-forms">{{ __('No enviar correos a suscriptores que completaron estos formularios') }}</label>
                        <select id="exclude-forms" class="form-select" multiple>
                            <option>{{ __('Registro de webinar') }}</option>
                            <option>{{ __('Checkout de upsell') }}</option>
                            <option>{{ __('Formulario de feedback') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Horario de envío') }}</h5>
            <p class="text-muted mb-0">{{ __('Configura la zona horaria predeterminada usada por esta secuencia.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="send-time-zone">{{ __('Zona horaria predeterminada') }}</label>
                    <select id="send-time-zone" name="send_time_zone" class="form-select @error('send_time_zone') is-invalid @enderror">
                        @foreach ($timezones as $value => $label)
                            <option value="{{ $value }}" @selected($value === $storedTimezone)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('send_time_zone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8 offset-lg-4">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="submit" class="btn btn-primary waves-effect waves-light">{{ __('Guardar') }}</button>
                <button type="button" class="btn btn-label-secondary waves-effect waves-light" onclick="location.href='{{ route('campaigns.index') }}'">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
</form>
@endsection
