@props([
    'previewHtml',
    'grapesEditorUrl',
    'templateLabel' => null,
    'messageId' => null,
    'previewFrameId' => null,
    'parentSyncsPreview' => false,
])

@php
    $iframeId = $previewFrameId ?: 'email-template-preview-'.\Illuminate\Support\Str::random(10);
@endphp

<div class="card mb-4 email-template-content-preview">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
            <label class="form-label mb-0">{{ __('Contenido del correo') }}</label>
            <div class="d-flex flex-wrap gap-2">
                @if ($messageId)
                    @php
                        $emailTestSendModalDomId = 'email-test-send-modal-'.$messageId;
                    @endphp
                    {{-- Waves.js binds btn-label-* and blocks data-bs-toggle modals — open via Bootstrap JS --}}
                    <button
                        type="button"
                        class="btn btn-label-secondary"
                        onclick="openEmailTestSendModal(@json($emailTestSendModalDomId))"
                    >
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
