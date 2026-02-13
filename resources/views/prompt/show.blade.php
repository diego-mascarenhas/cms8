@extends('layouts/layoutMaster')

@section('title', $prompt->section_label)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Prompts') }}/</span> {{ $prompt->section_label }}</h4>
        <p class="text-muted">{{ __('Instrucciones por módulo para IA') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('update', $prompt)
        <a href="{{ route('prompt.edit', $prompt) }}" class="btn btn-primary waves-effect waves-light"><i class="ti ti-edit me-1"></i>{{ __('Editar prompt') }}</a>
        @endcan
        <a href="{{ route('prompt-list') }}" class="btn btn-label-secondary waves-effect waves-light"><i class="ti ti-arrow-left me-1"></i>{{ __('Volver al listado') }}</a>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ $prompt->section_label }}</h5>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label text-muted">{{ __('Módulo') }}</label>
                <p class="mb-0">{{ $prompt->module?->name ?? '—' }}</p>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">{{ __('Clave de sección') }}</label>
                <p class="mb-0"><code>{{ $prompt->section_key }}</code></p>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">{{ __('Orden') }}</label>
                <p class="mb-0">{{ $prompt->order }}</p>
            </div>
            <div class="col-12">
                <label class="form-label text-muted">{{ __('Estado') }}</label>
                <p class="mb-0">
                    @if($prompt->is_active)
                        <span class="badge rounded-pill bg-label-success">{{ __('Activo') }}</span>
                    @else
                        <span class="badge rounded-pill bg-label-secondary">{{ __('Inactivo') }}</span>
                    @endif
                </p>
            </div>
        </div>
        <hr>
        <div class="mb-3">
            <label class="form-label text-muted">{{ __('Instrucción para la IA') }}</label>
            <div class="border rounded p-3 bg-light"><pre class="mb-0 small text-dark" style="white-space: pre-wrap;">{{ $prompt->prompt_instruction }}</pre></div>
        </div>
        @if($prompt->helper_text)
        <div>
            <label class="form-label text-muted">{{ __('Texto de ayuda') }}</label>
            <div class="border rounded p-3 bg-light"><pre class="mb-0 small text-dark" style="white-space: pre-wrap;">{{ $prompt->helper_text }}</pre></div>
        </div>
        @endif
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Probar prompt') }}</h5>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label" for="testInput">{{ __('Escribe aquí tu propuesta o texto a mejorar') }}</label>
            <textarea id="testInput" class="form-control" name="test_message" rows="8" placeholder="{{ __('Escribe aquí tu propuesta...') }}"></textarea>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="promptPreviewImage">{{ __('Subir imagen (opcional)') }}</label>
                <input type="file" id="promptPreviewImage" class="form-control" name="image" accept="image/*">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="promptPreviewAudio">{{ __('Subir audio (opcional)') }}</label>
                <input type="file" id="promptPreviewAudio" class="form-control" name="audio" accept="audio/*,.mp3,.wav,.m4a,.webm,.ogg">
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="translateTo">{{ __('Traducir respuesta a') }}</label>
                <select id="translateTo" class="form-select" name="translate_to">
                    <option value="">{{ __('No traducir') }}</option>
                    <option value="es">Español</option>
                    <option value="en">English</option>
                    <option value="fr">Français</option>
                    <option value="de">Deutsch</option>
                    <option value="it">Italiano</option>
                    <option value="pt">Português</option>
                    <option value="ja">日本語</option>
                    <option value="zh">中文</option>
                    <option value="ru">Русский</option>
                    <option value="ar">العربية</option>
                </select>
                <small class="text-muted">{{ __('Si subes audio, la transcripción se traducirá o la respuesta será en este idioma.') }}</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('Respuesta en audio') }}</label>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="respondWithAudio" name="respond_with_audio" value="1">
                    <label class="form-check-label" for="respondWithAudio">{{ __('Recibir la respuesta en audio (TTS)') }}</label>
                </div>
                <div id="voiceIdWrap" class="mt-2" style="display: none;">
                    <label class="form-label" for="voiceId">{{ __('Tu voz (Voice ID de ElevenLabs)') }}</label>
                    <input type="text" id="voiceId" class="form-control form-control-sm" name="voice_id" placeholder="{{ __('Opcional: pega el ID de tu voz clonada') }}" maxlength="100">
                </div>
            </div>
        </div>
        <button type="button" id="btnGenerateSuggestion" class="btn btn-primary">
            <i class="ti ti-sparkles me-1"></i>{{ __('Generar sugerencia con IA') }}
        </button>
        <div id="promptPreviewResponse" class="mt-4" style="display: none;">
            <label class="form-label text-muted">{{ __('Respuesta de la IA') }}</label>
            <div id="promptPreviewLoader" class="text-muted small mb-2" style="display: none;"><span class="spinner-border spinner-border-sm me-1" role="status"></span>{{ __('Procesando...') }}</div>
            <div id="promptPreviewContent" class="border rounded p-3 bg-light"><pre class="mb-0 small text-dark" style="white-space: pre-wrap;"></pre></div>
            <div id="promptPreviewAudioPlayer" class="mt-3" style="display: none;">
                <label class="form-label text-muted">{{ __('Respuesta en audio') }}</label>
                <audio id="promptPreviewAudioEl" controls class="w-100"></audio>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('btnGenerateSuggestion');
    var testInput = document.getElementById('testInput');
    var imageInput = document.getElementById('promptPreviewImage');
    var audioInput = document.getElementById('promptPreviewAudio');
    var respondWithAudioCheck = document.getElementById('respondWithAudio');
    var translateToSelect = document.getElementById('translateTo');
    var voiceIdWrap = document.getElementById('voiceIdWrap');
    var voiceIdInput = document.getElementById('voiceId');
    var responseContainer = document.getElementById('promptPreviewResponse');
    var loader = document.getElementById('promptPreviewLoader');
    var content = document.getElementById('promptPreviewContent');
    var audioPlayerWrap = document.getElementById('promptPreviewAudioPlayer');
    var audioEl = document.getElementById('promptPreviewAudioEl');

    function hasFiles() {
        return (imageInput && imageInput.files && imageInput.files.length) ||
               (audioInput && audioInput.files && audioInput.files.length);
    }

    if (respondWithAudioCheck && voiceIdWrap) {
        respondWithAudioCheck.addEventListener('change', function() {
            voiceIdWrap.style.display = this.checked ? 'block' : 'none';
        });
    }

    if (btn && testInput) {
        btn.addEventListener('click', function() {
            var message = (testInput && testInput.value) ? testInput.value.trim() : '';
            if (!message && !hasFiles()) {
                alert('{{ __("Escribe algo, sube una imagen o un audio para probar el prompt.") }}');
                return;
            }
            responseContainer.style.display = 'block';
            loader.style.display = 'block';
            content.querySelector('pre').textContent = '';
            if (audioPlayerWrap) audioPlayerWrap.style.display = 'none';
            if (audioEl) { audioEl.pause(); audioEl.removeAttribute('src'); }
            btn.disabled = true;

            var url = '{{ route("prompt.preview", $prompt) }}';
            var csrf = '{{ csrf_token() }}';
            var body;
            var headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

            var translateTo = translateToSelect ? translateToSelect.value : '';
            var voiceId = voiceIdInput ? voiceIdInput.value.trim() : '';

            if (hasFiles() || (respondWithAudioCheck && respondWithAudioCheck.checked) || translateTo) {
                body = new FormData();
                body.append('_token', csrf);
                body.append('test_message', message);
                if (translateTo) body.append('translate_to', translateTo);
                if (respondWithAudioCheck && respondWithAudioCheck.checked) {
                    body.append('respond_with_audio', '1');
                    if (voiceId) body.append('voice_id', voiceId);
                }
                if (imageInput && imageInput.files && imageInput.files[0]) body.append('image', imageInput.files[0]);
                if (audioInput && audioInput.files && audioInput.files[0]) body.append('audio', audioInput.files[0]);
            } else {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify({ test_message: message, translate_to: translateTo || null });
            }

            fetch(url, { method: 'POST', headers: headers, body: body })
            .then(function(res) {
                var contentType = res.headers.get('content-type') || '';
                return res.text().then(function(text) {
                    if (contentType.indexOf('application/json') !== -1) {
                        try {
                            return { ok: res.ok, data: JSON.parse(text) };
                        } catch (e) {
                            return { ok: false, data: null, text: text };
                        }
                    }
                    return { ok: false, data: null, text: text, status: res.status };
                });
            })
            .then(function(result) {
                loader.style.display = 'none';
                btn.disabled = false;
                if (result.data) {
                    var data = result.data;
                    if (data.success) {
                        content.querySelector('pre').textContent = data.response || '';
                        if (data.audio_base64 && audioEl && audioPlayerWrap) {
                            audioEl.src = 'data:' + (data.audio_mime || 'audio/mpeg') + ';base64,' + data.audio_base64;
                            audioPlayerWrap.style.display = 'block';
                        }
                    } else {
                        content.querySelector('pre').textContent = '{{ __("Error") }}: ' + (data.message || '{{ __("No se pudo obtener la respuesta.") }}');
                    }
                } else {
                    var msg = '{{ __("El servidor respondió con un error. Recarga la página o inténtalo más tarde.") }}';
                    if (result.status === 419) msg = '{{ __("Sesión caducada. Recarga la página e inicia sesión de nuevo.") }}';
                    else if (result.status === 403) msg = '{{ __("No tienes permiso para esta acción.") }}';
                    else if (result.status === 500) msg = '{{ __("Error interno del servidor. Revisa la consola o los logs.") }}';
                    content.querySelector('pre').textContent = msg + (result.status ? ' (HTTP ' + result.status + ')' : '');
                }
            })
            .catch(function(err) {
                loader.style.display = 'none';
                btn.disabled = false;
                content.querySelector('pre').textContent = '{{ __("Error") }}: ' + (err.message || '{{ __("Error de conexión.") }}');
            });
        });
    }
});
</script>
@endpush
