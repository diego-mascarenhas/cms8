@extends('layouts/layoutMaster')

@section('title', 'Editar reunión')

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
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Reuniones /</span> Editar</h4>
        <p class="text-muted">Cambiar el nombre de la reunión.</p>
    </div>
</div>

<div class="card">
    <form id="meeting-form" class="card-body" action="{{ route('meeting.update', $meeting) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-md-8">
                <x-input-general id="title" label="Título (*)" value="{{ old('title', $meeting->title) }}" maxlength="160" />
            </div>
        </div>
        <div class="pt-4">
            <button type="submit" class="btn btn-primary me-2">Guardar</button>
            <a href="{{ route('meeting.show', $meeting) }}" class="btn btn-label-secondary">Cancelar</a>
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
            }
        },
        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap5: new FormValidation.plugins.Bootstrap5({
                eleValidClass: '',
                rowSelector: '.col-md-8'
            }),
            submitButton: new FormValidation.plugins.SubmitButton(),
            defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
            autoFocus: new FormValidation.plugins.AutoFocus()
        }
    });
});
</script>
@endsection
