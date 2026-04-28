@extends('layouts/layoutMaster')

@section('title', 'Documentos procesados')

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

            initDatePickers();

            const table = $('#document-ingestions-table').DataTable({
                processing: true,
                serverSide: true,
                paging: false,
                lengthChange: false,
                info: false,
                ajax: {
                    url: '{{ route('assistant.documents.data') }}',
                    data: function (d) {
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },
                columns: [
                    { data: 'date_display', name: 'created_at' },
                    { data: 'source_name', name: 'source.name' },
                    {
                        data: 'document_name',
                        orderable: false,
                        render: function (value, type, row) {
                            const url = row.file_url ? '<small class="text-muted">' + row.file_url + '</small>' : '';
                            return '<div class="d-flex flex-column"><span>' + (value || 'Sin nombre') + '</span>' + url + '</div>';
                        }
                    },
                    {
                        data: 'reception_note',
                        orderable: false,
                        render: function (value) {
                            const note = value || '';
                            if (note.toLowerCase().includes('sin url')) {
                                return '<span class="badge bg-label-warning">' + note + '</span>';
                            }

                            return '<span class="badge bg-label-success">' + note + '</span>';
                        }
                    },
                    {
                        data: 'document_type',
                        render: function (value) {
                            const safe = value || 'unknown';
                            return '<span class="badge bg-label-primary text-uppercase">' + safe + '</span>';
                        }
                    },
                    {
                        data: 'classification_status',
                        render: function (value) {
                            const status = value || 'pending';
                            const map = {
                                pending: 'secondary',
                                classified: 'success',
                                needs_review: 'warning',
                                processed: 'info',
                                failed: 'danger'
                            };
                            const color = map[status] || 'secondary';
                            return '<span class="badge bg-label-' + color + ' text-uppercase">' + status + '</span>';
                        }
                    },
                    {
                        data: 'confidence_value',
                        className: 'text-end',
                        render: function (value) {
                            return Number(value || 0).toFixed(2);
                        }
                    }
                ],
                order: [[0, 'desc']],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
                    emptyTable: 'No se encontró actividad de documentos en este rango.'
                }
            });

            $('#document-ingestions-filters').on('submit', function (event) {
                event.preventDefault();
                table.ajax.reload();
            });
        });
    </script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Documentos procesados</h4>
        <p class="text-muted">Listado de documentos entrantes clasificados por la plataforma</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Total documentos</small>
                <h4 class="mb-0">{{ \App\Helpers\Helpers::formatCompactNumber($totalDocuments) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Clasificados</small>
                <h4 class="mb-0 text-success">{{ \App\Helpers\Helpers::formatCompactNumber($classified) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Pendientes de revisión</small>
                <h4 class="mb-0 text-warning">{{ \App\Helpers\Helpers::formatCompactNumber($needsReview) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="document-ingestions-filters" method="GET" action="{{ route('assistant.documents') }}" class="d-flex flex-wrap align-items-end gap-2 mb-3">
            <div>
                <label for="start_date" class="form-label mb-1">Desde</label>
                <input type="text" id="start_date" name="start_date" class="form-control input flatpickr-date-range" value="{{ $startDate }}" autocomplete="off">
            </div>
            <div>
                <label for="end_date" class="form-label mb-1">Hasta</label>
                <input type="text" id="end_date" name="end_date" class="form-control input flatpickr-date-range" value="{{ $endDate }}" autocomplete="off">
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-primary waves-effect waves-light">Aplicar</button>
            </div>
        </form>
        <div class="table-responsive">
            <table id="document-ingestions-table" class="table table-hover border-top">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Origen</th>
                        <th>Documento</th>
                        <th>Recepción</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th class="text-end">Confianza</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection
