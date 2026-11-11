@extends('layouts/layoutMaster')

@section('title', isset($survey) ? __('Editar encuesta') : __('Nueva encuesta'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Encuestas') }}/</span> {{ isset($survey) ? __('Editar') : __('Crear') }}</h4>
        <p class="text-muted">{{ __('Cada pregunta se guarda como un campo del JSON de la encuesta') }}</p>
    </div>
</div>

<div class="card mb-4">
    <form class="card-body" method="POST" action="{{ isset($survey) ? route('survey.update', $survey) : route('survey.store') }}">
        @csrf
        @if (isset($survey))
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="title">{{ __('Título') }}</label>
                <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $survey->title ?? '') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="code">{{ __('Código') }}</label>
                <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $survey->code ?? '') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="expires_at">{{ __('Vencimiento') }}</label>
                <input type="date" class="form-control" id="expires_at" name="expires_at" value="{{ old('expires_at', isset($survey) && $survey->expires_at ? $survey->expires_at->format('Y-m-d') : '') }}">
            </div>
            <div class="col-md-9">
                <label class="form-label" for="subtitle">{{ __('Subtítulo') }}</label>
                <input type="text" class="form-control" id="subtitle" name="subtitle" value="{{ old('subtitle', $survey->subtitle ?? '') }}">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $survey->is_active ?? false))>
                    <label class="form-check-label" for="is_active">{{ __('Activa') }}</label>
                </div>
            </div>
        </div>

        <div class="mt-4" id="survey-fields">
            @foreach ($fields as $index => $field)
                @include('survey.field', ['index' => $index, 'field' => $field])
            @endforeach
        </div>

        <div class="pt-4 d-flex gap-2">
            <button type="button" class="btn btn-label-primary" id="add-survey-field">{{ __('Añadir pregunta') }}</button>
            <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
            <a href="{{ route('survey.index') }}" class="btn btn-label-secondary">{{ __('Cancelar') }}</a>
        </div>
    </form>
</div>

<template id="survey-field-template">
    @include('survey.field', ['index' => '__INDEX__', 'field' => ['key' => '', 'label' => '', 'help' => '', 'required' => false, 'anonymous' => false, 'for_certificate' => false, 'options' => [['label' => '', 'value' => '']]]])
</template>
@endsection

@section('page-script')
<script>
document.getElementById('add-survey-field').addEventListener('click', function () {
    var container = document.getElementById('survey-fields');
    var html = document.getElementById('survey-field-template').innerHTML;
    var index = container.querySelectorAll('.survey-field').length;
    container.insertAdjacentHTML('beforeend', html.replaceAll('__INDEX__', index));
});
</script>
@endsection
