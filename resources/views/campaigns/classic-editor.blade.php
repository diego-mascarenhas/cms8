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

    @php
        $classicEditorCancelUrl = ($campaignId > 0)
            ? route('campaigns.show', $campaignId)
            : route('campaigns.index');
    @endphp

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">
                {{ $isSequenceCampaign ? __('Editar correo de secuencia') : __('Editar correo de la difusión') }}
            </h4>
            <p class="text-muted mb-0">
                {{ $isSequenceCampaign ? __('Nuevo paso o borrador de secuencia.') : __('Contenido del envío masivo (un correo o varios vinculados a la campaña).') }}
            </p>
            <p class="text-muted mb-0">{{ __('Tipo:') }} <span class="fw-semibold">{{ $selectedTypeLabel }}</span></p>
            @if ($selectedTitle !== '')
                <p class="text-muted mb-0">{{ __('Título de campaña:') }} <span class="fw-semibold">{{ $selectedTitle }}</span></p>
            @endif
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ $grapesEditorUrl }}" class="btn btn-label-secondary waves-effect">
                <i class="ti ti-external-link me-1"></i>{{ __('Abrir editor') }}
            </a>
            <button type="button" class="btn btn-label-danger waves-effect">{{ __('Eliminar') }}</button>
            <button type="submit" name="intent" value="save_next" class="btn btn-label-secondary waves-effect">{{ __('Guardar para después') }}</button>
        </div>
    </div>

    @if ($isSequenceCampaign)
        <div class="alert alert-warning d-flex mb-4" role="alert">
            <i class="ti ti-alert-triangle text-warning me-2 mt-1"></i>
            <div>
                <h6 class="alert-heading mb-1">{{ __('Edición incompleta') }}</h6>
                <p class="mb-0">{{ __('Este correo todavía no se está enviando a suscriptores de la secuencia. Termina la edición o presiona Guardar para habilitar el envío.') }}</p>
            </div>
        </div>
    @else
        <div class="alert alert-primary d-flex mb-4" role="alert">
            <i class="ti ti-info-circle text-primary me-2 mt-1"></i>
            <div>
                <h6 class="alert-heading mb-1">{{ __('Programación del envío masivo') }}</h6>
                <p class="mb-0">{{ __('El día y la hora del envío masivo no se definen en esta pantalla: configúralos al activar o programar el mensaje desde Mailer (ficha del mensaje y campaña). Esta vista es solo para redactar el correo.') }}</p>
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            @if ($selectedTemplateId > 0)
                <div class="alert alert-primary mb-4" role="alert">
                    {{ __('Plantilla seleccionada:') }} <strong>#{{ $selectedTemplateId }}</strong>
                </div>
            @endif

            @if ($isSequenceCampaign)
                <p class="text-muted small mb-4">
                    {{ __('El orden de pasos, esperas entre correos y condiciones (abrir/clic) se configuran en la línea de tiempo de la campaña, no con los campos de “día/hora” aquí. Este editor solo guarda el contenido del mensaje.') }}
                </p>
            @endif

            <div class="mb-3">
                <label class="form-label" for="internal-title">{{ __('Nombre de la campaña') }}</label>
                <input id="internal-title" name="internal_title" type="text" class="form-control" value="{{ $defaultInternalTitle }}" />
                <small class="text-muted">{{ __('Este nombre se usa en reportes y no se muestra a los destinatarios.') }}</small>
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

    @include('message.partials.email-template-content-preview', [
        'previewHtml' => $defaultBody,
        'grapesEditorUrl' => $grapesEditorUrl,
        'templateLabel' => ($selectedTemplateName ?? '') !== '' ? $selectedTemplateName : null,
        'messageId' => ($messageId ?? 0) > 0 ? $messageId : null,
        'previewFrameId' => 'body-preview-frame',
        'parentSyncsPreview' => true,
    ])

    <div class="mb-4">
        <label class="form-label visually-hidden" for="body-editor">{{ __('Cuerpo') }}</label>
        <div id="body-editor-container" class="d-none">
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
        <textarea id="body" name="body" class="d-none"></textarea>
        <textarea id="body-template" class="d-none">{{ $defaultBodyTemplate }}</textarea>
    </div>

    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 pt-2">
        <button type="submit" name="intent" value="save" class="btn btn-primary waves-effect waves-light">{{ __('Guardar') }}</button>
        <button type="button" class="btn btn-label-secondary waves-effect" onclick="location.href='{{ $classicEditorCancelUrl }}'">{{ __('Cancel') }}</button>
    </div>
</form>
@endsection
