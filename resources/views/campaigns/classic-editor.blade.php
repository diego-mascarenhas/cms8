@extends('layouts/layoutMaster')

@section('title', __('Editor clásico'))

@section('content')
<form action="#" method="POST">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Nuevo correo de secuencia') }}</h4>
            <p class="text-muted mb-0">{{ __('Tipo:') }} <span class="fw-semibold">{{ $selectedTypeLabel }}</span></p>
            @if ($selectedTitle !== '')
                <p class="text-muted mb-0">{{ __('Título de campaña:') }} <span class="fw-semibold">{{ $selectedTitle }}</span></p>
            @endif
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if ($selectedTemplateId > 0)
                <div class="alert alert-primary mb-4" role="alert">
                    {{ __('Plantilla seleccionada:') }} <strong>#{{ $selectedTemplateId }}</strong>
                </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="email-day">{{ __('Día') }}</label>
                    <input id="email-day" type="number" min="1" step="1" class="form-control" value="1" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email-time">{{ __('Hora') }}</label>
                    <select id="email-time" class="form-select">
                        @foreach ($sendTimes as $value => $label)
                            <option value="{{ $value }}" @selected($value === '840')>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <p class="text-muted mb-4">{{ __('Este correo se enviará según el día y hora configurados en la secuencia.') }}</p>

            <div class="mb-3">
                <label class="form-label" for="internal-title">{{ __('Título interno') }}</label>
                <input id="internal-title" type="text" class="form-control" value="{{ $selectedTitle }}" />
                <small class="text-muted">{{ __('Este título se usa en reportes y no se muestra a los destinatarios.') }}</small>
            </div>

            <div class="mb-3">
                <label class="form-label" for="subject">{{ __('Asunto') }}</label>
                <input id="subject" type="text" maxlength="140" class="form-control" />
                <small class="text-muted">{{ __('Máximo 140 caracteres.') }}</small>
            </div>

            <div class="mb-2">
                <label class="form-label" for="body">{{ __('Cuerpo') }}</label>
                <textarea id="body" class="form-control" rows="12" placeholder="{{ __('Escribe aquí el contenido del correo...') }}"></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
                <button type="submit" class="btn btn-label-secondary">{{ __('Guardar y agregar siguiente correo') }}</button>
            </div>
        </div>
    </div>
</form>
@endsection
