@extends('layouts/layoutMaster')

@section('title', __('Create Notification'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
@endsection

@section('content')
<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Notifications') }}/</span> {{ __('Create') }}</h4>
        <p class="text-muted">{{ __('Create a new notification') }}</p>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Notification Details') }}</h5>
    <form class="card-body" action="{{ route('notification.store') }}" method="POST">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label for="type_id" class="form-label">Tipo de notificación (*)</label>
                <select class="form-select @error('type_id') is-invalid @enderror" id="type_id" name="type_id" required>
                    <option value="">Seleccionar tipo</option>
                    @foreach($types as $type)
                        <option value="{{ $type['id'] }}" {{ old('type_id') == $type['id'] ? 'selected' : '' }}>
                            {{ $type['name'] }}
                        </option>
                    @endforeach
                </select>
                @error('type_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="contact_id" class="form-label">Contacto (*)</label>
                <select class="form-select @error('contact_id') is-invalid @enderror" id="contact_id" name="contact_id" required>
                    <option value="">Seleccionar contacto</option>
                    @foreach($contacts as $contact)
                        <option value="{{ $contact->id }}" {{ old('contact_id') == $contact->id ? 'selected' : '' }}>
                            {{ $contact->name }} {{ $contact->surname }} ({{ $contact->email }})
                        </option>
                    @endforeach
                </select>
                @error('contact_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="reference" class="form-label">Referencia</label>
                <input type="text" class="form-control @error('reference') is-invalid @enderror"
                       id="reference" name="reference" value="{{ old('reference') }}"
                       placeholder="ID del proyecto, tarea, etc.">
                @error('reference')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Opcional: ID del proyecto, tarea u otra referencia</div>
            </div>

            <div class="col-md-6">
                <label for="send_immediately" class="form-label">Acción</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="send_immediately" name="send_immediately" value="1" {{ old('send_immediately') ? 'checked' : '' }}>
                    <label class="form-check-label" for="send_immediately">
                        Enviar inmediatamente
                    </label>
                </div>
                <div class="form-text">Si no se marca, la notificación se guardará como borrador</div>
            </div>

            <div class="col-12">
                <label for="subject" class="form-label">Asunto (*)</label>
                <input type="text" class="form-control @error('subject') is-invalid @enderror"
                       id="subject" name="subject" value="{{ old('subject') }}" required>
                @error('subject')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="message" class="form-label">Mensaje (*)</label>
                <div id="message-editor" style="height: 200px;"></div>
                <textarea class="form-control d-none @error('message') is-invalid @enderror"
                          id="message" name="message" required>{{ old('message') }}</textarea>
                @error('message')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                <button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('notification-list') }}'">Cancelar</button>
                <button type="button" class="btn btn-info ms-auto" id="loadTemplate">
                    <i class="ti ti-template me-1"></i>Cargar plantilla
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Initialize Select2
    $('#contact_id, #type_id').select2({
        placeholder: function() {
            return $(this).data('placeholder');
        }
    });

    // Initialize Quill editor
    var quill = new Quill('#message-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                ['blockquote', 'code-block'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'size': ['small', false, 'large', 'huge'] }],
                [{ 'color': [] }, { 'background': [] }],
                ['clean']
            ]
        }
    });

    // Set initial content if exists
    @if(old('message'))
        quill.setText({!! json_encode(old('message')) !!});
    @endif

    // Update hidden textarea when form is submitted
    $('form').on('submit', function() {
        $('#message').val(quill.getText());
    });

    // Load template functionality
    $('#loadTemplate').on('click', function() {
        var typeId = $('#type_id').val();
        var contactId = $('#contact_id').val();
        var reference = $('#reference').val();

        if (!typeId) {
            alert('Por favor selecciona un tipo de notificación primero');
            return;
        }

        $.ajax({
            url: '{{ route("notification.get-template") }}',
            type: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                type_id: typeId,
                contact_id: contactId,
                reference: reference
            },
            success: function(response) {
                if (response.success) {
                    $('#subject').val(response.subject);
                    quill.setText(response.message);

                    if (!response.is_customizable) {
                        quill.disable();
                        $('#subject').prop('readonly', true);
                    } else {
                        quill.enable();
                        $('#subject').prop('readonly', false);
                    }
                }
            },
            error: function() {
                alert('Error al cargar la plantilla');
            }
        });
    });

    // Auto-load template when type changes
    $('#type_id').on('change', function() {
        var typeId = $(this).val();
        if (typeId && !$('#subject').val() && !quill.getText().trim()) {
            $('#loadTemplate').click();
        }
    });
});
</script>
@endsection
