@extends('layouts/layoutMaster')

@section('title', __('Editar campaña'))

@php
    $timezones = [
        'UTC' => '(GMT+0:00) UTC',
        'Europe/Madrid' => '(GMT+2:00) Madrid',
        'Europe/London' => '(GMT+1:00) London',
        'America/New_York' => '(GMT-4:00) America/New_York',
        'America/Chicago' => '(GMT-5:00) America/Chicago',
        'America/Los_Angeles' => '(GMT-7:00) America/Los_Angeles',
    ];
    $automationsList = array_values(is_array($storedAutomations ?? null) ? $storedAutomations : []);
@endphp

@section('content')
@if (session('success'))
    <div class="alert alert-success alert-dismissible mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Cerrar') }}"></button>
    </div>
@endif
<form action="{{ route('campaigns.update', $campaign) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Configuración de secuencia de correo') }}</h4>
            <p class="text-muted">{{ __('Edita y configura la secuencia de campaña seleccionada.') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('campaigns.index') }}" class="btn btn-label-secondary waves-effect waves-light">
                <i class="ti ti-arrow-left me-1"></i>{{ __('Volver') }}
            </a>
            <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Detalles de la secuencia') }}</h5>
            <p class="text-muted mb-0">{{ __('Edita los detalles de la secuencia de correos.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="internal-title">{{ __('Título interno') }}</label>
                    <input
                        id="internal-title"
                        name="title"
                        type="text"
                        class="form-control mb-2 @error('title') is-invalid @enderror"
                        value="{{ old('title', $campaign->name) }}"
                    />
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">
                        {{ __('Este título es interno para reportes y no se muestra a los destinatarios.') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Exclusiones de la secuencia') }}</h5>
            <p class="text-muted mb-0">{{ __('Deja de enviar correos cuando se cumpla una de estas reglas.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="exclude-offers">{{ __('No enviar correos a suscriptores que compraron estas ofertas') }}</label>
                        <select id="exclude-offers" class="form-select" multiple>
                            <option>{{ __('Plan anual') }}</option>
                            <option>{{ __('Curso premium') }}</option>
                            <option>{{ __('Paquete de coaching') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="exclude-forms">{{ __('No enviar correos a suscriptores que completaron estos formularios') }}</label>
                        <select id="exclude-forms" class="form-select" multiple>
                            <option>{{ __('Registro de webinar') }}</option>
                            <option>{{ __('Checkout de upsell') }}</option>
                            <option>{{ __('Formulario de feedback') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Horario de envío') }}</h5>
            <p class="text-muted mb-0">{{ __('Configura la zona horaria predeterminada usada por esta secuencia.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <label class="form-label" for="send-time-zone">{{ __('Zona horaria predeterminada') }}</label>
                    <select id="send-time-zone" name="send_time_zone" class="form-select @error('send_time_zone') is-invalid @enderror">
                        @foreach ($timezones as $value => $label)
                            <option value="{{ $value }}" @selected($value === $storedTimezone)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('send_time_zone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Orden y condiciones de envío') }}</h5>
            <p class="text-muted mb-0">{{ __('Define el orden de los mensajes, esperas entre pasos y si un paso depende de la apertura o clic del anterior.') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    @if (count($sequenceRows) === 0)
                        <p class="text-muted mb-0">
                            {{ __('Aún no hay mensajes vinculados a esta campaña. Añádelos desde el editor de secuencia.') }}
                        </p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Orden') }}</th>
                                        <th scope="col">{{ __('Mensaje') }}</th>
                                        <th scope="col">{{ __('Tipo') }}</th>
                                        <th scope="col">{{ __('Espera') }}</th>
                                        <th scope="col">{{ __('Condición') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sequenceRows as $idx => $row)
                                        @php
                                            $seqMessage = $campaign->messages->firstWhere('id', $row['message_id']);
                                        @endphp
                                        <tr>
                                            <td style="width: 6rem;">
                                                <input type="hidden" name="sequence[{{ $idx }}][message_id]" value="{{ $row['message_id'] }}">
                                                <input
                                                    type="number"
                                                    name="sequence[{{ $idx }}][sort_order]"
                                                    class="form-control form-control-sm @error('sequence.'.$idx.'.sort_order') is-invalid @enderror"
                                                    min="0"
                                                    max="10000"
                                                    value="{{ $row['sort_order'] }}"
                                                >
                                                @error('sequence.'.$idx.'.sort_order')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td>{{ $seqMessage?->name ?? '—' }}</td>
                                            <td>{{ $seqMessage?->type?->name ?? '—' }}</td>
                                            <td style="width: 8rem;">
                                                <input
                                                    type="number"
                                                    name="sequence[{{ $idx }}][delay_minutes_after_previous]"
                                                    class="form-control form-control-sm @error('sequence.'.$idx.'.delay_minutes_after_previous') is-invalid @enderror"
                                                    min="0"
                                                    placeholder="—"
                                                    value="{{ $row['delay_minutes_after_previous'] }}"
                                                >
                                                @error('sequence.'.$idx.'.delay_minutes_after_previous')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </td>
                                            <td style="min-width: 12rem;">
                                                <select
                                                    name="sequence[{{ $idx }}][condition_preset]"
                                                    class="form-select form-select-sm @error('sequence.'.$idx.'.condition_preset') is-invalid @enderror"
                                                >
                                                    <option value="none" @selected(($row['condition_preset'] ?? 'none') === 'none')>{{ __('Sin condición (siempre tras la espera)') }}</option>
                                                    <option value="opened" @selected(($row['condition_preset'] ?? '') === 'opened')>{{ __('Solo si abrió el paso anterior') }}</option>
                                                    <option value="clicked" @selected(($row['condition_preset'] ?? '') === 'clicked')>{{ __('Solo si hizo clic en el paso anterior') }}</option>
                                                </select>
                                                @error('sequence.'.$idx.'.condition_preset')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small mb-0 mt-2">
                            {{ __('Los canales futuros (WhatsApp, redes sociales, etc.) usan el mismo tipo de mensaje; la entrega se resolverá según el tipo elegido al crear el mensaje.') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <h5 class="mb-1">{{ __('Automatizaciones') }}</h5>
            <p class="text-muted mb-0">{{ __('Acciones adicionales disparadas por la secuencia (otro canal o mensaje concreto).') }}</p>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Automatizaciones') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        {{ __('Configura disparadores y el canal (tipo de mensaje). Opcionalmente enlaza un mensaje ya creado en tu equipo.') }}
                    </p>

                    <div id="automations-rows" class="mb-3"></div>

                    <button type="button" class="btn btn-sm btn-label-primary waves-effect waves-light" id="btn-add-automation">
                        <i class="ti ti-plus ti-sm me-1"></i>{{ __('Agregar automatización') }}
                    </button>

                    @error('automations')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <template id="automation-row-template">
        <div class="automation-row border rounded p-3 mb-3" data-automation-row>
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted small">{{ __('Automatización') }} <span data-automation-index></span></span>
                <button
                    type="button"
                    class="btn btn-link text-danger automation-remove p-0 m-0 border-0 shadow-none lh-1 text-decoration-none"
                    title="{{ __('Quitar') }}"
                >
                    <i class="ti ti-trash ti-sm"></i>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small mb-0">{{ __('Disparador') }}</label>
                    <select class="form-select form-select-sm" data-field="trigger" name="">
                        <option value="">{{ __('Selecciona…') }}</option>
                        <option value="after_previous_sent">{{ __('Tras enviar el paso anterior') }}</option>
                        <option value="if_opened_previous">{{ __('Si abrió el paso anterior') }}</option>
                        <option value="if_not_opened_previous">{{ __('Si no abrió el paso anterior') }}</option>
                        <option value="delay_after_enrollment">{{ __('Tras el alta en la secuencia') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">{{ __('Espera (h)') }}</label>
                    <input type="number" class="form-control form-control-sm" data-field="delay_hours" name="" min="0" max="8760" placeholder="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">{{ __('Canal (tipo)') }}</label>
                    <select class="form-select form-select-sm" data-field="channel_type_id" name="">
                        <option value="">{{ __('Selecciona…') }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-0">{{ __('Mensaje (opcional)') }}</label>
                    <select class="form-select form-select-sm" data-field="message_id" name="">
                        <option value="">{{ __('Ninguno') }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-0">{{ __('Notas') }}</label>
                    <input type="text" class="form-control form-control-sm" data-field="notes" name="" maxlength="500" placeholder="{{ __('Uso interno') }}">
                </div>
            </div>
        </div>
    </template>

    <hr class="my-4" />

    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
    </div>
</form>
@endsection

@section('page-script')
<script>
(function () {
    var container = document.getElementById('automations-rows');
    var template = document.getElementById('automation-row-template');
    var btnAdd = document.getElementById('btn-add-automation');
    if (!container || !template || !btnAdd) return;

    var messageTypes = @json($messageTypesJson);
    var automationMessages = @json($automationMessagesJson);
    var initialRows = @json($automationsList);
    var nextIndex = 0;

    function fillChannelOptions(select) {
        messageTypes.forEach(function (t) {
            var opt = document.createElement('option');
            opt.value = String(t.id);
            opt.textContent = t.name;
            select.appendChild(opt);
        });
    }

    function fillMessageOptions(select) {
        automationMessages.forEach(function (m) {
            var opt = document.createElement('option');
            opt.value = String(m.id);
            opt.textContent = m.name + ' (' + m.type_name + ')';
            select.appendChild(opt);
        });
    }

    function wireRow(row) {
        row.querySelector('.automation-remove').addEventListener('click', function () {
            row.remove();
            renumberRows();
        });
    }

    function renumberRows() {
        var rows = container.querySelectorAll('[data-automation-row]');
        rows.forEach(function (row, i) {
            var label = row.querySelector('[data-automation-index]');
            if (label) label.textContent = String(i + 1);
            row.querySelectorAll('[data-field]').forEach(function (el) {
                var field = el.getAttribute('data-field');
                el.name = 'automations[' + i + '][' + field + ']';
            });
        });
        nextIndex = rows.length;
    }

    function addRow(data) {
        data = data || {};
        var frag = template.content.cloneNode(true);
        var row = frag.querySelector('[data-automation-row]');
        var trig = row.querySelector('[data-field="trigger"]');
        var delay = row.querySelector('[data-field="delay_hours"]');
        var chan = row.querySelector('[data-field="channel_type_id"]');
        var msg = row.querySelector('[data-field="message_id"]');
        var notes = row.querySelector('[data-field="notes"]');
        fillChannelOptions(chan);
        fillMessageOptions(msg);
        if (data.trigger) trig.value = data.trigger;
        if (data.delay_hours !== undefined && data.delay_hours !== null) delay.value = String(data.delay_hours);
        if (data.channel_type_id) chan.value = String(data.channel_type_id);
        if (data.message_id) msg.value = String(data.message_id);
        if (data.notes) notes.value = data.notes;
        container.appendChild(row);
        wireRow(row);
        renumberRows();
    }

    btnAdd.addEventListener('click', function () {
        addRow({});
    });

    if (initialRows.length > 0) {
        initialRows.forEach(function (r) { addRow(r); });
    }
})();
</script>
@endsection
