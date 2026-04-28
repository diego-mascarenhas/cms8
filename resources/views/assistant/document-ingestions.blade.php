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
            const documentPreviewModal = new bootstrap.Modal(document.getElementById('documentPreviewModal'));

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
                            const name = value || 'Sin nombre';
                            if (row.file_url) {
                                const safeUrl = String(row.file_url).replace(/"/g, '&quot;');
                                const safeName = String(name).replace(/"/g, '&quot;');
                                const safeMime = String(row.mime_type || '').replace(/"/g, '&quot;');
                                return '<div class="d-flex flex-column">' +
                                    '<a href="#" class="document-preview-link" data-url="' + safeUrl + '" data-name="' + safeName + '" data-mime="' + safeMime + '">' + name + '</a>' +
                                    '<small class="text-muted">' + row.file_url + '</small>' +
                                    '</div>';
                            }

                            return '<div class="d-flex flex-column"><span>' + name + '</span></div>';
                        }
                    },
                    {
                        data: 'document_type',
                        className: 'text-center',
                        render: function (value) {
                            const safe = value || 'unknown';
                            const labels = {
                                business_card: 'Tarjeta',
                                invoice: 'Factura',
                                payment_proof: 'Comprobante de pago',
                                unknown: 'No clasificado'
                            };
                            return '<span class="badge bg-label-primary">' + (labels[safe] || 'No clasificado') + '</span>';
                        }
                    },
                    {
                        data: 'classification_status',
                        className: 'text-center',
                        render: function (value) {
                            const status = value || 'pending';
                            const map = {
                                pending: 'secondary',
                                classified: 'success',
                                needs_review: 'warning',
                                processed: 'info',
                                failed: 'danger'
                            };
                            const labels = {
                                pending: 'Pendiente',
                                classified: 'Clasificado',
                                needs_review: 'Revisión',
                                processed: 'Procesado',
                                failed: 'Fallido'
                            };
                            const color = map[status] || 'secondary';
                            return '<span class="badge bg-label-' + color + '">' + (labels[status] || 'Pendiente') + '</span>';
                        }
                    },
                    {
                        data: 'confidence_value',
                        className: 'text-center',
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

            $('#document-ingestions-table').on('click', '.document-preview-link', function (event) {
                event.preventDefault();
                const url = this.getAttribute('data-url') || '';
                const name = this.getAttribute('data-name') || 'Documento';
                const mime = (this.getAttribute('data-mime') || '').toLowerCase();
                if (!url) return;

                const titleEl = document.getElementById('documentPreviewModalLabel');
                const bodyEl = document.getElementById('documentPreviewModalBody');
                const externalEl = document.getElementById('documentPreviewExternal');
                titleEl.textContent = name;
                externalEl.setAttribute('href', url);

                if (mime.startsWith('image/')) {
                    bodyEl.innerHTML = '<img src="' + url + '" alt="' + name + '" class="img-fluid rounded">';
                } else if (mime.includes('pdf')) {
                    bodyEl.innerHTML = '<iframe src="' + url + '" style="width:100%;height:70vh;border:0;"></iframe>';
                } else {
                    bodyEl.innerHTML = '<iframe src="' + url + '" style="width:100%;height:70vh;border:0;"></iframe>';
                }

                documentPreviewModal.show();
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
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Confianza</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-labelledby="documentPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentPreviewModalLabel">Documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="documentPreviewModalBody"></div>
            <div class="modal-footer">
                <a href="#" target="_blank" rel="noopener" id="documentPreviewExternal" class="btn btn-label-primary">Abrir en nueva pestaña</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection
