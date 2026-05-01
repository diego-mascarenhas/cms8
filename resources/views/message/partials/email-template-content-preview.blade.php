@props([
    'previewHtml',
    'grapesEditorUrl',
    'templateLabel' => null,
    'messageId' => null,
])

@php
    $previewFrameId = 'email-template-preview-'.\Illuminate\Support\Str::random(10);
@endphp

<div class="card mb-4 email-template-content-preview">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
            <label class="form-label mb-0">{{ __('Contenido del correo') }}</label>
            <div class="d-flex flex-wrap gap-2">
                @if ($messageId)
                    <button type="button" class="btn btn-label-secondary" onclick="testSend({{ $messageId }})">
                        <i class="ti ti-send me-1"></i>{{ __('Enviar correo de prueba') }}
                    </button>
                @else
                    <span class="btn btn-label-secondary disabled" title="{{ __('Disponible después de guardar el mensaje') }}">
                        <i class="ti ti-send me-1"></i>{{ __('Enviar correo de prueba') }}
                    </span>
                @endif
                <a href="{{ $grapesEditorUrl }}" class="btn btn-primary">
                    <i class="ti ti-external-link me-1"></i>{{ __('Abrir editor visual') }}
                </a>
            </div>
        </div>

        @if ($templateLabel)
            <p class="text-muted small mb-3">{{ __('Plantilla:') }} <span class="fw-semibold">{{ $templateLabel }}</span></p>
        @endif

        <div class="mb-3">
            <label class="form-label">{{ __('Cuerpo') }}</label>
            <div
                class="position-relative border rounded overflow-hidden"
                onmouseenter="this.querySelector('[data-email-preview-overlay]').classList.remove('d-none')"
                onmouseleave="this.querySelector('[data-email-preview-overlay]').classList.add('d-none')"
            >
                <iframe
                    id="{{ $previewFrameId }}"
                    title="{{ __('Vista previa del contenido del correo') }}"
                    class="w-100 border-0"
                    style="min-height: 560px; background: #fff;"
                    src="about:blank"
                ></iframe>
                <script>
                    (function ()
                    {
                        var frame = document.getElementById(@json($previewFrameId));
                        if (! frame)
                        {
                            return;
                        }
                        frame.srcdoc = @json($previewHtml);
                    })();
                </script>
                <div
                    data-email-preview-overlay
                    class="position-absolute top-0 start-0 w-100 h-100 d-none d-flex align-items-center justify-content-center pe-none"
                    style="background: rgba(33, 37, 41, 0.55);"
                >
                    <a href="{{ $grapesEditorUrl }}" class="btn btn-dark pe-auto">
                        {{ __('Editar contenido') }}
                    </a>
                </div>
            </div>
            <small class="text-muted d-block mt-2">{{ __('Pasa el cursor sobre el contenido para editarlo.') }}</small>
        </div>
    </div>
</div>
