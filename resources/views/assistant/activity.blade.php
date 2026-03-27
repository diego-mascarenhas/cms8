@extends('layouts/layoutMaster')

@section('title', 'Actividad de IA')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-script')
    <script>
        $(function () {
            function initDatePickers() {
                $('.flatpickr-date-range').flatpickr({
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    altInput: true,
                    altFormat: 'd-m-Y',
                    locale: 'es',
                    monthSelectorType: 'static'
                });
            }

            function loadFlatpickrLocale(locale, callback) {
                if (locale === 'en') {
                    callback();
                    return;
                }

                if (flatpickr.l10ns && flatpickr.l10ns[locale]) {
                    flatpickr.localize(flatpickr.l10ns[locale]);
                    callback();
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/' + locale + '.js';
                script.onload = function () {
                    if (flatpickr.l10ns && flatpickr.l10ns[locale]) {
                        flatpickr.localize(flatpickr.l10ns[locale]);
                    }
                    callback();
                };
                script.onerror = callback;
                document.head.appendChild(script);
            }

            loadFlatpickrLocale('es', initDatePickers);

            const activityTable = $('#assistant-activity-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('assistant.activity.data') }}',
                    data: function (d) {
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },
                columns: [
                    {
                        data: null,
                        name: 'created_at',
                        render: function (row) {
                            const human = row.date_human ? '<small class="text-muted">' + row.date_human + '</small>' : '';

                            return '<div class="d-flex flex-column"><span>' + (row.date_display || '') + '</span>' + human + '</div>';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function (row) {
                            const email = row.user_email ? '<small class="text-muted">' + row.user_email + '</small>' : '';

                            return '<div class="d-flex flex-column"><span>' + (row.user_name || 'Desconocido') + '</span>' + email + '</div>';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function (row) {
                            return '<div class="d-flex flex-column"><span>' + (row.conversation_title || 'Sin título') + '</span><small class="text-muted">' + (row.conversation_id || '') + '</small></div>';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function (row) {
                            return '<div class="d-flex flex-column"><span>' + (row.model_name || '') + '</span><small class="text-muted">' + (row.provider_name || '') + '</small></div>';
                        }
                    },
                    {
                        data: 'prompt_tokens_value',
                        className: 'text-end',
                        render: function (value) {
                            return Number(value || 0).toLocaleString();
                        }
                    },
                    {
                        data: 'completion_tokens_value',
                        className: 'text-end',
                        render: function (value) {
                            return Number(value || 0).toLocaleString();
                        }
                    },
                    {
                        data: 'total_tokens_value',
                        className: 'text-end',
                        render: function (value) {
                            return Number(value || 0).toLocaleString();
                        }
                    },
                    {
                        data: 'estimated_cost_usd',
                        className: 'text-end',
                        render: function (value) {
                            return '$' + Number(value || 0).toFixed(6);
                        }
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
                    emptyTable: 'No se encontró actividad del asistente para este rango.'
                }
            });

            $('#assistant-activity-filters').on('submit', function (event) {
                event.preventDefault();
                activityTable.ajax.reload();
            });
        });
    </script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Actividad de IA</h4>
        <p class="text-muted">Conversaciones del asistente del equipo, uso de tokens, modelo y costos estimados</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Mensajes del asistente</small>
                <h4 class="mb-0">{{ \App\Helpers\Helpers::formatCompactNumber($totalMessages) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Tokens totales</small>
                <h4 class="mb-0">{{ \App\Helpers\Helpers::formatCompactNumber($totalTokens) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Costo estimado (USD)</small>
                <h4 class="mb-0">${{ number_format($totalEstimatedCostUsd, 6) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h5 class="mb-0">Mensajes de conversaciones</h5>
            <small class="text-muted">Proveedor/modelo por defecto: {{ $defaultProvider }} / {{ $defaultModel }}</small>
        </div>
    </div>
    <div class="card-body">
        <form id="assistant-activity-filters" method="GET" action="{{ route('assistant.activity') }}" class="row g-3 align-items-end">
            <div class="col-sm-4 col-md-3">
                <div class="form-group">
                    <label for="start_date" class="form-label mb-1">Desde</label>
                    <input type="text" id="start_date" name="start_date" class="form-control input flatpickr-date-range" value="{{ $startDate }}" autocomplete="off">
                </div>
            </div>
            <div class="col-sm-4 col-md-3">
                <div class="form-group">
                    <label for="end_date" class="form-label mb-1">Hasta</label>
                    <input type="text" id="end_date" name="end_date" class="form-control input flatpickr-date-range" value="{{ $endDate }}" autocomplete="off">
                </div>
            </div>
            <div class="col-sm-4 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary waves-effect waves-light">Aplicar</button>
            </div>
        </form>
        <div class="table-responsive mt-3">
            <table id="assistant-activity-table" class="table table-hover border-top">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Conversación</th>
                    <th>Modelo</th>
                    <th class="text-end">Entrada</th>
                    <th class="text-end">Salida</th>
                    <th class="text-end">Tokens totales</th>
                    <th class="text-end">USD estimado</th>
                </tr>
            </thead>
            <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection
