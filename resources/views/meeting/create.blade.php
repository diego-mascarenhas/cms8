@extends('layouts/layoutMaster')

@section('title', 'Subir reunión')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Reuniones /</span> Subir audio</h4>
        <p class="text-muted">Las voces salen como Persona 1, Persona 2 y así, en el orden en que hablan.</p>
    </div>
</div>

<div class="card">
    <form id="meeting-form" class="card-body" action="{{ route('meeting.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
            <div class="col-md-8">
                <x-input-general id="title" label="Título (*)" value="{{ old('title') }}" maxlength="160" />
            </div>
            <div class="col-md-4">
                <x-input-select
                    id="language"
                    label="Idioma del audio"
                    :options="['es' => 'Español', 'en' => 'Inglés', 'pt' => 'Portugués']"
                    value="es"
                    :allow-clear="false"
                />
            </div>
            <div class="col-12">
                <label class="form-label" for="audio">Audio</label>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-label-primary" id="record-audio"><i class="ti ti-microphone me-1"></i>Grabar</button>
                    <button type="button" class="btn btn-danger d-none" id="stop-audio"><i class="ti ti-player-stop me-1"></i>Detener</button>
                    <span class="text-muted align-self-center" id="record-status"></span>
                </div>
                <audio id="record-preview" class="d-none w-100 mb-3" controls></audio>
                <input id="audio" name="audio" type="file" accept=".mp3,.wav,.m4a,.mp4,.webm,.ogg,audio/*" class="form-control @error('audio') is-invalid @enderror" required>
                @error('audio')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Grabá con el micrófono o subí un mp3, wav, m4a, mp4, webm u ogg. Hasta 500 MB. Una hora grabada desde aquí queda en unos 30 MB.</div>
            </div>
        </div>
        <div class="pt-4">
            <button type="submit" class="btn btn-primary me-2">Transcribir</button>
            <a href="{{ route('meeting.index') }}" class="btn btn-label-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('meeting-form');
    if (!form || typeof FormValidation === 'undefined') {
        return;
    }

    FormValidation.formValidation(form, {
        fields: {
            title: {
                validators: {
                    notEmpty: {
                        message: 'Indicá un título para la reunión.'
                    },
                    stringLength: {
                        max: 160,
                        message: 'El título no puede pasar de 160 caracteres.'
                    }
                }
            },
            audio: {
                validators: {
                    notEmpty: {
                        message: 'Grabá o subí el audio de la reunión.'
                    }
                }
            }
        },
        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap5: new FormValidation.plugins.Bootstrap5({
                eleValidClass: '',
                rowSelector: '.col-md-8, .col-12'
            }),
            submitButton: new FormValidation.plugins.SubmitButton(),
            defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
            autoFocus: new FormValidation.plugins.AutoFocus()
        }
    });
});
</script>
<script>
(function () {
    var recordBtn = document.getElementById('record-audio');
    var stopBtn = document.getElementById('stop-audio');
    var status = document.getElementById('record-status');
    var preview = document.getElementById('record-preview');
    var input = document.getElementById('audio');
    var form = document.getElementById('meeting-form');
    var recorder = null;
    var chunks = [];
    var activeStream = null;
    var timer = null;
    var startedAt = 0;
    var maxBytes = 475 * 1024 * 1024;

    function clock(totalSeconds) {
        var minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
        var rest = String(totalSeconds % 60).padStart(2, '0');
        return minutes + ':' + rest;
    }

    function megabytes(bytes) {
        return (bytes / (1024 * 1024)).toFixed(1).replace('.', ',');
    }

    if (form) {
        form.addEventListener('click', function (event) {
            var submit = event.target.closest('button[type="submit"]');
            var file = input.files && input.files[0];
            if (!submit || !file || file.size <= maxBytes) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            status.textContent = 'El audio pesa ' + megabytes(file.size) + ' MB y el servidor acepta hasta 500 MB.';
        }, true);
    }

    if (!navigator.mediaDevices || typeof MediaRecorder === 'undefined') {
        recordBtn.classList.add('d-none');
        status.textContent = 'Este navegador no puede grabar. Subí el archivo.';
        return;
    }

    recordBtn.addEventListener('click', function () {
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            activeStream = stream;
            var mime = '';
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mime = 'audio/webm;codecs=opus';
            } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                mime = 'audio/mp4';
            }
            var options = { audioBitsPerSecond: 64000 };
            if (mime) {
                options.mimeType = mime;
            }
            try {
                recorder = new MediaRecorder(stream, options);
            } catch (error) {
                recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
            }
            chunks = [];
            recorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) {
                    chunks.push(event.data);
                }
            };
            recorder.onstop = function () {
                var elapsed = Math.max(0, Math.round((Date.now() - startedAt) / 1000));
                var type = (recorder.mimeType || 'audio/webm').split(';')[0];
                var extension = type.indexOf('mp4') !== -1 ? 'm4a' : (type.indexOf('ogg') !== -1 ? 'ogg' : 'webm');
                var blob = new Blob(chunks, { type: type });
                if (activeStream) {
                    activeStream.getTracks().forEach(function (track) { track.stop(); });
                    activeStream = null;
                }
                var file = new File([blob], 'reunion.' + extension, { type: type });
                var transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                if (preview.src) {
                    URL.revokeObjectURL(preview.src);
                }
                preview.src = URL.createObjectURL(blob);
                preview.classList.remove('d-none');
                preview.onloadedmetadata = function () {
                    if (!isFinite(preview.duration)) {
                        preview.currentTime = 1e101;
                        preview.ontimeupdate = function () {
                            preview.ontimeupdate = null;
                            preview.currentTime = 0;
                        };
                    }
                };
                if (blob.size > maxBytes) {
                    status.textContent = 'La grabación pesa ' + megabytes(blob.size) + ' MB y el servidor acepta hasta 500 MB.';
                } else {
                    status.textContent = 'Grabación lista (' + clock(elapsed) + ', ' + megabytes(blob.size) + ' MB). Podés transcribirla.';
                }
            };
            recorder.start(1000);
            recordBtn.classList.add('d-none');
            stopBtn.classList.remove('d-none');
            startedAt = Date.now();
            timer = setInterval(function () {
                status.textContent = 'Grabando ' + clock(Math.floor((Date.now() - startedAt) / 1000));
            }, 500);
        }).catch(function () {
            status.textContent = 'No pude usar el micrófono. Revisá el permiso del navegador o subí el archivo.';
        });
    });

    stopBtn.addEventListener('click', function () {
        clearInterval(timer);
        if (recorder && recorder.state === 'recording') {
            recorder.requestData();
            recorder.stop();
        }
        stopBtn.classList.add('d-none');
        recordBtn.classList.remove('d-none');
    });
})();
</script>
@endsection
