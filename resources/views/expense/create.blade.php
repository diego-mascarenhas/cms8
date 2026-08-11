@php
    $documentFlow = array_merge([
        'mode' => 'buy',
        'page_title' => 'Añadir gasto',
        'breadcrumb' => 'Gastos',
        'subtitle' => 'Registrar un nuevo gasto',
        'back_route' => route('expense.index'),
        'store_route' => route('expense.store'),
        'party_label' => 'Proveedor (*)',
        'party_placeholder' => 'Selecciona un proveedor',
        'create_party_label' => 'Crear proveedor',
        'create_party_modal_title' => 'Crear proveedor',
        'create_party_help' => 'Los datos fiscales se guardarán en el proveedor para futuras facturas.',
        'save_party_label' => 'Guardar proveedor',
        'remarks_label' => 'Comentario personal del gasto',
        'submit_label' => 'Guardar gasto',
        'account_hint' => 'antes de registrar el gasto.',
        'payments_section_title' => 'Pagos',
        'add_payment_label' => 'Añadir pago',
        'payment_date_label' => 'Fecha del pago (*)',
        'remove_payment_title' => 'Eliminar pago',
        'payments_empty_message' => 'Sin pagos registrados. La factura quedará pendiente de pago. Usa «Añadir pago» si quieres registrar uno.',
        'payments_empty_summary' => 'Sin pagos registrados. Pendiente de pago:',
        'paid_label' => 'Pagado',
        'payments_overflow_prefix' => 'La suma de pagos supera el',
        'payments_overflow_suffix' => 'total del gasto.',
        'duplicate_message' => 'Este número de comprobante ya fue registrado para este proveedor.',
        'detect_document_url' => route('expense.detect-document'),
        'check_duplicate_url' => route('expense.check-document-duplicate'),
        'create_party_url' => route('expense.create-supplier'),
        'suggested_categories_url' => route('expense.suggested-categories'),
        'livewire_key' => 'expense-line-cat-mgr-services',
    ], $documentFlow ?? []);
@endphp

@extends('layouts/layoutMaster')

@section('title', $documentFlow['page_title'])

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
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
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ $documentFlow['breadcrumb'] }}/</span> Crear</h4>
        <p class="text-muted">{{ $documentFlow['subtitle'] }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ $documentFlow['back_route'] }}" class="btn btn-label-secondary waves-effect">
            <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
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
    <form class="card-body" action="{{ $documentFlow['store_route'] }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf
        @php
            $selectedDocumentType = old('document_type', 'invoice');
            if (in_array($selectedDocumentType, $disabledDocumentTypes, true)) {
                $selectedDocumentType = 'invoice';
            }
        @endphp
        <input type="hidden" id="document_type" name="document_type" value="{{ $selectedDocumentType }}">

        @if (($documentFlow['mode'] ?? 'buy') === 'buy')
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($documentTypes as $documentTypeKey => $documentTypeLabel)
                            @php
                                $isDisabledDocumentType = in_array($documentTypeKey, $disabledDocumentTypes, true);
                                $isSelectedDocumentType = ! $isDisabledDocumentType && $selectedDocumentType === $documentTypeKey;
                            @endphp
                            <button
                                type="button"
                                class="btn btn-sm {{ $isSelectedDocumentType ? 'btn-primary' : 'btn-outline-secondary' }} document-type-btn"
                                data-document-type="{{ $documentTypeKey }}"
                                @if ($isDisabledDocumentType) disabled @endif
                            >
                                {{ $documentTypeLabel }}
                            </button>
                        @endforeach
                    </div>
                    @error('document_type')
                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                    @enderror
                </div>
            </div>
        @endif

        <div class="row g-3">
            @if (($documentFlow['mode'] ?? 'buy') === 'buy')
                <div class="col-lg-7">
                    <input type="file" id="document_file" name="document_file" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <div
                        id="document-drop-zone"
                        class="border rounded p-4 h-100 position-relative overflow-hidden"
                        style="border-style: dashed !important; cursor: pointer;"
                        role="button"
                        tabindex="0"
                        aria-label="Suelta un archivo o haz clic para subir"
                    >
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
                        </div>
                    </div>
                    @error('document_file')
                        <small class="text-danger d-block mt-2">{{ $message }}</small>
                    @enderror
                </div>
            @endif

            @php
                $isSellDocumentFlow = ($documentFlow['mode'] ?? 'buy') === 'sell';
            @endphp
            <div class="{{ $isSellDocumentFlow ? 'col-12' : 'col-lg-5' }}">
                <div class="row g-3">
                    <div class="{{ $isSellDocumentFlow ? 'col-md-8' : 'col-12' }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="enterprise_id" class="form-label mb-0">{{ $documentFlow['party_label'] }}</label>
                            @can('create', \App\Models\Enterprise::class)
                                <button type="button" class="btn btn-sm btn-outline-primary" id="open-create-supplier-modal">
                                    <i class="ti ti-building-store me-1"></i> {{ $documentFlow['create_party_label'] }}
                                </button>
                            @endcan
                        </div>
                        <select id="enterprise_id" name="enterprise_id" class="form-select select2-enterprise @error('enterprise_id') is-invalid @enderror">
                            <option value="">{{ $documentFlow['party_placeholder'] }}</option>
                            @foreach ($enterprises as $enterprise)
                                @php
                                    $enterpriseContacts = $enterprise->contacts
                                        ->map(function ($contact) {
                                            $name = trim(($contact->name ?? '').' '.($contact->surname ?? ''));
                                            $email = trim((string) ($contact->email ?? ''));
                                            if ($name === '' && $email === '') {
                                                return null;
                                            }
                                            $label = ($name !== '' && $email !== '')
                                                ? $name.' · '.$email
                                                : ($name !== '' ? $name : $email);

                                            return ['label' => $label, 'search' => $label];
                                        })
                                        ->filter()
                                        ->values()
                                        ->all();
                                    $enterpriseTypeName = $enterprise->type?->name;
                                    $responsibleName = trim((string) ($enterprise->responsible?->name ?? ''));
                                    $responsibleEmail = trim((string) ($enterprise->responsible?->email ?? ''));
                                    $enterpriseResponsible = match (true) {
                                        $responsibleName !== '' && $responsibleEmail !== '' => $responsibleName.' · '.$responsibleEmail,
                                        $responsibleName !== '' => $responsibleName,
                                        $responsibleEmail !== '' => $responsibleEmail,
                                        default => '',
                                    };
                                    $enterpriseKeywords = trim(implode(' ', array_filter([
                                        collect($enterpriseContacts)->pluck('search')->implode(' '),
                                        $enterpriseTypeName,
                                        $enterpriseResponsible,
                                    ])));
                                @endphp
                                <option
                                    value="{{ $enterprise->id }}"
                                    data-keywords="{{ $enterpriseKeywords }}"
                                    data-type="{{ $enterpriseTypeName ?? '' }}"
                                    data-responsible="{{ $enterpriseResponsible }}"
                                    data-contacts='@json($enterpriseContacts)'
                                    {{ old('enterprise_id') == $enterprise->id ? 'selected' : '' }}
                                >
                                    {{ $enterprise->name }}
                                </option>
                            @endforeach
                        </select>
                        <small id="enterprise-detection-status" class="d-block mt-1 text-muted"></small>
                        @error('enterprise_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @if ($isSellDocumentFlow)
                        <div class="col-md-4">
                            <label for="document_number" class="form-label">Número de comprobante</label>
                            <input
                                type="text"
                                id="document_number"
                                class="form-control"
                                value=""
                                placeholder="Automático"
                                readonly
                                tabindex="-1"
                                aria-readonly="true"
                            >
                            <small class="text-muted d-block mt-1">Se asignará al guardar</small>
                        </div>
                    @endif

                    <div class="{{ $isSellDocumentFlow ? 'col-md-4' : 'col-md-6' }}">
                        <label for="date" class="form-label">Fecha (*)</label>
                        <input type="text" id="date" name="date" class="form-control expense-date @error('date') is-invalid @enderror" value="{{ old('date', now()->toDateString()) }}">
                        @error('date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="{{ $isSellDocumentFlow ? 'col-md-4' : 'col-md-6' }}">
                        <label for="due_date" class="form-label">Fecha vencimiento</label>
                        <input type="text" id="due_date" name="due_date" class="form-control expense-date @error('due_date') is-invalid @enderror" value="{{ old('due_date', old('date', now()->toDateString())) }}">
                        @error('due_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @unless ($isSellDocumentFlow)
                        <div class="col-12">
                            <label for="document_number" class="form-label">Número de comprobante</label>
                            <input type="text" id="document_number" name="document_number" class="form-control @error('document_number') is-invalid @enderror" value="{{ old('document_number') }}">
                            <small id="document-number-duplicate-warning" class="d-none text-danger d-block mt-1"></small>
                            @error('document_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endunless

                    <div class="{{ $isSellDocumentFlow ? 'col-md-4' : 'col-12' }}">
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
                                @php
                                    $selectedLineCategoryId = (string) data_get($line, 'category_id', old('lines.'.$index.'.category_id', ''));
                                    $selectedLineCategoryName = collect($expenseCategoryOptions)
                                        ->firstWhere('id', (int) $selectedLineCategoryId)['name'] ?? '';
                                    $hasLineCategory = $selectedLineCategoryId !== '';
                                @endphp
                                <tr class="expense-line" data-line-index="{{ $index }}">
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                                            <label class="form-label small mb-0">Concepto (*)</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <input
                                                    type="hidden"
                                                    name="lines[{{ $index }}][category_id]"
                                                    class="line-category-id"
                                                    value="{{ $hasLineCategory ? $selectedLineCategoryId : '' }}"
                                                >
                                                <button
                                                    type="button"
                                                    class="badge border-0 line-category-badge {{ $hasLineCategory ? 'bg-label-primary' : 'bg-label-secondary' }}"
                                                    title="Seleccionar categoría"
                                                >
                                                    {{ $hasLineCategory ? ($selectedLineCategoryName !== '' ? $selectedLineCategoryName : '#'.$selectedLineCategoryId) : 'Sin categoría' }}
                                                </button>
                                                <button type="button" class="remove-line-btn text-muted border-0 bg-transparent p-0" title="Eliminar línea" style="line-height: 1;">
                                                    <i class="ti ti-trash ti-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                        @error('lines.'.$index.'.category_id')
                                            <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
                                        @enderror
                                        <div class="mb-2">
                                            <input
                                                type="text"
                                                name="lines[{{ $index }}][concept]"
                                                class="form-control line-concept @error('lines.'.$index.'.concept') is-invalid @enderror"
                                                value="{{ data_get($line, 'concept', '') }}"
                                            >
                                            @error('lines.'.$index.'.concept')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1 d-block text-end">Base (*)</label>
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    name="lines[{{ $index }}][base_amount]"
                                                    class="form-control text-end line-base @error('lines.'.$index.'.base_amount') is-invalid @enderror"
                                                    value="{{ \App\Helpers\Helpers::formatDecimal((float) data_get($line, 'base_amount', 0)) }}"
                                                >
                                                @error('lines.'.$index.'.base_amount')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1 d-block text-end">IVA %</label>
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    name="lines[{{ $index }}][vat_percent]"
                                                    class="form-control text-end line-vat @error('lines.'.$index.'.vat_percent') is-invalid @enderror"
                                                    value="{{ data_get($line, 'vat_percent', '0') }}"
                                                >
                                                @error('lines.'.$index.'.vat_percent')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1 d-block text-end text-nowrap">Retención %</label>
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    name="lines[{{ $index }}][retention_percent]"
                                                    class="form-control text-end line-retention @error('lines.'.$index.'.retention_percent') is-invalid @enderror"
                                                    value="{{ data_get($line, 'retention_percent', '0') }}"
                                                >
                                                @error('lines.'.$index.'.retention_percent')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1 d-block text-end">Imputa %</label>
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    name="lines[{{ $index }}][allocation_percent]"
                                                    class="form-control text-end line-allocation @error('lines.'.$index.'.allocation_percent') is-invalid @enderror"
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
                    <i class="ti ti-plus me-1"></i> Añadir ítem
                </button>
            </div>

            <div class="col-lg-4">
                <div class="card bg-label-warning mb-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Base imponible</span>
                            <strong id="summary-base">0,00</strong>
                        </div>
                        <div id="summary-vat-lines" class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>I.V.A. 0%</span>
                                <strong>0,00</strong>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total retención</span>
                            <strong id="summary-retention">0,00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total</span>
                            <strong id="summary-total">0,00</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        @php
            $initialPayments = old('payments');
            if (! is_array($initialPayments)) {
                $initialPayments = [];
            }
        @endphp

        @if ($paymentTypes->isEmpty())
            <div class="alert alert-warning">
                No hay formas de pago configuradas. Contacta con el administrador del sistema.
            </div>
        @endif

        @if ($paymentAccounts->isEmpty())
            <div class="alert alert-warning">
                No hay cuentas de pago activas en tu empresa.
                @can('create', \App\Models\PaymentAccount::class)
                    <a href="{{ route('payment-account.create') }}">Crea una cuenta</a> {{ $documentFlow['account_hint'] }}
                @else
                    Crea una cuenta {{ $documentFlow['account_hint'] }}
                @endcan
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">{{ $documentFlow['payments_section_title'] }}</h5>
            <button type="button" id="add-expense-payment" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-plus me-1"></i>{{ $documentFlow['add_payment_label'] }}
            </button>
        </div>

        <div id="expense-payments-container">
            @foreach ($initialPayments as $paymentIndex => $payment)
                <div class="expense-payment-block border rounded p-3 mb-2" data-payment-index="{{ $paymentIndex }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2 col-lg-2">
                            <label class="form-label" for="payment_date_{{ $paymentIndex }}">{{ $documentFlow['payment_date_label'] }}</label>
                            <input
                                type="text"
                                id="payment_date_{{ $paymentIndex }}"
                                name="payments[{{ $paymentIndex }}][payment_date]"
                                class="form-control expense-date payment-date @error('payments.'.$paymentIndex.'.payment_date') is-invalid @enderror"
                                value="{{ old('payments.'.$paymentIndex.'.payment_date', $payment['payment_date'] ?? now()->toDateString()) }}"
                            >
                            @error('payments.'.$paymentIndex.'.payment_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2 col-lg-2">
                            <label class="form-label" for="payment_amount_{{ $paymentIndex }}">Importe</label>
                            @php
                                $paymentAmountValue = old('payments.'.$paymentIndex.'.amount', $payment['amount'] ?? '');
                                $paymentAmountDisplay = filled($paymentAmountValue)
                                    ? \App\Helpers\Helpers::formatDecimal((float) $paymentAmountValue)
                                    : '';
                            @endphp
                            <input
                                type="text"
                                inputmode="decimal"
                                id="payment_amount_{{ $paymentIndex }}"
                                name="payments[{{ $paymentIndex }}][amount]"
                                class="form-control text-end payment-amount @error('payments.'.$paymentIndex.'.amount') is-invalid @enderror"
                                value="{{ $paymentAmountDisplay }}"
                            >
                            @error('payments.'.$paymentIndex.'.amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2 col-lg-2">
                            <label class="form-label" for="payment_type_id_{{ $paymentIndex }}">Forma de pago (*)</label>
                            <select
                                id="payment_type_id_{{ $paymentIndex }}"
                                name="payments[{{ $paymentIndex }}][type_id]"
                                class="form-select payment-type-select @error('payments.'.$paymentIndex.'.type_id') is-invalid @enderror"
                            >
                                <option value="">Selecciona forma de pago</option>
                                @foreach ($paymentTypes as $paymentType)
                                    <option value="{{ $paymentType->id }}" {{ (string) old('payments.'.$paymentIndex.'.type_id', $payment['type_id'] ?? $defaultPaymentTypeId) === (string) $paymentType->id ? 'selected' : '' }}>
                                        {{ $paymentType->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payments.'.$paymentIndex.'.type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 col-lg-3">
                            <label class="form-label" for="payment_account_id_{{ $paymentIndex }}">Cuenta (*)</label>
                            <select
                                id="payment_account_id_{{ $paymentIndex }}"
                                name="payments[{{ $paymentIndex }}][account_id]"
                                class="form-select payment-account-select @error('payments.'.$paymentIndex.'.account_id') is-invalid @enderror"
                            >
                                <option value="">Selecciona cuenta</option>
                                @foreach ($paymentAccounts as $account)
                                    <option
                                        value="{{ $account->id }}"
                                        data-currency-id="{{ $account->currency_id }}"
                                        {{ (string) old('payments.'.$paymentIndex.'.account_id', $payment['account_id'] ?? $defaultPaymentAccountId) === (string) $account->id ? 'selected' : '' }}
                                    >
                                        {{ $account->name }} ({{ strtoupper((string) ($account->currency->code ?? 'USD')) }})
                                    </option>
                                @endforeach
                            </select>
                            @error('payments.'.$paymentIndex.'.account_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2 col-lg-2">
                            <label class="form-label" for="payment_status_{{ $paymentIndex }}">Estado (*)</label>
                            <select
                                id="payment_status_{{ $paymentIndex }}"
                                name="payments[{{ $paymentIndex }}][status]"
                                class="form-select select2 payment-status-select @error('payments.'.$paymentIndex.'.status') is-invalid @enderror"
                            >
                                @foreach ($statusOptions as $statusId => $statusLabel)
                                    <option value="{{ $statusId }}" {{ (string) old('payments.'.$paymentIndex.'.status', $payment['status'] ?? '2') === (string) $statusId ? 'selected' : '' }}>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payments.'.$paymentIndex.'.status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-1 col-lg-1 d-flex justify-content-end">
                            <button type="button" class="btn btn-icon btn-label-danger remove-payment-btn" title="{{ $documentFlow['remove_payment_title'] }}">
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p id="expense-payments-empty" class="text-muted small mb-0 {{ $initialPayments !== [] ? 'd-none' : '' }}">
            {{ $documentFlow['payments_empty_message'] }}
        </p>

        <div id="payments-summary" class="small text-muted mt-2"></div>
        @error('payments')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror

        <div class="row g-3 mt-4">
            <div class="col-md-6">
                <label for="remarks" class="form-label">{{ $documentFlow['remarks_label'] }}</label>
                <input type="text" id="remarks" name="remarks" class="form-control @error('remarks') is-invalid @enderror" maxlength="1000" value="{{ old('remarks') }}">
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
            <a href="{{ $documentFlow['back_route'] }}" class="btn btn-label-secondary">Cancelar</a>
            <div class="d-flex gap-2">
                <button type="submit" name="submit_action" value="draft" class="btn btn-label-primary expense-submit-btn">Guardar borrador</button>
                <button type="submit" name="submit_action" value="save" class="btn btn-primary expense-submit-btn">{{ $documentFlow['submit_label'] }}</button>
            </div>
        </div>
    </form>
</div>

@can('create', \App\Models\Enterprise::class)
<div class="modal fade" id="createSupplierModal" tabindex="-1" aria-labelledby="createSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSupplierModalLabel">{{ $documentFlow['create_party_modal_title'] }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="create-supplier-form">
                <div class="modal-body">
                    <p class="text-muted small mb-3">{{ $documentFlow['create_party_help'] }}</p>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="supplier_name" class="form-label">Nombre o razón social (*)</label>
                            <input type="text" id="supplier_name" name="name" class="form-control" maxlength="75" required>
                        </div>
                        <div class="col-md-4">
                            <label for="supplier_identification_number" class="form-label">NIF/CIF</label>
                            <input type="text" id="supplier_identification_number" name="identification_number" class="form-control" maxlength="30">
                        </div>
                        <div class="col-md-6">
                            <label for="supplier_email" class="form-label">Email</label>
                            <input type="email" id="supplier_email" name="email" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label for="supplier_phone" class="form-label">Teléfono</label>
                            <input type="tel" id="supplier_phone" name="phone" class="form-control" maxlength="25" inputmode="tel" autocomplete="tel" placeholder="Ej. +34 600 123 456" pattern="^[+\-\d\s()]+$" title="Solo números, espacios y los símbolos + - ( )">
                        </div>
                        <div class="col-12">
                            <label for="supplier_website" class="form-label">Web</label>
                            <input type="text" id="supplier_website" name="website" class="form-control" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label for="supplier_address" class="form-label">Dirección fiscal</label>
                            <input type="text" id="supplier_address" name="address" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-2">
                            <label for="supplier_postal_code" class="form-label">Código postal</label>
                            <input type="text" id="supplier_postal_code" name="postal_code" class="form-control" maxlength="20">
                        </div>
                        <div class="col-md-3">
                            <label for="supplier_locality" class="form-label">Localidad</label>
                            <input type="text" id="supplier_locality" name="locality" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-3">
                            <label for="supplier_province" class="form-label">Provincia</label>
                            <input type="text" id="supplier_province" name="province" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label for="supplier_country" class="form-label">País</label>
                            <select id="supplier_country" name="country" class="form-select select2-supplier-country">
                                <option value="">Seleccione un país</option>
                                @foreach ($countries as $country)
                                    <option value="{{ strtoupper($country->code) }}" data-flag="{{ strtolower($country->code) }}" @selected(strtoupper($country->code) === 'ES')>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div id="create-supplier-error" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="create-supplier-submit">
                        <span class="submit-label">{{ $documentFlow['save_party_label'] }}</span>
                        <span class="submit-loading d-none">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            Guardando...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@include('partials.line-category-modal', [
    'categoryOptions' => $expenseCategoryOptions ?? [],
    'showSuggestion' => true,
    'livewireKey' => $documentFlow['livewire_key'],
])
@endsection

@section('page-script')
@php
    $paymentTypeOptions = $paymentTypes->map(fn ($type) => [
        'id' => $type->id,
        'name' => $type->display_name,
    ])->values();
    $paymentStatusOptions = collect($statusOptions)->map(fn ($label, $id) => [
        'id' => $id,
        'name' => $label,
    ])->values();
    $defaultPaymentTypeIdJs = $defaultPaymentTypeId ?? null;
    $defaultPaymentAccountIdJs = $defaultPaymentAccountId ?? null;
    $expenseCategoryOptionsJs = collect($expenseCategoryOptions ?? [])->values();
@endphp
<script>
    $(function () {
        var documentFlow = @json($documentFlow);
        var partyNoun = documentFlow.mode === 'sell' ? 'cliente' : 'proveedor';
        var $createSupplierModal = $('#createSupplierModal');
        var expenseCategoryOptions = @json($expenseCategoryOptionsJs);

        $('.select2').not('.select2-supplier-country').not('.select2-enterprise').not('.payment-type-select').not('.payment-account-select').not('.payment-status-select').each(function () {
            var $this = $(this);
            $this.wrap('<div class="position-relative"></div>');
            $this.select2({
                dropdownParent: $this.parent(),
                width: '100%',
                allowClear: false
            });
        });

        var lastEnterpriseSearchTerm = '';
        var canCreateSupplier = $('#open-create-supplier-modal').length > 0;
        var $enterpriseSelectInit = $('#enterprise_id');

        if ($enterpriseSelectInit.length) {
            if (! $enterpriseSelectInit.parent().hasClass('position-relative')) {
                $enterpriseSelectInit.wrap('<div class="position-relative"></div>');
            }

            function foldEnterpriseAccent(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase();
            }

            $enterpriseSelectInit.select2({
                dropdownParent: $enterpriseSelectInit.parent(),
                width: '100%',
                allowClear: false,
                placeholder: documentFlow.party_placeholder,
                language: {
                    noResults: function () {
                        if (canCreateSupplier) {
                            return 'Sin resultados. Clic aquí o en «' + documentFlow.create_party_label + '» para darlo de alta con datos fiscales.';
                        }

                        return 'Sin resultados';
                    },
                    searching: function () {
                        return 'Buscando…';
                    }
                },
                templateResult: function (data) {
                    if (! data.id) {
                        return data.text;
                    }

                    var $el = $(data.element);
                    var type = $el.data('type');
                    var contacts = [];
                    try {
                        var rawContacts = $el.attr('data-contacts');
                        contacts = rawContacts ? JSON.parse(rawContacts) : [];
                        if (! Array.isArray(contacts)) {
                            contacts = [];
                        }
                    } catch (e) {
                        contacts = [];
                    }

                    var term = foldEnterpriseAccent($.trim(String($('.select2-container--open .select2-search__field').val() || '')));
                    var secondary = '';
                    if (term !== '' && contacts.length) {
                        for (var i = 0; i < contacts.length; i++) {
                            var contactLabel = contacts[i] && contacts[i].label ? String(contacts[i].label) : '';
                            if (contactLabel && foldEnterpriseAccent(contactLabel).indexOf(term) > -1) {
                                secondary = contactLabel;
                                break;
                            }
                        }
                    }
                    if (! secondary && contacts.length && contacts[0].label) {
                        secondary = String(contacts[0].label);
                    }
                    if (! secondary) {
                        secondary = $el.data('responsible') ? String($el.data('responsible')) : '';
                    }

                    var $root = $('<span class="d-block"></span>');
                    var $title = $('<span></span>').append(document.createTextNode(data.text));

                    if (type) {
                        $title.append($('<span class="text-muted ms-1"></span>').text('· ' + type));
                    }

                    $root.append($title);

                    if (secondary) {
                        $root.append(
                            $('<span class="d-block text-muted small"></span>').text(secondary)
                        );
                    }

                    return $root;
                },
                templateSelection: function (data) {
                    if (! data.id) {
                        return data.text;
                    }

                    var type = $(data.element).data('type');
                    if (! type) {
                        return data.text;
                    }

                    return $('<span></span>')
                        .append(document.createTextNode(data.text))
                        .append($('<span class="text-muted ms-1"></span>').text('· ' + type));
                },
                matcher: function (params, data) {
                    if ($.trim(params.term) === '') {
                        return data;
                    }

                    if (typeof data.text === 'undefined') {
                        return null;
                    }

                    var term = foldEnterpriseAccent(params.term);
                    var text = foldEnterpriseAccent(data.text);
                    var keywords = foldEnterpriseAccent($(data.element).data('keywords') || '');

                    if (text.indexOf(term) > -1 || keywords.indexOf(term) > -1) {
                        return data;
                    }

                    return null;
                }
            });

            $enterpriseSelectInit.on('select2:open', function () {
                var $searchField = $('.select2-container--open .select2-search__field');
                $searchField.off('input.enterpriseCreate').on('input.enterpriseCreate', function () {
                    lastEnterpriseSearchTerm = $.trim(String($(this).val() || ''));
                });
            });

            $(document).on('mouseenter', '#select2-enterprise_id-results .select2-results__message', function () {
                $(this).css({ cursor: 'pointer', color: 'var(--bs-primary)' });
            });
        }

        function initSupplierCountrySelect() {
            var $country = $('#supplier_country');
            if (! $country.length) {
                return;
            }

            if ($country.hasClass('select2-hidden-accessible')) {
                $country.select2('destroy');
            }

            $country.select2({
                dropdownParent: $createSupplierModal.length ? $createSupplierModal : $('body'),
                width: '100%',
                allowClear: true,
                placeholder: 'Seleccione un país',
                templateResult: function (data) {
                    if (!data.id) {
                        return data.text;
                    }

                    return $('<span><span class="fi fi-' + $(data.element).data('flag') + ' me-2"></span>' + data.text + '</span>');
                },
                templateSelection: function (data) {
                    if (!data.id) {
                        return data.text;
                    }

                    return $('<span><span class="fi fi-' + $(data.element).data('flag') + ' me-2"></span>' + data.text + '</span>');
                }
            });
        }

        if ($createSupplierModal.length) {
            initSupplierCountrySelect();
            $createSupplierModal.on('shown.bs.modal', initSupplierCountrySelect);
        }

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

        $('.expense-date').not('.payment-date').flatpickr({
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            locale: spanishLocale,
            allowInput: true
        });

        var paymentTypeOptions = @json($paymentTypeOptions);
        var paymentAccountOptions = @json($paymentAccountOptions);
        var paymentStatusOptions = @json($paymentStatusOptions);
        var defaultPaymentTypeId = @json($defaultPaymentTypeIdJs);
        var defaultPaymentAccountId = @json($defaultPaymentAccountIdJs);
        var $paymentsContainer = $('#expense-payments-container');
        var nextPaymentIndex = $paymentsContainer.find('.expense-payment-block').length;

        $('.document-type-btn').on('click', function () {
            if ($(this).prop('disabled')) {
                return;
            }

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
        var detectDocumentUrl = documentFlow.detect_document_url;
        var checkDocumentDuplicateUrl = documentFlow.check_duplicate_url;
        var createSupplierUrl = documentFlow.create_party_url;
        var csrfToken = @json(csrf_token());
        var $enterpriseSelect = $('#enterprise_id');
        var $enterpriseDetectionStatus = $('#enterprise-detection-status');
        var $documentNumberInput = $('#document_number');
        var $documentNumberDuplicateWarning = $('#document-number-duplicate-warning');
        var $expenseSubmitButtons = $('form.card-body .expense-submit-btn');
        var documentDuplicateCheckTimer = null;
        var documentDuplicateRequest = null;
        var documentNumberIsDuplicate = false;
        var $openCreateSupplierModal = $('#open-create-supplier-modal');
        var $createSupplierForm = $('#create-supplier-form');
        var $createSupplierError = $('#create-supplier-error');
        var detectedSupplierData = null;

        function setExpenseSubmitEnabled(enabled) {
            $expenseSubmitButtons.prop('disabled', !enabled);
            $expenseSubmitButtons.toggleClass('disabled', !enabled);
        }

        function clearDocumentDuplicateWarning() {
            documentNumberIsDuplicate = false;
            $documentNumberInput.removeClass('is-invalid');
            $documentNumberDuplicateWarning.addClass('d-none').text('');
            setExpenseSubmitEnabled(true);
        }

        function showDocumentDuplicateWarning(message) {
            documentNumberIsDuplicate = true;
            $documentNumberInput.addClass('is-invalid');
            $documentNumberDuplicateWarning
                .removeClass('d-none')
                .text(message || documentFlow.duplicate_message);
            setExpenseSubmitEnabled(false);
        }

        function scheduleDocumentDuplicateCheck() {
            if (!checkDocumentDuplicateUrl) {
                return;
            }

            clearTimeout(documentDuplicateCheckTimer);

            documentDuplicateCheckTimer = setTimeout(function () {
                checkDocumentDuplicate();
            }, 400);
        }

        function checkDocumentDuplicate() {
            if (!checkDocumentDuplicateUrl) {
                return;
            }

            var enterpriseId = $enterpriseSelect.val();
            var documentNumber = $.trim(String($documentNumberInput.val() || ''));

            if (!enterpriseId || documentNumber === '') {
                clearDocumentDuplicateWarning();
                return;
            }

            if (documentDuplicateRequest && typeof documentDuplicateRequest.abort === 'function') {
                documentDuplicateRequest.abort();
            }

            documentDuplicateRequest = $.ajax({
                url: checkDocumentDuplicateUrl,
                type: 'POST',
                data: {
                    enterprise_id: enterpriseId,
                    document_number: documentNumber,
                },
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
                success: function (response) {
                    if (!response || response.duplicate !== true) {
                        clearDocumentDuplicateWarning();
                        return;
                    }

                    var warningMessage = response.message || documentFlow.duplicate_message;
                    if (response.invoice && response.invoice.date) {
                        warningMessage += ' Registrado el ' + response.invoice.date + '.';
                    }

                    showDocumentDuplicateWarning(warningMessage);
                },
                error: function (xhr, textStatus) {
                    if (textStatus === 'abort') {
                        return;
                    }

                    clearDocumentDuplicateWarning();
                },
            });
        }

        function initPaymentDatePickers($context) {
            ($context || $(document)).find('.payment-date').each(function () {
                if (this._flatpickr) {
                    return;
                }

                flatpickr(this, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    locale: spanishLocale,
                    allowInput: true
                });
            });
        }

        function initPaymentSelects($context) {
            ($context || $paymentsContainer).find('.payment-type-select, .payment-account-select, .payment-status-select').each(function () {
                var $this = $(this);
                if ($this.hasClass('select2-hidden-accessible')) {
                    $this.select2('destroy');
                }

                if (! $this.parent().hasClass('position-relative')) {
                    $this.wrap('<div class="position-relative"></div>');
                }

                var config = {
                    dropdownParent: $this.parent(),
                    width: '100%',
                    allowClear: false,
                };

                if ($this.hasClass('payment-type-select')) {
                    config.placeholder = 'Selecciona forma de pago';
                } else if ($this.hasClass('payment-account-select')) {
                    config.placeholder = 'Selecciona cuenta';
                } else if ($this.hasClass('payment-status-select')) {
                    config.minimumResultsForSearch = Infinity;
                }

                $this.select2(config);
            });
        }

        function findPaymentAccountOption(accountId) {
            return paymentAccountOptions.find(function (option) {
                return String(option.id) === String(accountId);
            }) || null;
        }

        function accountAcceptsType(accountOption, typeId) {
            if (!accountOption || !typeId) {
                return true;
            }

            return (accountOption.payment_type_ids || []).some(function (id) {
                return String(id) === String(typeId);
            });
        }

        function filteredPaymentAccounts(currencyId, typeId) {
            return paymentAccountOptions.filter(function (option) {
                if (currencyId && String(option.currency_id) !== String(currencyId)) {
                    return false;
                }

                if (typeId && !accountAcceptsType(option, typeId)) {
                    return false;
                }

                return true;
            });
        }

        function filteredPaymentTypes(accountId) {
            var account = findPaymentAccountOption(accountId);

            if (!account) {
                return paymentTypeOptions;
            }

            return paymentTypeOptions.filter(function (option) {
                return (account.payment_type_ids || []).some(function (id) {
                    return String(id) === String(option.id);
                });
            });
        }

        function buildAccountSelectOptions(selectedValue, currencyId, typeId) {
            var accounts = filteredPaymentAccounts(currencyId, typeId);
            var html = '<option value="">Selecciona cuenta</option>';

            if (accounts.length === 0) {
                return html;
            }

            accounts.forEach(function (option) {
                var selected = String(selectedValue || '') === String(option.id) ? ' selected' : '';
                html += '<option value="' + escapeHtml(String(option.id)) + '" data-currency-id="' + escapeHtml(String(option.currency_id || '')) + '"' + selected + '>' +
                    escapeHtml(option.name + ' (' + option.currency_code + ')') +
                    '</option>';
            });

            return html;
        }

        function buildTypeSelectOptions(selectedValue, accountId) {
            var types = filteredPaymentTypes(accountId);
            var html = '<option value="">Selecciona forma de pago</option>';

            types.forEach(function (option) {
                var selected = String(selectedValue || '') === String(option.id) ? ' selected' : '';
                html += '<option value="' + escapeHtml(String(option.id)) + '"' + selected + '>' + escapeHtml(String(option.name)) + '</option>';
            });

            return html;
        }

        function refreshPaymentBlockSelectors($block) {
            var currencyId = $currencySelect.val() || '';
            var $accountSelect = $block.find('.payment-account-select');
            var $typeSelect = $block.find('.payment-type-select');
            var accountId = $accountSelect.val() || '';
            var typeId = $typeSelect.val() || '';

            var accounts = filteredPaymentAccounts(currencyId, typeId);
            if (accountId && !accounts.some(function (option) {
                return String(option.id) === String(accountId);
            })) {
                accountId = accounts[0] ? accounts[0].id : '';
            }

            var types = filteredPaymentTypes(accountId);
            if (typeId && !types.some(function (option) {
                return String(option.id) === String(typeId);
            })) {
                typeId = types[0] ? types[0].id : '';
            }

            accounts = filteredPaymentAccounts(currencyId, typeId);
            if (accountId && !accounts.some(function (option) {
                return String(option.id) === String(accountId);
            })) {
                accountId = accounts[0] ? accounts[0].id : '';
                types = filteredPaymentTypes(accountId);
                if (typeId && !types.some(function (option) {
                    return String(option.id) === String(typeId);
                })) {
                    typeId = types[0] ? types[0].id : '';
                }
            }

            if ($accountSelect.hasClass('select2-hidden-accessible')) {
                $accountSelect.select2('destroy');
            }

            if ($typeSelect.hasClass('select2-hidden-accessible')) {
                $typeSelect.select2('destroy');
            }

            $accountSelect.html(buildAccountSelectOptions(accountId, currencyId, typeId));
            $typeSelect.html(buildTypeSelectOptions(typeId, accountId));
        }

        function refreshAllPaymentBlocks() {
            $paymentsContainer.find('.expense-payment-block').each(function () {
                refreshPaymentBlockSelectors($(this));
            });

            initPaymentSelects($paymentsContainer);
        }

        function buildSelectOptions(options, placeholder, selectedValue) {
            var html = '<option value="">' + escapeHtml(placeholder) + '</option>';
            options.forEach(function (option) {
                var selected = String(selectedValue || '') === String(option.id) ? ' selected' : '';
                html += '<option value="' + escapeHtml(String(option.id)) + '"' + selected + '>' + escapeHtml(String(option.name)) + '</option>';
            });

            return html;
        }

        function createPaymentBlock(index, paymentData) {
            var payment = paymentData || {};
            var paymentDate = payment.payment_date ? String(payment.payment_date) : '{{ now()->toDateString() }}';
            var amount = payment.amount !== undefined && payment.amount !== null && payment.amount !== ''
                ? formatAmount(payment.amount)
                : '';
            var typeId = payment.type_id ? String(payment.type_id) : String(defaultPaymentTypeId || '');
            var accountId = payment.account_id ? String(payment.account_id) : String(defaultPaymentAccountId || '');
            var status = payment.status ? String(payment.status) : '2';
            var currencyId = $currencySelect.val() || '';

            return [
                '<div class="expense-payment-block border rounded p-3 mb-2" data-payment-index="' + index + '">',
                '  <div class="row g-3 align-items-end">',
                '    <div class="col-md-2 col-lg-2">',
                '      <label class="form-label" for="payment_date_' + index + '">' + escapeHtml(documentFlow.payment_date_label) + '</label>',
                '      <input type="text" id="payment_date_' + index + '" name="payments[' + index + '][payment_date]" class="form-control expense-date payment-date" value="' + escapeHtml(paymentDate) + '">',
                '    </div>',
                '    <div class="col-md-2 col-lg-2">',
                '      <label class="form-label" for="payment_amount_' + index + '">Importe</label>',
                '      <input type="text" inputmode="decimal" id="payment_amount_' + index + '" name="payments[' + index + '][amount]" class="form-control text-end payment-amount" value="' + escapeHtml(amount) + '">',
                '    </div>',
                '    <div class="col-md-2 col-lg-2">',
                '      <label class="form-label" for="payment_type_id_' + index + '">Forma de pago (*)</label>',
                '      <select id="payment_type_id_' + index + '" name="payments[' + index + '][type_id]" class="form-select payment-type-select">',
                buildTypeSelectOptions(typeId, accountId),
                '      </select>',
                '    </div>',
                '    <div class="col-md-3 col-lg-3">',
                '      <label class="form-label" for="payment_account_id_' + index + '">Cuenta (*)</label>',
                '      <select id="payment_account_id_' + index + '" name="payments[' + index + '][account_id]" class="form-select payment-account-select">',
                buildAccountSelectOptions(accountId, currencyId, typeId),
                '      </select>',
                '    </div>',
                '    <div class="col-md-2 col-lg-2">',
                '      <label class="form-label" for="payment_status_' + index + '">Estado (*)</label>',
                '      <select id="payment_status_' + index + '" name="payments[' + index + '][status]" class="form-select select2 payment-status-select">',
                (function () {
                    var html = '';
                    paymentStatusOptions.forEach(function (option) {
                        var selected = String(status) === String(option.id) ? ' selected' : '';
                        html += '<option value="' + escapeHtml(String(option.id)) + '"' + selected + '>' + escapeHtml(String(option.name)) + '</option>';
                    });
                    return html;
                })(),
                '      </select>',
                '    </div>',
                '    <div class="col-md-1 col-lg-1 d-flex justify-content-end">',
                '      <button type="button" class="btn btn-icon btn-label-danger remove-payment-btn" title="' + escapeHtml(documentFlow.remove_payment_title) + '">',
                '        <i class="ti ti-trash"></i>',
                '      </button>',
                '    </div>',
                '  </div>',
                '</div>',
            ].join('');
        }

        function calculateExpenseTotals() {
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

            return {
                base: roundMoney(base),
                vatAmount: roundMoney(vatAmount),
                retentionAmount: roundMoney(retentionAmount),
                total: roundMoney(total),
                vatByRate: vatByRate
            };
        }

        function updatePaymentRemoveButtons() {
            var $blocks = $paymentsContainer.find('.expense-payment-block');
            $blocks.find('.remove-payment-btn').prop('disabled', false);
            $('#expense-payments-empty').toggleClass('d-none', $blocks.length > 0);
        }

        function updatePaymentsSummary() {
            var totals = calculateExpenseTotals();
            var paid = 0;
            var $amountInputs = $paymentsContainer.find('.payment-amount');

            $amountInputs.each(function () {
                paid += asNumber($(this).val());
            });

            paid = roundMoney(paid);
            var total = roundMoney(totals.total);
            var pending = Math.max(roundMoney(total - paid), 0);
            var exceedsTotal = total > 0 && paid > total + 0.001;
            var $summary = $('#payments-summary');

            if ($amountInputs.length === 0) {
                $summary
                    .removeClass('text-danger')
                    .addClass('text-muted')
                    .text(documentFlow.payments_empty_summary + ' ' + formatAmount(total));
                return;
            }

            var summaryParts = [
                documentFlow.paid_label + ': ' + formatAmount(paid),
                'Total: ' + formatAmount(total),
            ];

            if (pending > 0 && ! exceedsTotal) {
                summaryParts.push('Pendiente: ' + formatAmount(pending));
            }

            var summaryText = summaryParts.join(' · ');

            if (exceedsTotal) {
                summaryText += ' — ' + documentFlow.payments_overflow_prefix + ' ' + documentFlow.payments_overflow_suffix;
            }

            $summary
                .toggleClass('text-danger', exceedsTotal)
                .toggleClass('text-muted', ! exceedsTotal)
                .text(summaryText);

            $paymentsContainer.find('.payment-amount').each(function () {
                $(this).toggleClass('is-invalid', exceedsTotal);
            });
        }

        function capPaymentAmountInput($input) {
            var totals = calculateExpenseTotals();
            var maxTotal = roundMoney(totals.total);

            if (maxTotal <= 0) {
                return;
            }

            var sumOthers = 0;

            $paymentsContainer.find('.payment-amount').not($input).each(function () {
                sumOthers += asNumber($(this).val());
            });

            var maxAllowed = Math.max(maxTotal - sumOthers, 0);
            var current = asNumber($input.val());

            if (current > maxAllowed + 0.001) {
                setAmountInputValue($input.get(0), maxAllowed > 0 ? maxAllowed : '');
            }
        }

        function paymentsExceedTotal() {
            var totals = calculateExpenseTotals();
            var total = roundMoney(totals.total);

            if (total <= 0) {
                return false;
            }

            var paid = 0;
            var hasSpecifiedAmount = false;

            $paymentsContainer.find('.payment-amount').each(function () {
                var value = $(this).val();
                if (value === '' || value === null) {
                    return;
                }

                hasSpecifiedAmount = true;
                paid += asNumber(value);
            });

            return hasSpecifiedAmount && roundMoney(paid) > total + 0.001;
        }

        initPaymentDatePickers($paymentsContainer);
        initPaymentSelects($paymentsContainer);
        refreshAllPaymentBlocks();
        updatePaymentRemoveButtons();
        updatePaymentsSummary();
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

        function assignDocumentFile(file) {
            if (!file || !$documentInput.length) {
                return false;
            }

            try {
                var transfer = new DataTransfer();
                transfer.items.add(file);
                $documentInput[0].files = transfer.files;
            } catch (error) {
                return false;
            }

            updateDocumentName();

            return true;
        }

        function preventDocumentDropDefaults(event) {
            event.preventDefault();
            event.stopPropagation();
        }

        $dropZone.on('click', function (event) {
            if ($(event.target).closest('#remove-document-file').length) {
                return;
            }

            $documentInput.trigger('click');
        });

        $dropZone.on('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            $documentInput.trigger('click');
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
            $dropZone.on(eventName, preventDocumentDropDefaults);
        });

        $dropZone.on('dragenter dragover', function () {
            $dropZone.addClass('border-primary bg-label-primary');
        });

        $dropZone.on('dragleave drop', function () {
            $dropZone.removeClass('border-primary bg-label-primary');
        });

        $dropZone.on('drop', function (event) {
            var droppedFiles = event.originalEvent.dataTransfer.files;

            if (!droppedFiles || droppedFiles.length === 0) {
                return;
            }

            assignDocumentFile(droppedFiles[0]);
        });

        $documentInput.on('change', updateDocumentName);

        $removeDocumentFileButton.on('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            $documentInput.val('');
            updateDocumentName();
        });

        var $lineCategoryModal = $('#lineCategoryModal');
        var $lineCategoryModalSelect = $('#line_category_modal_select');
        var $lineCategorySuggestion = $('#line-category-suggestion');
        var $lineCategorySuggestionText = $('#line-category-suggestion-text');
        var suggestedCategoryItems = [];
        var activeCategoryLineIndex = null;
        var suggestedCategoriesUrl = documentFlow.suggested_categories_url;
        var suggestedCategoriesRequest = null;

        function syncExpenseCategoryOptionsFromSelect() {
            var options = [];

            $lineCategoryModalSelect.find('option').each(function () {
                if (! this.value) {
                    return;
                }

                var $option = $(this);
                var $group = $option.parent('optgroup');

                options.push({
                    id: parseInt(this.value, 10),
                    name: $.trim(String($option.text() || '')),
                    group: $group.length ? String($group.attr('label') || '') : null,
                });
            });

            expenseCategoryOptions = options;
            $('#line-category-empty').toggleClass('d-none', options.length > 0);
        }

        function categoryNameById(categoryId) {
            if (!categoryId) {
                return '';
            }

            var match = (expenseCategoryOptions || []).find(function (option) {
                return String(option.id) === String(categoryId);
            });

            if (match) {
                return String(match.name);
            }

            var $option = $lineCategoryModalSelect.find('option[value="' + String(categoryId).replace(/"/g, '\\"') + '"]').first();

            return $option.length ? $.trim(String($option.text() || '')) : '';
        }

        function setLineCategory($row, categoryId, categoryName) {
            if (! $row || ! $row.length) {
                return;
            }

            var id = categoryId ? String(categoryId) : '';
            var resolvedName = categoryName || categoryNameById(id);
            var hasCategory = id !== '';
            var $badge = $row.find('.line-category-badge');

            $row.find('.line-category-id').val(hasCategory ? id : '');
            $badge
                .text(hasCategory ? (resolvedName || ('#' + id)) : 'Sin categoría')
                .toggleClass('bg-label-primary', hasCategory)
                .toggleClass('bg-label-secondary', !hasCategory);
        }

        function suggestionForLineIndex(lineIndex) {
            var item = suggestedCategoryItems[lineIndex] || null;
            if (!item || !item.category_id) {
                return null;
            }

            return {
                category_id: item.category_id,
                category_name: item.category_name || categoryNameById(item.category_id),
                description: item.description || '',
            };
        }

        function applySuggestedCategoriesToLines(onlyEmpty) {
            $linesBody.find('.expense-line').each(function (index) {
                var $row = $(this);
                var currentCategoryId = $.trim(String($row.find('.line-category-id').val() || ''));

                if (onlyEmpty && currentCategoryId !== '') {
                    return;
                }

                var suggestion = suggestionForLineIndex(index);
                if (!suggestion) {
                    return;
                }

                setLineCategory($row, suggestion.category_id, suggestion.category_name);
            });
        }

        function initLineCategoryModalSelect() {
            if (! $lineCategoryModalSelect.length) {
                return;
            }

            if ($lineCategoryModalSelect.hasClass('select2-hidden-accessible')) {
                $lineCategoryModalSelect.select2('destroy');
            }

            $lineCategoryModalSelect.select2({
                dropdownParent: $lineCategoryModal,
                width: '100%',
                allowClear: true,
                placeholder: 'Sin categoría',
            });
        }

        function openLineCategoryModal($row) {
            if (! $row || ! $row.length || ! $lineCategoryModal.length) {
                return;
            }

            activeCategoryLineIndex = parseInt($row.data('line-index'), 10);
            if (! Number.isFinite(activeCategoryLineIndex)) {
                activeCategoryLineIndex = $row.index();
            }

            var currentCategoryId = $.trim(String($row.find('.line-category-id').val() || ''));
            var suggestion = suggestionForLineIndex(activeCategoryLineIndex);

            initLineCategoryModalSelect();
            $lineCategoryModalSelect.val(currentCategoryId).trigger('change');

            if (suggestion && suggestion.category_id) {
                var suggestionLabel = suggestion.category_name || ('#' + suggestion.category_id);
                if (suggestion.description) {
                    suggestionLabel += ' · ' + suggestion.description;
                }
                $lineCategorySuggestionText.text(suggestionLabel);
                $lineCategorySuggestion.removeClass('d-none');
            } else {
                $lineCategorySuggestion.addClass('d-none');
                $lineCategorySuggestionText.text('');
            }

            bootstrap.Modal.getOrCreateInstance($lineCategoryModal.get(0)).show();
        }

        function loadSuggestedCategoriesForEnterprise(enterpriseId, options) {
            var config = options || {};
            suggestedCategoryItems = [];

            if (!suggestedCategoriesUrl || !enterpriseId) {
                return;
            }

            if (suggestedCategoriesRequest && typeof suggestedCategoriesRequest.abort === 'function') {
                suggestedCategoriesRequest.abort();
            }

            suggestedCategoriesRequest = $.ajax({
                url: suggestedCategoriesUrl,
                type: 'GET',
                data: {
                    enterprise_id: enterpriseId,
                },
                success: function (response) {
                    if (!response || response.success !== true) {
                        return;
                    }

                    suggestedCategoryItems = Array.isArray(response.items) ? response.items : [];

                    if (config.applyToEmptyLines !== false) {
                        applySuggestedCategoriesToLines(true);
                    }
                },
            });
        }

        function createLineRow(index, lineData) {
            var line = lineData || {};
            var concept = line.concept ? String(line.concept) : '';
            var categoryId = line.category_id ? String(line.category_id) : '';
            var categoryName = line.category_name || categoryNameById(categoryId);
            var hasCategory = categoryId !== '';
            var baseAmount = formatAmount(line.base_amount !== undefined ? line.base_amount : 0);
            var vatPercent = Number.isFinite(parseFloat(line.vat_percent)) ? parseFloat(line.vat_percent).toFixed(2) : '0';
            var retentionPercent = Number.isFinite(parseFloat(line.retention_percent)) ? parseFloat(line.retention_percent).toFixed(2) : '0';
            var allocationPercent = Number.isFinite(parseFloat(line.allocation_percent)) ? parseFloat(line.allocation_percent).toFixed(2) : '100';

            return [
                '<tr class="expense-line" data-line-index="' + index + '">',
                '  <td>',
                '    <div class="d-flex justify-content-between align-items-center mb-2 gap-2">',
                '      <label class="form-label small mb-0">Concepto (*)</label>',
                '      <div class="d-flex align-items-center gap-2">',
                '        <input type="hidden" name="lines[' + index + '][category_id]" class="line-category-id" value="' + escapeHtml(hasCategory ? categoryId : '') + '">',
                '        <button type="button" class="badge border-0 line-category-badge ' + (hasCategory ? 'bg-label-primary' : 'bg-label-secondary') + '" title="Seleccionar categoría">',
                escapeHtml(hasCategory ? (categoryName || ('#' + categoryId)) : 'Sin categoría'),
                '        </button>',
                '        <button type="button" class="remove-line-btn text-muted border-0 bg-transparent p-0" title="Eliminar línea" style="line-height: 1;">',
                '          <i class="ti ti-trash ti-xs"></i>',
                '        </button>',
                '      </div>',
                '    </div>',
                '    <div class="mb-2">',
                '      <input type="text" name="lines[' + index + '][concept]" class="form-control line-concept" value="' + escapeHtml(concept) + '">',
                '    </div>',
                '    <div class="row g-2">',
                '      <div class="col-md-3">',
                '        <label class="form-label small mb-1 d-block text-end">Base (*)</label>',
                '        <input type="text" inputmode="decimal" name="lines[' + index + '][base_amount]" class="form-control text-end line-base" value="' + escapeHtml(baseAmount) + '">',
                '      </div>',
                '      <div class="col-md-3">',
                '        <label class="form-label small mb-1 d-block text-end">IVA %</label>',
                '        <input type="text" inputmode="decimal" name="lines[' + index + '][vat_percent]" class="form-control text-end line-vat" value="' + escapeHtml(vatPercent) + '">',
                '      </div>',
                '      <div class="col-md-3">',
                '        <label class="form-label small mb-1 d-block text-end text-nowrap">Retención %</label>',
                '        <input type="text" inputmode="decimal" name="lines[' + index + '][retention_percent]" class="form-control text-end line-retention" value="' + escapeHtml(retentionPercent) + '">',
                '      </div>',
                '      <div class="col-md-3">',
                '        <label class="form-label small mb-1 d-block text-end">Imputa %</label>',
                '        <input type="text" inputmode="decimal" name="lines[' + index + '][allocation_percent]" class="form-control text-end line-allocation" value="' + escapeHtml(allocationPercent) + '">',
                '      </div>',
                '    </div>',
                '  </td>',
                '</tr>',
            ].join('');
        }

        function setExpenseDate(inputTarget, dateValue) {
            if (!dateValue) {
                return;
            }

            var $input = typeof inputTarget === 'string' ? $(inputTarget).first() : $(inputTarget).first();
            var inputElement = $input.get(0);
            if (inputElement && inputElement._flatpickr) {
                inputElement._flatpickr.setDate(dateValue, true, 'Y-m-d');
            } else if ($input.length) {
                $input.val(dateValue).trigger('change');
            }
        }

        function ensureEnterpriseOption(enterpriseId, enterpriseName) {
            var value = String(enterpriseId);
            if ($enterpriseSelect.find('option[value="' + value + '"]').length === 0) {
                $enterpriseSelect.append(
                    $('<option>', {
                        value: value,
                        text: enterpriseName
                    })
                );
            }
        }

        function isValidSupplierPhone(phone) {
            phone = (phone || '').trim();
            if (!phone) {
                return false;
            }
            if (!/^[+\-\d\s()]+$/.test(phone)) {
                return false;
            }
            var digits = phone.replace(/\D/g, '');
            return digits.length >= 9 && digits.length <= 15;
        }

        function prefillSupplierModal(supplier) {
            var data = supplier && typeof supplier === 'object' ? supplier : {};
            var form = $('#create-supplier-form').get(0);

            if (form) {
                form.reset();
            }

            $('#supplier_name').val(data.name || data.legal_name || data.brand_name || '');
            $('#supplier_identification_number').val(data.identification_number || '');
            $('#supplier_email').val(data.email || '');
            $('#supplier_phone').val(isValidSupplierPhone(data.phone) ? data.phone : '');
            $('#supplier_website').val(data.website || '');
            $('#supplier_address').val(data.address || '');
            $('#supplier_postal_code').val(data.postal_code || '');
            $('#supplier_locality').val(data.locality || '');
            $('#supplier_province').val(data.province || '');
            $('#supplier_country').val((data.country || 'ES').toUpperCase()).trigger('change');
        }

        function openCreateSupplierModal(extraData) {
            var supplier = Object.assign({}, detectedSupplierData || {}, extraData || {});

            if (!supplier.name && lastEnterpriseSearchTerm !== '') {
                supplier.name = lastEnterpriseSearchTerm;
            }

            if ($enterpriseSelect.hasClass('select2-hidden-accessible')) {
                $enterpriseSelect.select2('close');
            }

            prefillSupplierModal(supplier);
            $createSupplierError.addClass('d-none').text('');
            bootstrap.Modal.getOrCreateInstance($createSupplierModal.get(0)).show();
        }

        function updateEnterpriseDetectionUi(data) {
            detectedSupplierData = data.detected_supplier || null;
            $enterpriseDetectionStatus
                .removeClass('text-success text-warning text-danger text-muted')
                .text('');

            var match = data.enterprise_match || {};
            var enterpriseName = data.enterprise_name ? String(data.enterprise_name) : '';

            if (match.status === 'matched' && data.enterprise_id) {
                ensureEnterpriseOption(data.enterprise_id, enterpriseName);
                $enterpriseSelect.val(String(data.enterprise_id)).trigger('change');

                var sourceLabel = match.source === 'logo'
                    ? 'logo/marca'
                    : (match.source === 'tax_id' ? 'NIF/CIF' : 'nombre');

                $enterpriseDetectionStatus
                    .addClass('text-success')
                    .text('Proveedor reconocido por ' + sourceLabel + ': ' + enterpriseName + '.');
                return;
            }

            if (match.status === 'suggested' && data.enterprise_id) {
                ensureEnterpriseOption(data.enterprise_id, enterpriseName);
                $enterpriseSelect.val(String(data.enterprise_id)).trigger('change');
                $enterpriseDetectionStatus
                    .addClass('text-warning')
                    .text('Posible proveedor detectado: ' + enterpriseName + '. Confirma o selecciona otro.');
                return;
            }

            if (enterpriseName !== '') {
                $enterpriseDetectionStatus
                    .addClass('text-warning')
                    .text('No encontramos "' + enterpriseName + '" en tus proveedores. Selecciona uno o créalo con datos fiscales.');
                lastEnterpriseSearchTerm = enterpriseName;
            } else {
                $enterpriseDetectionStatus
                    .addClass('text-muted')
                    .text('Selecciona el proveedor de la factura o créalo si no existe.');
            }
        }

        function applyDetectedDocumentData(data) {
            if (!data || typeof data !== 'object') {
                return;
            }

            updateEnterpriseDetectionUi(data);

            if (data.document_number) {
                $('#document_number').val(String(data.document_number));
                scheduleDocumentDuplicateCheck();
            }

            if (data.date) {
                setExpenseDate('#date', String(data.date));
            }

            if (data.due_date) {
                setExpenseDate('#due_date', String(data.due_date));
            }

            if (data.payment_date) {
                setExpenseDate($paymentsContainer.find('.payment-date').first(), String(data.payment_date));
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
                initAmountInputs($linesBody);
                applySuggestedCategoriesToLines(true);
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

        if ($openCreateSupplierModal.length) {
            $openCreateSupplierModal.on('click', function () {
                openCreateSupplierModal();
            });

            $(document).on('mousedown', '#select2-enterprise_id-results .select2-results__message', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openCreateSupplierModal();
            });
        }

        if ($createSupplierForm.length) {
            $createSupplierForm.on('submit', function (event) {
                event.preventDefault();

                if (!createSupplierUrl) {
                    return;
                }

                var phoneValue = ($('#supplier_phone').val() || '').trim();
                if (phoneValue !== '') {
                    if (!/^[+\-\d\s()]+$/.test(phoneValue)) {
                        $createSupplierError.removeClass('d-none').text('El teléfono solo puede contener números, espacios y los símbolos + - ( ).');
                        return;
                    }

                    var phoneDigits = phoneValue.replace(/\D/g, '');
                    if (phoneDigits.length < 9 || phoneDigits.length > 15) {
                        $createSupplierError.removeClass('d-none').text('Introduce un teléfono válido (entre 9 y 15 dígitos).');
                        return;
                    }
                }

                var $submit = $('#create-supplier-submit');
                $submit.prop('disabled', true);
                $submit.find('.submit-label').addClass('d-none');
                $submit.find('.submit-loading').removeClass('d-none');
                $createSupplierError.addClass('d-none').text('');

                $.ajax({
                    url: createSupplierUrl,
                    type: 'POST',
                    data: $createSupplierForm.serialize(),
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function (response) {
                        if (!response || response.success !== true || !response.enterprise) {
                            $createSupplierError.removeClass('d-none').text('No se pudo crear el ' + partyNoun + '.');
                            return;
                        }

                        ensureEnterpriseOption(response.enterprise.id, response.enterprise.name);
                        $enterpriseSelect.val(String(response.enterprise.id)).trigger('change');
                        $enterpriseDetectionStatus
                            .removeClass('text-warning text-danger text-muted')
                            .addClass('text-success')
                            .text((documentFlow.mode === 'sell' ? 'Cliente' : 'Proveedor') + ' creado y seleccionado: ' + response.enterprise.name + '.');

                        bootstrap.Modal.getOrCreateInstance($createSupplierModal.get(0)).hide();
                    },
                    error: function (xhr) {
                        var message = 'No se pudo crear el ' + partyNoun + '.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            message = Object.values(xhr.responseJSON.errors).flat().join(' ');
                        }
                        $createSupplierError.removeClass('d-none').text(message);
                    },
                    complete: function () {
                        $submit.prop('disabled', false);
                        $submit.find('.submit-label').removeClass('d-none');
                        $submit.find('.submit-loading').addClass('d-none');
                    }
                });
            });
        }

        $('#add-expense-line').on('click', function () {
            var $row = $(createLineRow(nextLineIndex));
            $linesBody.append($row);
            initAmountInputs($row);

            var suggestion = suggestionForLineIndex(nextLineIndex);
            if (suggestion) {
                setLineCategory($row, suggestion.category_id, suggestion.category_name);
            }

            nextLineIndex += 1;
            refreshSummary();
        });

        $linesBody.on('click', '.line-category-badge', function () {
            openLineCategoryModal($(this).closest('.expense-line'));
        });

        if ($lineCategoryModal.length) {
            initLineCategoryModalSelect();
            syncExpenseCategoryOptionsFromSelect();
            $lineCategoryModal.on('shown.bs.modal', function () {
                syncExpenseCategoryOptionsFromSelect();
                initLineCategoryModalSelect();
            });

            $('#apply-line-category-suggestion').on('click', function () {
                var suggestion = suggestionForLineIndex(activeCategoryLineIndex);
                if (!suggestion) {
                    return;
                }

                $lineCategoryModalSelect.val(String(suggestion.category_id)).trigger('change');
            });

            $('#clear-line-category').on('click', function () {
                var $row = $linesBody.find('.expense-line').filter(function () {
                    return String($(this).data('line-index')) === String(activeCategoryLineIndex);
                }).first();

                setLineCategory($row, '', '');
                bootstrap.Modal.getOrCreateInstance($lineCategoryModal.get(0)).hide();
            });

            $('#save-line-category').on('click', function () {
                var $row = $linesBody.find('.expense-line').filter(function () {
                    return String($(this).data('line-index')) === String(activeCategoryLineIndex);
                }).first();
                var selectedId = $.trim(String($lineCategoryModalSelect.val() || ''));
                var selectedName = '';

                if (selectedId !== '') {
                    selectedName = $.trim(String($lineCategoryModalSelect.find('option:selected').first().text() || ''));
                    if (selectedName === '' || selectedName === 'Sin categoría') {
                        selectedName = categoryNameById(selectedId);
                    }
                }

                syncExpenseCategoryOptionsFromSelect();
                setLineCategory($row, selectedId, selectedName);
                bootstrap.Modal.getOrCreateInstance($lineCategoryModal.get(0)).hide();
            });

            function registerExpenseCategoryRefreshListener() {
                if (typeof Livewire === 'undefined' || typeof Livewire.on !== 'function') {
                    return;
                }

                Livewire.on('module-categories-refreshed', function (event) {
                    var detail = Array.isArray(event) ? event[0] : event;
                    if (! detail || detail.selectId !== 'line_category_modal_select') {
                        return;
                    }

                    setTimeout(function () {
                        syncExpenseCategoryOptionsFromSelect();
                        initLineCategoryModalSelect();
                    }, 250);
                });
            }

            if (window.Livewire) {
                registerExpenseCategoryRefreshListener();
            } else {
                document.addEventListener('livewire:init', registerExpenseCategoryRefreshListener);
            }
        }

        $enterpriseSelect.on('change.suggestedCategories', function () {
            loadSuggestedCategoriesForEnterprise($(this).val(), { applyToEmptyLines: true });
        });

        $(document).on('click', '.remove-line-btn', function () {
            if ($linesBody.find('.expense-line').length <= 1) {
                return;
            }

            $(this).closest('.expense-line').remove();
            refreshSummary();
        });

        function asNumber(value) {
            if (value === '' || value === null || value === undefined) {
                return 0;
            }

            var normalized = String(value).trim();

            if (normalized.includes(',')) {
                normalized = normalized.replace(/\./g, '').replace(',', '.');
            }

            var parsed = parseFloat(normalized);

            return Number.isFinite(parsed) ? parsed : 0;
        }

        function roundMoney(value) {
            return Math.round(asNumber(value) * 100) / 100;
        }

        function formatAmount(value) {
            return asNumber(value).toLocaleString('es-ES', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        function initAmountInput(element) {
            if (!element || typeof Cleave === 'undefined') {
                return;
            }

            if (element._amountCleave) {
                element._amountCleave.destroy();
                element._amountCleave = null;
            }

            element._amountCleave = new Cleave(element, {
                numeral: true,
                numeralThousandsGroupStyle: 'thousand',
                delimiter: '.',
                numeralDecimalMark: ',',
                numeralDecimalScale: 2,
            });
        }

        function initAmountInputs($container) {
            $container.find('.line-base, .payment-amount').each(function () {
                initAmountInput(this);
            });
        }

        function setAmountInputValue(element, value) {
            if (!element) {
                return;
            }

            if (!element._amountCleave) {
                initAmountInput(element);
            }

            if (value === '' || value === null || value === undefined) {
                element.value = '';
                return;
            }

            element._amountCleave.setRawValue(asNumber(value).toFixed(2));
        }

        function normalizeAmountInputsForSubmit() {
            $('.line-base, .payment-amount').each(function () {
                var $input = $(this);
                var rawValue = String($input.val() || '').trim();

                if ($input.hasClass('payment-amount') && rawValue === '') {
                    return;
                }

                var normalized = asNumber(rawValue).toFixed(2);

                if (this._amountCleave) {
                    this._amountCleave.destroy();
                    this._amountCleave = null;
                }

                $input.val(normalized);
            });
        }

        function formatPercent(value) {
            var number = asNumber(value);
            if (Number.isInteger(number)) {
                return String(number);
            }

            return number.toFixed(2).replace(/\.?0+$/, '');
        }

        $('#add-expense-payment').on('click', function () {
            var $previousBlock = $paymentsContainer.find('.expense-payment-block').last();
            var previousValues = {
                payment_date: $previousBlock.find('.payment-date').val() || '{{ now()->toDateString() }}',
                type_id: $previousBlock.find('.payment-type-select').val() || '',
                account_id: $previousBlock.find('.payment-account-select').val() || '',
                status: $previousBlock.find('.payment-status-select').val() || '2',
            };

            var $block = $(createPaymentBlock(nextPaymentIndex, previousValues));
            $paymentsContainer.append($block);
            nextPaymentIndex += 1;
            initPaymentDatePickers($block);
            initPaymentSelects($block);
            initAmountInputs($block);
            updatePaymentsSummary();
            updatePaymentRemoveButtons();
        });

        $paymentsContainer.on('click', '.remove-payment-btn', function () {
            $(this).closest('.expense-payment-block').remove();
            updatePaymentsSummary();
            updatePaymentRemoveButtons();
        });

        $paymentsContainer.on('input', '.payment-amount', function () {
            capPaymentAmountInput($(this));
            updatePaymentsSummary();
        });

        $('form.card-body').on('submit', function (event) {
            if (documentNumberIsDuplicate) {
                event.preventDefault();
                setExpenseSubmitEnabled(false);
                $documentNumberInput.trigger('focus');
                return false;
            }

            normalizeAmountInputsForSubmit();
        });

        function refreshSummary() {
            var totals = calculateExpenseTotals();

            $('#summary-base').text(formatAmount(totals.base));
            $('#summary-retention').text(formatAmount(totals.retentionAmount));
            $('#summary-total').text(formatAmount(totals.total));

            var vatRates = Object.keys(totals.vatByRate).sort(function (left, right) {
                return asNumber(left) - asNumber(right);
            });

            $summaryVatLines.empty();

            if (vatRates.length === 0) {
                $summaryVatLines.append(
                    '<div class="d-flex justify-content-between"><span>I.V.A. 0%</span><strong>' + formatAmount(0) + '</strong></div>'
                );
            } else {
                vatRates.forEach(function (rate, index) {
                    var marginClass = index < vatRates.length - 1 ? ' mb-1' : '';
                    $summaryVatLines.append(
                        '<div class="d-flex justify-content-between' + marginClass + '"><span>I.V.A. ' + formatPercent(rate) + '%</span><strong>' + formatAmount(totals.vatByRate[rate]) + '</strong></div>'
                    );
                });
            }

            updatePaymentsSummary();
        }

        $(document).on('input', '.line-base, .line-vat, .line-retention, .line-allocation', refreshSummary);

        $currencySelect.on('change', refreshAllPaymentBlocks);

        $(document).on('change', '.payment-account-select', function () {
            refreshPaymentBlockSelectors($(this).closest('.expense-payment-block'));

            if ($currencySelect.val()) {
                initPaymentSelects($(this).closest('.expense-payment-block'));
                return;
            }

            var selectedCurrencyId = $(this).find(':selected').data('currency-id');
            if (selectedCurrencyId) {
                $currencySelect.val(String(selectedCurrencyId)).trigger('change');
            } else {
                initPaymentSelects($(this).closest('.expense-payment-block'));
            }
        });

        $(document).on('change', '.payment-type-select', function () {
            refreshPaymentBlockSelectors($(this).closest('.expense-payment-block'));
            initPaymentSelects($(this).closest('.expense-payment-block'));
        });

        $enterpriseSelect.on('change', scheduleDocumentDuplicateCheck);
        $documentNumberInput.on('input', scheduleDocumentDuplicateCheck);

        initAmountInputs($('form.card-body'));
        if ($enterpriseSelect.val()) {
            loadSuggestedCategoriesForEnterprise($enterpriseSelect.val(), { applyToEmptyLines: true });
        }
        refreshSummary();

        @if ($errors->any())
        var $firstInvalidField = $('.is-invalid').first();
        if ($firstInvalidField.length) {
            $firstInvalidField.trigger('focus');
            $('html, body').animate({
                scrollTop: Math.max($firstInvalidField.offset().top - 120, 0),
            }, 250);
        }
        @endif
    });
</script>
@include('components.partials.select2-module-category-quick-create', [
    'selectId' => 'line_category_modal_select',
    'moduleKey' => 'services',
    'multiple' => false,
])
@endsection
