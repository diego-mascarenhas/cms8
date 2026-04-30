@extends('layouts/layoutMaster')

@section('title', __('Editor clásico'))

@section('page-script')
<script>
    $(function ()
    {
        const syncBodyBeforeSubmit = function ()
        {
            const editor = $('#body-editor');
            const bodyInput = $('#body');
            const bodyTemplate = $('#body-template');
            if (editor.length && bodyInput.length && bodyTemplate.length)
            {
                const bodyContent = editor.html();
                const fullHtml = bodyTemplate.val().replace('__EMAIL_BODY__', bodyContent);
                bodyInput.val(fullHtml);
            }
        };

        $('form.campaign-classic-editor-form').on('submit', function ()
        {
            syncBodyBeforeSubmit();
        });

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

        const editor = $('#body-editor');
        const bodyInput = $('#body');
        const bodyTemplate = $('#body-template');
        const bodyPreviewFrame = $('#body-preview-frame');
        const bodyPreviewCard = $('#body-preview-card');
        const bodyPreviewOverlay = $('#body-preview-overlay');

        const syncBodyContent = function ()
        {
            const bodyContent = editor.html();
            const fullHtml = bodyTemplate.val().replace('__EMAIL_BODY__', bodyContent);

            bodyInput.val(fullHtml);
            bodyPreviewFrame.attr('srcdoc', fullHtml);
        };

        editor.on('input', function ()
        {
            syncBodyContent();
        });

        bodyPreviewCard.on('mouseenter', function ()
        {
            bodyPreviewOverlay.removeClass('d-none');
        });

        bodyPreviewCard.on('mouseleave', function ()
        {
            bodyPreviewOverlay.addClass('d-none');
        });

        syncBodyContent();
        updateCounter('#subject', '#subject-char-count');
        updateCounter('#preview_text', '#preview-char-count');
    });
</script>
@endsection

@section('content')
@if (session('status'))
    <div class="alert alert-success alert-dismissible mb-4" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Cerrar') }}"></button>
    </div>
@endif
<form action="{{ route('campaigns.classic-editor.store') }}" method="POST" class="mb-4 campaign-classic-editor-form">
    @csrf
    <input type="hidden" name="type" value="{{ $selectedType }}">
    <input type="hidden" name="title" value="{{ $selectedTitle }}">
    <input type="hidden" name="template_id" value="{{ $selectedTemplateId }}">
    <input type="hidden" name="campaign_id" value="{{ $campaignId }}">
    <input type="hidden" name="message_id" value="{{ $messageId }}">
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
            <a
                href="{{ $grapesEditorUrl }}"
                class="btn btn-label-secondary"
            >
                <i class="ti ti-external-link me-1"></i>{{ __('Abrir editor') }}
            </a>
            <button type="submit" name="intent" value="save" class="btn btn-primary">{{ __('Guardar') }}</button>
            <button type="submit" name="intent" value="save_next" class="btn btn-label-secondary">{{ __('Guardar para después') }}</button>
        </div>
    </div>

    <div class="alert alert-warning d-flex mb-4" role="alert">
        <i class="ti ti-alert-triangle text-warning me-2 mt-1"></i>
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
                <input id="internal-title" name="internal_title" type="text" class="form-control" value="{{ $defaultInternalTitle }}" />
                <small class="text-muted">{{ __('Este título se usa en reportes y no se muestra a los destinatarios.') }}</small>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="subject">{{ __('Asunto') }}</label>
                    <small class="text-muted"><span id="subject-char-count">0</span>/140 {{ __('caracteres') }}</small>
                </div>
                <input id="subject" name="subject" type="text" maxlength="140" class="form-control" value="{{ $defaultSubject }}" />
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="preview_text">{{ __('Texto de vista previa') }}</label>
                    <small class="text-muted"><span id="preview-char-count">0</span>/140 {{ __('caracteres') }}</small>
                </div>
                <input id="preview_text" name="preview_text" type="text" maxlength="140" class="form-control" value="{{ $defaultPreviewText }}" />
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
                    <a
                        href="{{ $grapesEditorUrl }}"
                        class="btn btn-primary"
                    >
                        <i class="ti ti-external-link me-1"></i>{{ __('Abrir editor visual') }}
                    </a>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="body-editor">{{ __('Cuerpo') }}</label>
                <div id="body-preview-card" class="position-relative border rounded overflow-hidden">
                    <iframe
                        id="body-preview-frame"
                        title="{{ __('Vista previa del contenido del correo') }}"
                        class="w-100 border-0"
                        style="min-height: 560px; background: #fff;"
                        srcdoc="{{ e($defaultBody) }}"
                    ></iframe>
                    <div
                        id="body-preview-overlay"
                        class="position-absolute top-0 start-0 w-100 h-100 d-none d-flex align-items-center justify-content-center pe-none"
                        style="background: rgba(33, 37, 41, 0.55);"
                    >
                        <a
                            id="body-edit-trigger"
                            href="{{ $grapesEditorUrl }}"
                            class="btn btn-dark pe-auto"
                        >
                            {{ __('Editar contenido') }}
                        </a>
                    </div>
                </div>

                <div id="body-editor-container" class="d-none mt-3">
                    <div
                        id="body-editor"
                        class="form-control overflow-auto"
                        contenteditable="true"
                        style="min-height: 330px;"
                    >{!! $defaultBodyContent !!}</div>
                    <div class="d-flex justify-content-end mt-2">
                        <button id="body-editor-done" type="button" class="btn btn-label-secondary">{{ __('Listo') }}</button>
                    </div>
                </div>

                <small class="text-muted d-block mt-2">{{ __('Pasa el cursor sobre el contenido para editarlo.') }}</small>
                <textarea id="body" name="body" class="d-none"></textarea>
                <textarea id="body-template" class="d-none">{{ $defaultBodyTemplate }}</textarea>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-label-danger">{{ __('Eliminar') }}</button>
        <button type="submit" name="intent" value="save" class="btn btn-primary">{{ __('Guardar') }}</button>
        <button type="submit" name="intent" value="save_next" class="btn btn-label-secondary">{{ __('Guardar para después') }}</button>
    </div>
</form>
@endsection
