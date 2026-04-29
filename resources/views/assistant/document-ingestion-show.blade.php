@extends('layouts/layoutMaster')

@section('title', 'Detalle de documento')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Detalle de interpretación</h4>
        <p class="text-muted">Campos detectados y texto leído del documento</p>
    </div>
    <div>
        @if(($data->classification_status ?? '') !== 'processed')
            <form action="{{ route('assistant.documents.mark-ingested', $data->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success">Ingresar documento</button>
            </form>
        @endif
        <form action="{{ route('assistant.documents.reprocess', $data->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">Procesar ahora</button>
        </form>
        <a href="{{ route('assistant.documents') }}" class="btn btn-label-secondary">Volver</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Vista previa</h5>
            </div>
            <div class="card-body">
                @if(!empty($data->file_url))
                    @php($mime = strtolower((string) ($data->mime_type ?? '')))
                    @if(str_starts_with($mime, 'image/'))
                        <img src="{{ $data->file_url }}" alt="{{ $data->file_name }}" class="img-fluid rounded">
                    @else
                        <iframe src="{{ $data->file_url }}" style="width: 100%; height: 70vh; border: 0;"></iframe>
                    @endif
                    <div class="mt-3">
                        <a href="{{ $data->file_url }}" target="_blank" rel="noopener" class="btn btn-label-primary btn-sm">Abrir archivo original</a>
                    </div>
                @else
                    <p class="text-muted mb-0">Este registro no tiene URL de archivo disponible.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Resultado de interpretación</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Origen</small>
                        <strong>{{ $data->source?->name ?: (((string) ($data->conversation?->channel ?? '')) === 'whatsapp' ? 'WhatsApp' : 'Chat') }}</strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Estado</small>
                        @php($statusValue = (string) ($data->classification_status ?? 'pending'))
                        <strong>{{ $statusValue === 'processed' ? 'ingresado' : $statusValue }}</strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Tipo detectado</small>
                        <strong>{{ $data->document_type ?? 'unknown' }}</strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Confianza</small>
                        <strong>{{ number_format((float) ($data->classification_confidence ?? 0), 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Campos detectados</h5>
            </div>
            <div class="card-body">
                @if(!empty($data->extracted_data) && is_array($data->extracted_data))
                    <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($data->extracted_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <p class="text-muted mb-0">Aún no hay campos estructurados detectados para este documento.</p>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Texto leído (OCR)</h5>
            </div>
            <div class="card-body">
                @if(!empty($data->ocr_text))
                    <pre class="mb-0" style="white-space: pre-wrap;">{{ $data->ocr_text }}</pre>
                @else
                    <p class="text-muted mb-0">Todavía no hay texto OCR disponible para este documento.</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Metadata técnica</h5>
            </div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($data->classification_meta ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                @if(!empty($data->processing_error))
                    <hr>
                    <small class="text-muted d-block mb-1">Error de procesamiento</small>
                    <pre class="mb-0 text-danger" style="white-space: pre-wrap;">{{ $data->processing_error }}</pre>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
