@extends('layouts/layoutMaster')

@section('title', __('Importar configuración'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Importar') }}</h4>
        <p class="text-muted">{{ __('Pegá o subí un JSON exportado de embudo, automatización o prompt. La cabecera humano_export indica a qué pertenece.') }}</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-4">
    <h5 class="card-header">{{ __('JSON de export Humano') }}</h5>
    <form class="card-body" action="{{ route('humano.import.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="file">{{ __('Archivo .json (opcional)') }}</label>
            <input type="file" class="form-control" id="file" name="file" accept=".json,application/json,text/plain">
        </div>
        <div class="mb-3">
            <label class="form-label" for="json">{{ __('O pegá el JSON') }}</label>
            <textarea class="form-control font-monospace" id="json" name="json" rows="16" placeholder='{ "humano_export": { "type": "funnel", "belongs_to": "Embudo", ... } }'>{{ old('json') }}</textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('Importar') }}</button>
        </div>
    </form>
</div>
@endsection
