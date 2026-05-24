@props([
    'previewHtml',
    'grapesEditorUrl',
    'templateLabel' => null,
    'messageId' => null,
    'templateId' => null,
    'previewFrameId' => null,
    'parentSyncsPreview' => false,
    'templateHashedId' => null,
    'duplicateFormId' => null,
    'duplicateModalId' => null,
    'removeTemplateUrl' => null,
    'emailTestSendModalInline' => false,
    'useMailHtmlTextarea' => false,
    'mailHtmlTextareaValue' => '',
    'mailHtmlTextareaReadonly' => false,
])

@php
    $iframeId = $previewFrameId ?: 'email-template-preview-'.\Illuminate\Support\Str::random(10);
    $dupModalDomId = $duplicateModalId ?? 'email-template-duplicate-modal';
    $canEmailTestSend = $messageId || $templateId;
    $emailTestSendModalDomId = $messageId
        ? 'email-test-send-modal-'.$messageId
        : ($templateId ? 'email-test-send-modal-draft-'.$templateId : null);
@endphp

<div class="card mb-4 email-template-content-preview">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 py-3">
        <h5 class="mb-0">{{ __('Contenido del correo') }}</h5>
        <div class="d-flex flex-wrap gap-1 gap-sm-2">
            @if ($canEmailTestSend && $emailTestSendModalDomId)
                <button
                    type="button"
                    class="btn btn-sm btn-label-secondary waves-effect"
                    data-bs-toggle="modal"
                    data-bs-target="#{{ $emailTestSendModalDomId }}"
                    aria-controls="{{ $emailTestSendModalDomId }}"
                >
                    <i class="ti ti-send ti-sm me-1"></i>{{ __('app.message_email_test_send_button') }}
                </button>
            @else
                <span class="btn btn-sm btn-label-secondary disabled" title="{{ __('Disponible después de guardar el mensaje') }}">
                    <i class="ti ti-send ti-sm me-1"></i>{{ __('app.message_email_test_send_button') }}
                </span>
            @endif
                @if (filled($grapesEditorUrl) && $grapesEditorUrl !== '#')
                    <button
                        type="button"
                        class="btn btn-sm btn-primary waves-effect waves-light"
                        data-huma-open-visual-editor="1"
                        data-editor-url="{{ $grapesEditorUrl }}"
                        data-template-id="{{ (int) ($templateId ?? 0) }}"
                        data-message-id="{{ filled($messageId) ? (int) $messageId : '' }}"
                    >
                        <i class="ti ti-edit ti-sm me-1"></i>{{ __('app.message_visual_editor_button') }}
                    </button>
                @else
                    <span
                        class="btn btn-sm btn-primary disabled"
                        role="button"
                        tabindex="-1"
                        title="{{ __('Select a template with a visual editor to open it.') }}"
                    >
                        <i class="ti ti-edit ti-sm me-1"></i>{{ __('app.message_visual_editor_button') }}
                    </span>
                @endif
                @if ($useMailHtmlTextarea && ! $mailHtmlTextareaReadonly && filled($templateId))
                    <button
                        type="button"
                        class="btn btn-sm btn-label-warning waves-effect"
                        data-huma-update-template="1"
                        data-template-id="{{ (int) $templateId }}"
                        data-message-id="{{ filled($messageId) ? (int) $messageId : '' }}"
                    >
                        <i class="ti ti-device-floppy ti-sm me-1"></i>{{ __('app.email_template_update_button') }}
                    </button>
                @endif
                @if (filled($templateHashedId) && filled($duplicateFormId))
                    <button
                        type="button"
                        class="btn btn-sm btn-label-primary waves-effect"
                        data-bs-toggle="modal"
                        data-bs-target="#{{ $dupModalDomId }}"
                    >
                        <i class="ti ti-copy ti-sm me-1"></i>{{ __('app.email_template_duplicate_button') }}
                    </button>
                @endif
                @if (filled($removeTemplateUrl))
                    <a
                        href="{{ $removeTemplateUrl }}"
                        class="btn btn-sm btn-label-danger waves-effect"
                        title="{{ __('app.message_remove_mail_template_hint') }}"
                        data-message-remove-template-confirm="1"
                    >
                        <i class="ti ti-unlink ti-sm me-1"></i>{{ __('app.message_remove_mail_template') }}
                    </a>
                @endif
        </div>
    </div>
    <div class="card-body">
        @if ($messageId || $templateId)
            @include('message.partials.email-test-send-modal', [
                'messageId' => $messageId,
                'templateId' => $templateId,
                'inlineOnly' => $emailTestSendModalInline,
            ])
        @endif

        @if ($templateLabel)
            <p class="text-muted small mb-3">{{ __('Plantilla:') }} <span class="fw-semibold">{{ $templateLabel }}</span></p>
        @endif

        <div class="mb-3">
            @if ($useMailHtmlTextarea)
                <label class="form-label" for="message-template-html-quill-editor">{{ __('Contenido del correo') }}</label>
                {{-- JSON carrier: raw HTML inside <textarea> breaks on </textarea> and confuses the HTML parser. --}}
                <script type="application/json" id="message-template-html-initial-json">@json($mailHtmlTextareaValue)</script>
                <textarea
                    id="message-template-html-body"
                    name="template_html"
                    class="d-none"
                    spellcheck="false"
                    autocomplete="off"
                    @if ($mailHtmlTextareaReadonly) readonly @endif
                ></textarea>
                <div class="border rounded overflow-hidden bg-white message-template-quill-wrap" style="min-height: 320px;">
                    <div id="message-template-html-quill-editor" class="message-template-html-quill-root"></div>
                </div>
            @else
            <div class="border rounded overflow-hidden">
                <iframe
                    id="{{ $iframeId }}"
                    title="{{ __('Vista previa del contenido del correo') }}"
                    class="w-100 border-0"
                    style="min-height: 560px; background: #fff;"
                    src="about:blank"
                    data-email-template-preview-frame="1"
                ></iframe>
                @unless ($parentSyncsPreview)
                <script>
                    (function ()
                    {
                        var frame = document.getElementById(@json($iframeId));
                        if (! frame)
                        {
                            return;
                        }
                        frame.srcdoc = @json($previewHtml);
                    })();
                </script>
                @endunless
            </div>
            @endif
        </div>
    </div>
</div>
