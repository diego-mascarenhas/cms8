@props([
    'previewHtml',
    'grapesEditorUrl',
    'templateLabel' => null,
    'messageId' => null,
    'previewFrameId' => null,
    'parentSyncsPreview' => false,
    'templateHashedId' => null,
    'duplicateFormId' => null,
    'duplicateModalId' => null,
])

@php
    $iframeId = $previewFrameId ?: 'email-template-preview-'.\Illuminate\Support\Str::random(10);
    $dupModalDomId = $duplicateModalId ?? 'email-template-duplicate-modal';
    $defaultDuplicateTemplateName = filled($templateLabel)
        ? \Illuminate\Support\Str::limit(rtrim($templateLabel).' ('.__('app.email_template_copy_suffix').')', 75, '')
        : __('app.email_template_duplicate_default_name');
@endphp

<div class="card mb-4 email-template-content-preview">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 py-3">
        <h5 class="mb-0">{{ __('Contenido del correo') }}</h5>
        <div class="d-flex flex-wrap gap-2">
            @if ($messageId)
                @php
                    $emailTestSendModalDomId = 'email-test-send-modal-'.$messageId;
                @endphp
                <button
                    type="button"
                    class="btn btn-label-secondary"
                    data-bs-toggle="modal"
                    data-bs-target="#{{ $emailTestSendModalDomId }}"
                    aria-controls="{{ $emailTestSendModalDomId }}"
                >
                    <i class="ti ti-send me-1"></i>{{ __('Enviar correo de prueba') }}
                </button>
            @else
                <span class="btn btn-label-secondary disabled" title="{{ __('Disponible después de guardar el mensaje') }}">
                    <i class="ti ti-send me-1"></i>{{ __('Enviar correo de prueba') }}
                </span>
            @endif
                @if (filled($grapesEditorUrl) && $grapesEditorUrl !== '#')
                    <a
                        href="{{ $grapesEditorUrl }}"
                        class="btn btn-primary"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <i class="ti ti-external-link me-1"></i>{{ __('Abrir editor visual') }}
                    </a>
                @else
                    <span
                        class="btn btn-primary disabled"
                        role="button"
                        tabindex="-1"
                        title="{{ __('Select a template with a visual editor to open it.') }}"
                    >
                        <i class="ti ti-external-link me-1"></i>{{ __('Abrir editor visual') }}
                    </span>
                @endif
                @if (filled($templateHashedId) && filled($duplicateFormId))
                    <button
                        type="button"
                        class="btn btn-label-primary waves-effect"
                        data-bs-toggle="modal"
                        data-bs-target="#{{ $dupModalDomId }}"
                    >
                        <i class="ti ti-copy me-1"></i>{{ __('app.email_template_duplicate_button') }}
                    </button>
                @endif
        </div>
    </div>
    <div class="card-body">
        @if ($messageId)
            @include('message.partials.email-test-send-modal', ['messageId' => $messageId])
        @endif

        @if ($templateLabel)
            <p class="text-muted small mb-3">{{ __('Plantilla:') }} <span class="fw-semibold">{{ $templateLabel }}</span></p>
        @endif

        <div class="mb-3">
            <div class="border rounded overflow-hidden">
                <iframe
                    id="{{ $iframeId }}"
                    title="{{ __('Vista previa del contenido del correo') }}"
                    class="w-100 border-0"
                    style="min-height: 560px; background: #fff;"
                    src="about:blank"
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
        </div>
    </div>
</div>

@if (filled($templateHashedId) && filled($duplicateFormId))
    @push('modals')
        <div
            class="modal fade"
            id="{{ $dupModalDomId }}"
            tabindex="-1"
            aria-labelledby="{{ $dupModalDomId }}-title"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="{{ $dupModalDomId }}-title">{{ __('app.email_template_duplicate_modal_title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cerrar') }}"></button>
                    </div>
                    <div class="modal-body">
                        <label for="{{ $dupModalDomId }}-name" class="form-label">{{ __('app.email_template_duplicate_modal_name_label') }}</label>
                        <input
                            type="text"
                            class="form-control @error('duplicate_template_name') is-invalid @enderror"
                            id="{{ $dupModalDomId }}-name"
                            name="duplicate_template_name"
                            form="{{ $duplicateFormId }}"
                            required
                            minlength="3"
                            maxlength="75"
                            value="{{ old('duplicate_template_name', $defaultDuplicateTemplateName) }}"
                            autocomplete="off"
                        >
                        @error('duplicate_template_name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @if ($messageId)
                            <input type="hidden" name="message_id" form="{{ $duplicateFormId }}" value="{{ $messageId }}">
                        @endif
                        <p class="text-muted small mb-0 mt-2">
                            @if ($messageId)
                                {{ __('app.email_template_duplicate_modal_hint') }}
                            @else
                                {{ __('app.email_template_duplicate_modal_hint_draft') }}
                            @endif
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary waves-effect" data-bs-dismiss="modal">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" form="{{ $duplicateFormId }}" class="btn btn-primary waves-effect waves-light">
                            {{ __('app.email_template_duplicate_modal_submit') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endpush

    @if ($errors->has('duplicate_template_name') || $errors->has('message_id'))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function ()
                {
                    var modalEl = document.getElementById(@json($dupModalDomId));
                    if (! modalEl || typeof bootstrap === 'undefined' || ! bootstrap.Modal)
                    {
                        return;
                    }
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                });
            </script>
        @endpush
    @endif
@endif
