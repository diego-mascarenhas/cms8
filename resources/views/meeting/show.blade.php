@extends('layouts/layoutMaster')

@section('title', $meeting->title)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Reuniones /</span> {{ $meeting->title }}</h4>
        <p class="text-muted">{{ $meeting->original_filename }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @if ($meeting->status->value === 'ready')
            <form action="{{ route('meeting.summary', $meeting) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-label-primary waves-effect">
                    <i class="ti ti-notes me-1"></i>Resumen
                </button>
            </form>
        @endif
        <a href="{{ route('meeting.index') }}" class="btn btn-label-secondary waves-effect">
            <i class="ti ti-arrow-left me-1"></i>Volver
        </a>
        <form action="{{ route('meeting.destroy', $meeting) }}" method="POST" onsubmit="return confirm('¿Eliminar esta reunión?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-label-danger waves-effect">
                <i class="ti ti-trash me-1"></i>Eliminar
            </button>
        </form>
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

@if ($meeting->status->value === 'pending' || $meeting->status->value === 'processing')
    <div class="alert alert-info">Se está transcribiendo el audio y separando las voces. Esta página se actualiza sola.</div>
@endif

@if ($meeting->status->value === 'failed')
    <div class="alert alert-danger">No se pudo transcribir. {{ $meeting->error }}</div>
@endif

@if ($meeting->status->value === 'ready')
    @php
        $summary = is_array($meeting->summary) ? $meeting->summary : null;
    @endphp
    @if ($summary)
        <div class="card mb-4">
            <h5 class="card-header">Resumen</h5>
            <div class="card-body">
                @if (($summary['synthesis'] ?? '') !== '')
                    <p class="mb-3">{{ $summary['synthesis'] }}</p>
                @endif
                @if (($summary['viewpoints'] ?? []) !== [])
                    <h6 class="mb-2">Puntos de vista</h6>
                    <ul class="mb-3">
                        @foreach ($summary['viewpoints'] as $viewpoint)
                            <li>
                                @if (($viewpoint['speaker'] ?? '') !== '')
                                    <strong>{{ $viewpoint['speaker'] }}.</strong>
                                @endif
                                {{ $viewpoint['view'] ?? '' }}
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (($summary['intention'] ?? '') !== '')
                    <h6 class="mb-2">Intención</h6>
                    <p class="mb-3">{{ $summary['intention'] }}</p>
                @endif
                @if (($summary['items'] ?? []) !== [])
                    <h6 class="mb-2">Para tratar</h6>
                    <ol class="mb-3">
                        @foreach ($summary['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ol>
                @endif
                @if (($summary['tasks'] ?? []) !== [])
                    <h6 class="mb-2">Tareas</h6>
                    <ul class="mb-0">
                        @foreach ($summary['tasks'] as $task)
                            <li>
                                {{ $task['title'] ?? '' }}
                                @if (($task['owner'] ?? '') !== '')
                                    <span class="text-muted">— {{ $task['owner'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

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
                    $linkLabel = $contact ? 'Cambiar' : 'Vincular';
                @endphp
                <div class="d-flex gap-3">
                    <span class="position-relative d-inline-flex flex-shrink-0">
                        <img src="{{ $avatar }}" alt="" class="rounded-circle" width="40" height="40" style="object-fit:cover">
                    </span>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <strong>{{ $speakerName }}</strong>
                            <div class="d-flex align-items-center gap-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-icon btn-text-secondary"
                                    title="{{ $linkLabel }}"
                                    aria-label="{{ $linkLabel }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#link-speaker-modal"
                                    data-speaker="{{ $turn['speaker'] ?? '' }}"
                                >
                                    <i class="ti {{ $contact ? 'ti-replace' : 'ti-link' }} ti-sm"></i>
                                </button>
                                <span class="text-muted">{{ gmdate('i:s', (int) ($turn['start_seconds'] ?? 0)) }}</span>
                            </div>
                        </div>
                        <p class="mb-0 mt-2">{{ $turn['text'] ?? '' }}</p>
                    </div>
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
                        <div class="form-text">El vínculo nombra esta voz en esta reunión. Persona 1 de otra grabación no es la misma persona.</div>
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
