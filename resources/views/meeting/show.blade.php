@extends('layouts/layoutMaster')

@section('title', $meeting->title)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Reuniones /</span> {{ $meeting->title }}</h4>
        <p class="text-muted">{{ $meeting->original_filename }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('meeting.index') }}" class="btn btn-label-secondary">Volver</a>
        <form action="{{ route('meeting.destroy', $meeting) }}" method="POST" onsubmit="return confirm('¿Eliminar esta reunión?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-label-danger">Eliminar</button>
        </form>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($meeting->status->value === 'pending' || $meeting->status->value === 'processing')
    <div class="alert alert-info">Se está transcribiendo el audio y separando las voces. Esta página se actualiza sola.</div>
@endif

@if ($meeting->status->value === 'failed')
    <div class="alert alert-danger">No se pudo transcribir. {{ $meeting->error }}</div>
@endif

@if ($meeting->status->value === 'ready')
    <div class="card">
        <div class="card-body d-flex flex-column gap-4">
            @forelse ($meeting->turns ?? [] as $turn)
                @php
                    $contact = isset($turn['contact_id']) ? ($contacts[$turn['contact_id']] ?? null) : null;
                    $speakerName = $contact
                        ? trim($contact->name.' '.($contact->surname ?? ''))
                        : ($turn['label'] ?? 'Persona');
                    $avatar = $contact
                        ? $contact->avatarUrl(40)
                        : \App\Helpers\AvatarHelper::generate($speakerName, 40);
                @endphp
                <div>
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center">
                            <span class="position-relative d-inline-flex flex-shrink-0 me-3">
                                <img src="{{ $avatar }}" alt="" class="rounded-circle" width="40" height="40" style="object-fit:cover">
                            </span>
                            <strong>{{ $speakerName }}</strong>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-label-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#link-speaker-modal"
                                data-speaker="{{ $turn['speaker'] ?? '' }}"
                            >{{ $contact ? 'Cambiar' : 'Vincular' }}</button>
                            <span class="text-muted">{{ gmdate('i:s', (int) ($turn['start_seconds'] ?? 0)) }}</span>
                        </div>
                    </div>
                    <p class="mb-0 mt-2">{{ $turn['text'] ?? '' }}</p>
                </div>
            @empty
                <p class="text-muted mb-0">No hay intervenciones para mostrar.</p>
            @endforelse
        </div>
    </div>

    <div class="modal fade" id="link-speaker-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('meeting.speakers.update', $meeting) }}" method="POST">
                    @csrf
                    <input type="hidden" name="speaker" id="speaker-key" value="">
                    <div class="modal-header">
                        <h5 class="modal-title">Vincular voz</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="speaker-contact">Persona</label>
                        <select id="speaker-contact" name="contact_id" class="form-select"></select>
                        <div class="form-text">El vínculo nombra esta voz en esta reunión. Una grabación nueva no la reconoce sola.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-label-secondary" name="contact_id" value="">Quitar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@section('page-script')
<script>
@if (in_array($meeting->status->value, ['pending', 'processing'], true))
setTimeout(function () { window.location.reload(); }, 8000);
@endif
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('link-speaker-modal');
    var select = $('#speaker-contact');
    if (!modal || !select.length) {
        return;
    }

    select.select2({
        dropdownParent: $('#link-speaker-modal'),
        width: '100%',
        placeholder: 'Buscar persona',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: @json(route('meeting.contacts')),
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data.results || [] };
            }
        },
        templateResult: function (state) {
            return speakerOption(state);
        },
        templateSelection: function (state) {
            return speakerOption(state);
        }
    });

    modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('speaker-key').value = button ? (button.getAttribute('data-speaker') || '') : '';
        select.val(null).trigger('change');
    });

    function speakerOption(state) {
        if (!state.id) {
            return state.text;
        }
        var label = $('<span>').text(state.text);
        if (!state.avatar) {
            return label;
        }
        return $('<span class="d-flex align-items-center">').append(
            $('<img>', { src: state.avatar, class: 'rounded-circle me-2', width: 32, height: 32 }).css('object-fit', 'cover'),
            label
        );
    }
});
</script>
@endsection
