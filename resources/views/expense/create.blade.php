@extends('layouts/layoutMaster')

@section('title', 'Añadir gasto')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
@php
    $oldLines = old('lines', [
        [
            'concept' => '',
            'base_amount' => '0.00',
            'vat_percent' => '0',
            'retention_percent' => '0',
            'allocation_percent' => '100',
        ],
    ]);
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Gastos/</span> Crear</h4>
        <p class="text-muted">Registrar un nuevo gasto</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('expense.index') }}" class="btn btn-label-secondary">Volver a gastos</a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <form class="card-body" action="{{ route('expense.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="document_type" name="document_type" value="{{ old('document_type', 'invoice') }}">

        <div class="row g-3 mb-1">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($documentTypes as $documentTypeKey => $documentTypeLabel)
                        <button
                            type="button"
                            class="btn btn-sm {{ old('document_type', 'invoice') === $documentTypeKey ? 'btn-primary' : 'btn-outline-secondary' }} document-type-btn"
                            data-document-type="{{ $documentTypeKey }}"
                        >
                            {{ $documentTypeLabel }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <h5 class="mb-3">Factura de compra</h5>

        <div class="row g-3">
            <div class="col-lg-7">
                <label id="document-drop-zone" for="document_file" class="border rounded p-4 h-100 d-block position-relative overflow-hidden" style="border-style: dashed !important; cursor: pointer;">
                    <div id="document-file-meta" class="d-none position-absolute top-0 end-0 p-2 d-flex align-items-center gap-2" style="z-index: 2; max-width: 85%;">
                        <span id="selected-document-name" class="text-primary small text-truncate"></span>
                        <button
                            type="button"
                            id="remove-document-file"
                            class="border-0 bg-transparent text-danger p-0"
                            title="Eliminar documento"
                            style="line-height: 1;"
                        >
                            <i class="ti ti-trash ti-xs"></i>
                        </button>
                    </div>
                    <div class="d-flex flex-column align-items-center justify-content-center text-center h-100 w-100">
                        <i id="document-drop-icon" class="ti ti-cloud-upload ti-lg mb-2 text-muted"></i>
                        <p id="document-drop-title" class="mb-2 fw-medium">Suelta un archivo o haz clic para subir</p>
                        <p id="document-drop-subtitle" class="mb-1 text-muted">Opcional: factura, ticket o documento fiscal</p>
                        <div id="document-file-preview" class="d-none mt-2 w-100 h-100 d-flex align-items-center justify-content-center"></div>
                        <p id="document-detection-status" class="mb-0 text-muted small mt-1"></p>
                        <div id="document-detection-loading" class="d-none mt-2">
                            <span class="spinner-border spinner-border-sm text-primary me-2" role="status" aria-hidden="true"></span>
                            <span class="small text-muted">Analizando documento...</span>
                        </div>
                        <input type="file" id="document_file" name="document_file" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </div>
                </label>
                @error('document_file')
                    <small class="text-danger d-block mt-2">{{ $message }}</small>
                @enderror
            </div>

            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="enterprise_id" class="form-label">Empresa</label>
                        <select id="enterprise_id" name="enterprise_id" class="form-select select2 @error('enterprise_id') is-invalid @enderror">
                            <option value="">Selecciona una empresa</option>
                            @foreach ($enterprises as $enterprise)
                                <option value="{{ $enterprise->id }}" {{ old('enterprise_id') == $enterprise->id ? 'selected' : '' }}>
                                    {{ $enterprise->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('enterprise_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="date" class="form-label">Fecha factura <span class="text-danger">*</span></label>
                        <input type="text" id="date" name="date" class="form-control expense-date @error('date') is-invalid @enderror" value="{{ old('date', now()->toDateString()) }}" required>
                        @error('date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="due_date" class="form-label">Fecha vencimiento</label>
                        <input type="text" id="due_date" name="due_date" class="form-control expense-date @error('due_date') is-invalid @enderror" value="{{ old('due_date', old('date', now()->toDateString())) }}">
                        @error('due_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="document_number" class="form-label">N.º factura</label>
                        <input type="text" id="document_number" name="document_number" class="form-control @error('document_number') is-invalid @enderror" value="{{ old('document_number') }}">
                        @error('document_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <x-module-categories-select
                            id="expense_category_id"
                            label="Tipo de gasto"
                            moduleKey="products"
                            :selected="old('expense_category_id')"
                            :allowEmpty="true"
                            emptyText="Selecciona una categoría"
                        />
                    </div>

                    <div class="col-12">
                        <label for="currency_id" class="form-label">Moneda</label>
                        <select id="currency_id" name="currency_id" class="form-select select2 @error('currency_id') is-invalid @enderror">
                            <option value="">Selecciona moneda</option>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" {{ (string) old('currency_id') === (string) $currency->id ? 'selected' : '' }}>
                                    {{ $currency->code }} - {{ $currency->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('currency_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="row g-3 align-items-start">
            <div class="col-lg-8">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody id="expense-lines-body">
                            @foreach ($oldLines as $index => $line)
                                <tr class="expense-line" data-line-index="{{ $index }}">
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label small mb-0">Concepto</label>
                                            <button type="button" class="remove-line-btn text-muted border-0 bg-transparent p-0" title="Eliminar línea" style="line-height: 1;">
                                                <i class="ti ti-trash ti-xs"></i>
                                            </button>
                                        </div>
                                        <div class="mb-2">
                                            <input
                                                type="text"
                                                name="lines[{{ $index }}][concept]"
                                                class="form-control line-concept @error('lines.'.$index.'.concept') is-invalid @enderror"
                                                value="{{ data_get($line, 'concept', '') }}"
                                                required
                                            >
                                            @error('lines.'.$index.'.concept')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Base</label>
                                                <input
                                                    type="number"
                                                    name="lines[{{ $index }}][base_amount]"
                                                    class="form-control text-end line-base @error('lines.'.$index.'.base_amount') is-invalid @enderror"
                                                    min="0.01"
                                                    step="0.01"
                                                    value="{{ data_get($line, 'base_amount', '0.00') }}"
                                                    required
                                                >
                                                @error('lines.'.$index.'.base_amount')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">IVA %</label>
                                                <input
                                                    type="number"
                                                    name="lines[{{ $index }}][vat_percent]"
                                                    class="form-control text-end line-vat @error('lines.'.$index.'.vat_percent') is-invalid @enderror"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value="{{ data_get($line, 'vat_percent', '0') }}"
                                                >
                                                @error('lines.'.$index.'.vat_percent')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1 text-nowrap">Retención %</label>
                                                <input
                                                    type="number"
                                                    name="lines[{{ $index }}][retention_percent]"
                                                    class="form-control text-end line-retention @error('lines.'.$index.'.retention_percent') is-invalid @enderror"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value="{{ data_get($line, 'retention_percent', '0') }}"
                                                >
                                                @error('lines.'.$index.'.retention_percent')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Imputa %</label>
                                                <input
                                                    type="number"
                                                    name="lines[{{ $index }}][allocation_percent]"
                                                    class="form-control text-end line-allocation @error('lines.'.$index.'.allocation_percent') is-invalid @enderror"
                                                    min="0.01"
                                                    max="100"
                                                    step="0.01"
                                                    value="{{ data_get($line, 'allocation_percent', '100') }}"
                                                >
                                                @error('lines.'.$index.'.allocation_percent')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @error('lines')
                    <small class="text-danger d-block mt-1">{{ $message }}</small>
                @enderror

                <button type="button" id="add-expense-line" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="ti ti-plus me-1"></i> Añadir línea
                </button>
            </div>

            <div class="col-lg-4">
                <div class="card bg-label-warning mb-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Base imponible</span>
                            <strong id="summary-base">0.00</strong>
                        </div>
                        <div id="summary-vat-lines" class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>I.V.A. 0%</span>
                                <strong>0.00</strong>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total retención</span>
                            <strong id="summary-retention">0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total</span>
                            <strong id="summary-total">0.00</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_investment" name="is_investment" value="1" {{ old('is_investment') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_investment">Es una inversión</label>
                </div>
            </div>
            <div class="col-md-8">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="cash_criteria" name="cash_criteria" value="1" {{ old('cash_criteria') ? 'checked' : '' }}>
                    <label class="form-check-label" for="cash_criteria">Gasto sujeto a criterio de caja</label>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">Pago</h5>
        <div class="p-3 rounded bg-label-warning">
            <div class="row g-3">
                <div class="col-md-2">
                    <label for="payment_date" class="form-label">Fecha del pago <span class="text-danger">*</span></label>
                    <input type="text" id="payment_date" name="payment_date" class="form-control expense-date @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', now()->toDateString()) }}" required>
                    @error('payment_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="payment_amount" class="form-label">Importe pagado</label>
                    <input type="number" id="payment_amount" name="payment_amount" class="form-control text-end @error('payment_amount') is-invalid @enderror" step="0.01" min="0.01" value="{{ old('payment_amount') }}" placeholder="Automático según totales">
                    @error('payment_amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="type_id" class="form-label">Forma de pago <span class="text-danger">*</span></label>
                    <select id="type_id" name="type_id" class="form-select select2 @error('type_id') is-invalid @enderror" required>
                        <option value="">Selecciona forma de pago</option>
                        @foreach ($paymentTypes as $paymentType)
                            <option value="{{ $paymentType->id }}" {{ old('type_id') == $paymentType->id ? 'selected' : '' }}>{{ $paymentType->name }}</option>
                        @endforeach
                    </select>
                    @error('type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="account_id" class="form-label">Cuenta <span class="text-danger">*</span></label>
                    <select id="account_id" name="account_id" class="form-select select2 @error('account_id') is-invalid @enderror" required>
                        <option value="">Selecciona cuenta</option>
                        @foreach ($paymentAccounts as $account)
                            <option value="{{ $account->id }}" data-currency-id="{{ $account->currency_id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }} ({{ strtoupper((string) ($account->currency->code ?? 'USD')) }})
                            </option>
                        @endforeach
                    </select>
                    @error('account_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Estado <span class="text-danger">*</span></label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                        @foreach ($statusOptions as $statusId => $statusLabel)
                            <option value="{{ $statusId }}" {{ (string) old('status', '2') === (string) $statusId ? 'selected' : '' }}>
                                {{ $statusLabel }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row g-3 mt-4">
            <div class="col-md-6">
                <label for="remarks" class="form-label">Comentario personal del gasto</label>
                <textarea id="remarks" name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3">{{ old('remarks') }}</textarea>
                @error('remarks')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="tags" class="form-label">Etiquetas / Proyecto</label>
                <input type="text" id="tags" name="tags" class="form-control @error('tags') is-invalid @enderror" value="{{ old('tags') }}" placeholder="Ej. operaciones, q2, proyecto-x">
                @error('tags')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <a href="{{ route('expense.index') }}" class="btn btn-label-secondary">Cancelar</a>
            <div class="d-flex gap-2">
                <button type="submit" name="submit_action" value="draft" class="btn btn-label-primary">Guardar borrador</button>
                <button type="submit" name="submit_action" value="save" class="btn btn-primary">Guardar gasto</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('page-script')
<script>
    $(function () {
        $('.select2').each(function () {
            var $this = $(this);
            $this.wrap('<div class="position-relative"></div>');
            $this.select2({
                dropdownParent: $this.parent(),
                width: '100%',
                allowClear: false
            });
        });

        var spanishLocale = {
            firstDayOfWeek: 1,
            weekdays: {
                shorthand: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
            },
            months: {
                shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
            },
            ordinal: function () {
                return 'º';
            },
            rangeSeparator: ' a ',
            weekAbbreviation: 'Sem',
            scrollTitle: 'Desplázate para aumentar',
            toggleTitle: 'Haz clic para alternar',
        };

        $('.expense-date').flatpickr({
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            locale: spanishLocale,
            allowInput: true
        });

        $('.document-type-btn').on('click', function () {
            var selectedType = $(this).data('document-type');
            $('#document_type').val(selectedType);
            $('.document-type-btn').removeClass('btn-primary').addClass('btn-outline-secondary');
            $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
        });

        var $dropZone = $('#document-drop-zone');
        var $documentInput = $('#document_file');
        var $documentDropIcon = $('#document-drop-icon');
        var $documentDropTitle = $('#document-drop-title');
        var $documentDropSubtitle = $('#document-drop-subtitle');
        var $documentFileMeta = $('#document-file-meta');
        var $removeDocumentFileButton = $('#remove-document-file');
        var $documentName = $('#selected-document-name');
        var $documentFilePreview = $('#document-file-preview');
        var $documentDetectionStatus = $('#document-detection-status');
        var $documentDetectionLoading = $('#document-detection-loading');
        var $linesBody = $('#expense-lines-body');
        var $currencySelect = $('#currency_id');
        var $summaryVatLines = $('#summary-vat-lines');
        var detectDocumentUrl = @json(route('expense.detect-document'));
        var csrfToken = @json(csrf_token());
        var nextLineIndex = $linesBody.find('.expense-line').length;
        var documentPreviewObjectUrl = null;

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function updateDocumentName() {
            var file = $documentInput[0].files[0];
            if (file) {
                $documentName.text(file.name);
                setDropzoneUploadedState(true);
                renderDocumentFilePreview(file);
                detectExpenseDocument(file);
            } else {
                $documentName.text('');
                $documentDetectionStatus.text('');
                $documentDetectionLoading.addClass('d-none');
                setDropzoneUploadedState(false);
                clearDocumentFilePreview();
            }
        }

        function clearDocumentFilePreview() {
            if (documentPreviewObjectUrl) {
                URL.revokeObjectURL(documentPreviewObjectUrl);
                documentPreviewObjectUrl = null;
            }
            $documentFilePreview.addClass('d-none').empty();
        }

        function setDropzoneUploadedState(hasFile) {
            $documentDropIcon.toggleClass('d-none', hasFile);
            $documentDropTitle.toggleClass('d-none', hasFile);
            $documentDropSubtitle.toggleClass('d-none', hasFile);
            $documentFileMeta.toggleClass('d-none', !hasFile);
        }

        function renderDocumentFilePreview(file) {
            clearDocumentFilePreview();

            if (!file) {
                return;
            }

            var mimeType = (file.type || '').toLowerCase();
            documentPreviewObjectUrl = URL.createObjectURL(file);

            if (mimeType.indexOf('image/') === 0) {
                $documentFilePreview
                    .html(
                        '<div class="border rounded p-2 bg-white w-100">' +
                        '<img src="' + escapeHtml(documentPreviewObjectUrl) + '" alt="Vista previa documento" class="img-fluid rounded" style="max-height: 310px; width: 100%; object-fit: contain;">' +
                        '</div>'
                    )
                    .removeClass('d-none');
                return;
            }

            if (mimeType === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                $documentFilePreview
                    .html(
                        '<div class="border rounded p-2 bg-white w-100">' +
                        '<iframe src="' + escapeHtml(documentPreviewObjectUrl) + '#toolbar=0&navpanes=0&scrollbar=1" title="Vista previa PDF" style="width: 100%; height: 310px; border: 0;"></iframe>' +
                        '</div>'
                    )
                    .removeClass('d-none');
                return;
            }
        }

        $dropZone.on('dragover', function (event) {
            event.preventDefault();
            $dropZone.addClass('border-primary bg-label-primary');
        });

        $dropZone.on('dragleave', function () {
            $dropZone.removeClass('border-primary bg-label-primary');
        });

        $dropZone.on('drop', function (event) {
            event.preventDefault();
            $dropZone.removeClass('border-primary bg-label-primary');

            var droppedFiles = event.originalEvent.dataTransfer.files;

            if (!droppedFiles || droppedFiles.length === 0) {
                return;
            }

            var transfer = new DataTransfer();
            transfer.items.add(droppedFiles[0]);
            $documentInput[0].files = transfer.files;
            updateDocumentName();
        });

        $documentInput.on('change', updateDocumentName);

        $removeDocumentFileButton.on('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            $documentInput.val('');
            updateDocumentName();
        });

        function createLineRow(index, lineData) {
            var line = lineData || {};
            var concept = line.concept ? String(line.concept) : '';
            var baseAmount = Number.isFinite(parseFloat(line.base_amount)) ? parseFloat(line.base_amount).toFixed(2) : '0.00';
            var vatPercent = Number.isFinite(parseFloat(line.vat_percent)) ? parseFloat(line.vat_percent).toFixed(2) : '0';
            var retentionPercent = Number.isFinite(parseFloat(line.retention_percent)) ? parseFloat(line.retention_percent).toFixed(2) : '0';
            var allocationPercent = Number.isFinite(parseFloat(line.allocation_percent)) ? parseFloat(line.allocation_percent).toFixed(2) : '100';

            return [
                '<tr class="expense-line" data-line-index="' + index + '">',
                '  <td>',
                '    <div class="d-flex justify-content-between align-items-center mb-2">',
                '      <label class="form-label small mb-0">Concepto</label>',
                '      <button type="button" class="remove-line-btn text-muted border-0 bg-transparent p-0" title="Eliminar línea" style="line-height: 1;">',
                '        <i class="ti ti-trash ti-xs"></i>',
                '      </button>',
                '    </div>',
                '    <div class="mb-2">',
                '      <input type="text" name="lines[' + index + '][concept]" class="form-control line-concept" value="' + escapeHtml(concept) + '" required>',
                '    </div>',
                '    <div class="row g-2">',
                '      <div class="col-md-3">',
                '        <label class="form-label small mb-1">Base</label>',
                '        <input type="number" name="lines[' + index + '][base_amount]" class="form-control text-end line-base" min="0.01" step="0.01" value="' + escapeHtml(baseAmount) + '" required>',
                '      </div>',
                '      <div class="col-md-3">',
                '        <label class="form-label small mb-1">IVA %</label>',
                '        <input type="number" name="lines[' + index + '][vat_percent]" class="form-control text-end line-vat" min="0" max="100" step="0.01" value="' + escapeHtml(vatPercent) + '">',
                '      </div>',
                '      <div class="col-md-3">',
                '        <label class="form-label small mb-1 text-nowrap">Retención %</label>',
                '        <input type="number" name="lines[' + index + '][retention_percent]" class="form-control text-end line-retention" min="0" max="100" step="0.01" value="' + escapeHtml(retentionPercent) + '">',
                '      </div>',
                '      <div class="col-md-3">',
                '        <label class="form-label small mb-1">Imputa %</label>',
                '        <input type="number" name="lines[' + index + '][allocation_percent]" class="form-control text-end line-allocation" min="0.01" max="100" step="0.01" value="' + escapeHtml(allocationPercent) + '">',
                '      </div>',
                '    </div>',
                '  </td>',
                '</tr>',
            ].join('');
        }

        function setExpenseDate(inputSelector, dateValue) {
            if (!dateValue) {
                return;
            }

            var inputElement = $(inputSelector).get(0);
            if (inputElement && inputElement._flatpickr) {
                inputElement._flatpickr.setDate(dateValue, true, 'Y-m-d');
            } else {
                $(inputSelector).val(dateValue).trigger('change');
            }
        }

        function applyDetectedDocumentData(data) {
            if (!data || typeof data !== 'object') {
                return;
            }

            if (data.enterprise_id) {
                var enterpriseValue = String(data.enterprise_id);
                if ($('#enterprise_id option[value="' + enterpriseValue + '"]').length > 0) {
                    $('#enterprise_id').val(enterpriseValue).trigger('change');
                }
            }

            if (data.document_number) {
                $('#document_number').val(String(data.document_number));
            }

            if (data.date) {
                setExpenseDate('#date', String(data.date));
            }

            if (data.due_date) {
                setExpenseDate('#due_date', String(data.due_date));
            }

            if (data.payment_date) {
                setExpenseDate('#payment_date', String(data.payment_date));
            }

            if (data.currency_id) {
                var currencyValue = String(data.currency_id);
                if ($('#currency_id option[value="' + currencyValue + '"]').length > 0) {
                    $('#currency_id').val(currencyValue).trigger('change');
                }
            }

            if (Array.isArray(data.lines) && data.lines.length > 0) {
                $linesBody.empty();
                data.lines.forEach(function (line, index) {
                    $linesBody.append(createLineRow(index, line));
                });
                nextLineIndex = data.lines.length;
            }

            if (data.payment_amount && (!$('#payment_amount').val() || $('#payment_amount').val() === '')) {
                $('#payment_amount').val(formatAmount(data.payment_amount));
            }

            refreshSummary();
        }

        function detectExpenseDocument(file) {
            if (!file || !detectDocumentUrl) {
                return;
            }

            var formData = new FormData();
            formData.append('document_file', file);

            $documentDetectionStatus
                .removeClass('text-danger text-success')
                .addClass('text-muted')
                .text('');
            $documentDetectionLoading.removeClass('d-none');

            $.ajax({
                url: detectDocumentUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function (response) {
                    $documentDetectionLoading.addClass('d-none');

                    if (!response || response.success !== true) {
                        $documentDetectionStatus
                            .removeClass('text-muted text-success')
                            .addClass('text-danger')
                            .text('No se pudo interpretar el documento.');
                        return;
                    }

                    applyDetectedDocumentData(response.data || {});
                    $documentDetectionStatus
                        .removeClass('text-muted text-danger')
                        .addClass('text-success')
                        .text('');
                },
                error: function () {
                    $documentDetectionLoading.addClass('d-none');
                    $documentDetectionStatus
                        .removeClass('text-muted text-success')
                        .addClass('text-danger')
                        .text('No se pudo analizar el documento. Revisa OCR/AI en ajustes.');
                }
            });
        }

        setDropzoneUploadedState(false);

        $('#add-expense-line').on('click', function () {
            $linesBody.append(createLineRow(nextLineIndex));
            nextLineIndex += 1;
            refreshSummary();
        });

        $(document).on('click', '.remove-line-btn', function () {
            if ($linesBody.find('.expense-line').length <= 1) {
                return;
            }

            $(this).closest('.expense-line').remove();
            refreshSummary();
        });

        function asNumber(value) {
            var parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function formatAmount(value) {
            return asNumber(value).toFixed(2);
        }

        function formatPercent(value) {
            var number = asNumber(value);
            if (Number.isInteger(number)) {
                return String(number);
            }

            return number.toFixed(2).replace(/\.?0+$/, '');
        }

        function refreshSummary() {
            var base = 0;
            var vatAmount = 0;
            var retentionAmount = 0;
            var total = 0;
            var vatByRate = {};

            $linesBody.find('.expense-line').each(function () {
                var $line = $(this);
                var lineBase = asNumber($line.find('.line-base').val());
                var lineVatPercent = asNumber($line.find('.line-vat').val());
                var lineRetentionPercent = asNumber($line.find('.line-retention').val());
                var lineAllocationPercent = asNumber($line.find('.line-allocation').val());

                var lineVatAmount = lineBase * (lineVatPercent / 100);
                var lineRetentionAmount = lineBase * (lineRetentionPercent / 100);
                var lineTotal = (lineBase + lineVatAmount - lineRetentionAmount) * (lineAllocationPercent / 100);

                base += lineBase;
                vatAmount += lineVatAmount;
                retentionAmount += lineRetentionAmount;
                total += lineTotal;

                if (lineVatPercent > 0) {
                    var rateKey = lineVatPercent.toFixed(2);
                    vatByRate[rateKey] = (vatByRate[rateKey] || 0) + lineVatAmount;
                }
            });

            $('#summary-base').text(formatAmount(base));
            $('#summary-retention').text(formatAmount(retentionAmount));
            $('#summary-total').text(formatAmount(total));

            var vatRates = Object.keys(vatByRate).sort(function (left, right) {
                return asNumber(left) - asNumber(right);
            });

            $summaryVatLines.empty();

            if (vatRates.length === 0) {
                $summaryVatLines.append(
                    '<div class="d-flex justify-content-between"><span>I.V.A. 0%</span><strong>0.00</strong></div>'
                );
            } else {
                vatRates.forEach(function (rate, index) {
                    var marginClass = index < vatRates.length - 1 ? ' mb-1' : '';
                    $summaryVatLines.append(
                        '<div class="d-flex justify-content-between' + marginClass + '"><span>I.V.A. ' + formatPercent(rate) + '%</span><strong>' + formatAmount(vatByRate[rate]) + '</strong></div>'
                    );
                });
            }

            if ($('#payment_amount').val() === '') {
                $('#payment_amount').attr('placeholder', formatAmount(total));
            }
        }

        $(document).on('input', '.line-base, .line-vat, .line-retention, .line-allocation', refreshSummary);

        $('#account_id').on('change', function () {
            if ($currencySelect.val()) {
                return;
            }

            var selectedCurrencyId = $(this).find(':selected').data('currency-id');
            if (selectedCurrencyId) {
                $currencySelect.val(String(selectedCurrencyId)).trigger('change');
            }
        });

        refreshSummary();
    });
</script>
@endsection
