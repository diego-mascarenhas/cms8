@extends('layouts/layoutMaster')

@section('title', 'Resumen')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ $meeting->title }} /</span> Resumen</h4>
        <p class="text-muted">Síntesis, acuerdos y lo que conviene hacer después.</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('meeting.show', $meeting) }}" class="btn btn-label-secondary waves-effect">
            <i class="ti ti-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success d-flex align-items-center" role="alert">
        <span class="alert-icon text-success me-2">
            <i class="ti ti-check ti-xs"></i>
        </span>
        {{ session('status') }}
    </div>
@endif

@if ($errors->has('summary'))
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <span class="alert-icon text-danger me-2">
            <i class="ti ti-ban ti-xs"></i>
        </span>
        {{ $errors->first('summary') }}
    </div>
@endif

<div class="card mb-4">
    <h5 class="card-header">Enlace público</h5>
    <div class="card-body">
        <p class="text-muted">Quien tenga este enlace ve lo que se conversó, sin entrar a la plataforma.</p>
        <div class="input-group">
            <input id="public-meeting-link" type="text" class="form-control" readonly value="{{ $publicUrl }}">
            <button type="button" class="btn btn-outline-primary" id="copy-public-link">Copiar</button>
        </div>
    </div>
</div>

@php
    $summary = is_array($meeting->summary) ? $meeting->summary : null;
@endphp

@if ($summary)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Resumen</h5>
            <form action="{{ route('meeting.summary', $meeting) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-label-primary">Actualizar</button>
            </form>
        </div>
        <div class="card-body">
            @include('meeting.partials.summary', ['summary' => $summary])
        </div>
    </div>
@else
    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-3">Todavía no hay una síntesis de esta reunión.</p>
            <form action="{{ route('meeting.summary', $meeting) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-notes me-1"></i>Generar resumen
                </button>
            </form>
        </div>
    </div>
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('copy-public-link');
    var input = document.getElementById('public-meeting-link');
    if (!button || !input) {
        return;
    }
    button.addEventListener('click', function () {
        input.select();
        navigator.clipboard.writeText(input.value).then(function () {
            button.textContent = 'Copiado';
        });
    });
});
</script>
@endsection
