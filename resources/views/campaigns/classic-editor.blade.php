@extends('layouts/layoutMaster')

@section('title', __('Editor clásico'))

@section('page-script')
<script>
    $(function ()
    {
        const updateCounter = function (inputSelector, counterSelector)
        {
            const currentLength = $(inputSelector).val().length;
            $(counterSelector).text(currentLength);
        };

        $('#subject').on('input', function ()
        {
            updateCounter('#subject', '#subject-char-count');
        });

        $('#preview_text').on('input', function ()
        {
            updateCounter('#preview_text', '#preview-char-count');
        });

        updateCounter('#subject', '#subject-char-count');
        updateCounter('#preview_text', '#preview-char-count');
    });
</script>
@endsection

@section('content')
<form action="#" method="POST" class="mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Editar correo de secuencia') }}</h4>
            <p class="text-muted mb-0">{{ __('Nuevo correo de secuencia') }}</p>
            <p class="text-muted mb-0">{{ __('Tipo:') }} <span class="fw-semibold">{{ $selectedTypeLabel }}</span></p>
            @if ($selectedTitle !== '')
                <p class="text-muted mb-0">{{ __('Título de campaña:') }} <span class="fw-semibold">{{ $selectedTitle }}</span></p>
            @endif
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="javascript:;" class="btn btn-label-secondary">
                <i class="ti ti-external-link me-1"></i>{{ __('Abrir editor') }}
            </a>
            <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
            <button type="submit" class="btn btn-label-secondary">{{ __('Guardar y agregar siguiente correo') }}</button>
        </div>
    </div>

    <div class="alert alert-warning d-flex mb-4" role="alert">
        <span class="alert-icon text-warning me-2">
            <i class="ti ti-alert-triangle"></i>
        </span>
        <div>
            <h6 class="alert-heading mb-1">{{ __('Edición incompleta') }}</h6>
            <p class="mb-0">{{ __('Este correo todavía no se está enviando a suscriptores de la secuencia. Termina la edición o presiona Guardar para habilitar el envío.') }}</p>
        </div>
    </div>

    <div class="card mb-4">
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

            <p class="text-muted mb-4">{{ __('Este correo se enviará a la hora seleccionada, 1 día después de que alguien se suscriba a esta secuencia.') }}</p>

            <div class="mb-3">
                <label class="form-label" for="internal-title">{{ __('Título interno') }}</label>
                <input id="internal-title" type="text" class="form-control" value="{{ $selectedTitle }}" />
                <small class="text-muted">{{ __('Este título se usa en reportes y no se muestra a los destinatarios.') }}</small>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="subject">{{ __('Asunto') }}</label>
                    <small class="text-muted"><span id="subject-char-count">0</span>/140 {{ __('caracteres') }}</small>
                </div>
                <input id="subject" type="text" maxlength="140" class="form-control" value="{{ __('Asunto') }}" />
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="preview_text">{{ __('Texto de vista previa') }}</label>
                    <small class="text-muted"><span id="preview-char-count">0</span>/140 {{ __('caracteres') }}</small>
                </div>
                <input id="preview_text" type="text" maxlength="140" class="form-control" />
                <small class="text-muted">{{ __('Texto que aparece después del asunto en la bandeja de entrada del destinatario.') }}</small>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
                <label class="form-label mb-0">{{ __('Contenido del correo') }}</label>
                <div class="d-flex flex-wrap gap-2">
                    <a href="javascript:;" class="btn btn-label-secondary">
                        <i class="ti ti-folder me-1"></i>{{ __('Guardar como plantilla') }}
                    </a>
                    <a href="javascript:;" class="btn btn-label-secondary">
                        <i class="ti ti-send me-1"></i>{{ __('Enviar correo de prueba') }}
                    </a>
                    <a href="javascript:;" class="btn btn-primary">
                        <i class="ti ti-external-link me-1"></i>{{ __('Abrir editor visual') }}
                    </a>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="body">{{ __('Cuerpo') }}</label>
                <textarea id="body" class="form-control" rows="14" placeholder="{{ __('Escribe aquí el contenido del correo...') }}"></textarea>
            </div>

            <div class="border rounded p-3 bg-lighter">
                <h6 class="mb-2">{{ __('Vista previa') }}</h6>
                <p class="text-muted mb-0">{{ __('Aquí se mostrará la vista previa del correo una vez que se conecte el editor visual.') }}</p>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-label-danger">{{ __('Eliminar') }}</button>
        <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
        <button type="submit" class="btn btn-label-secondary">{{ __('Guardar y agregar siguiente correo') }}</button>
    </div>
</form>
@endsection
