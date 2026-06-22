@extends('layouts/layoutMaster')

@section('title', __('Add Expense'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Expenses') }}/</span> {{ __('Create') }}</h4>
        <p class="text-muted">{{ __('Register a new expense') }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('expense.index') }}" class="btn btn-label-secondary">{{ __('Back to expenses') }}</a>
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
                            {{ strtoupper($documentTypeLabel) }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="border rounded p-4 h-100" style="border-style: dashed !important;">
                    <div class="d-flex flex-column align-items-center justify-content-center text-center h-100">
                        <i class="ti ti-cloud-upload ti-lg mb-2 text-muted"></i>
                        <p class="mb-2 fw-medium">{{ __('Drop a file or click to upload') }}</p>
                        <p class="mb-3 text-muted">{{ __('Optional: invoice, receipt, or tax document') }}</p>
                        <input type="file" class="form-control" id="document_file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="enterprise_id" class="form-label">{{ __('Provider') }}</label>
                        <select id="enterprise_id" name="enterprise_id" class="form-select select2 @error('enterprise_id') is-invalid @enderror" data-allow-clear="true">
                            <option value="">{{ __('Select a provider') }}</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('enterprise_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('enterprise_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="date" class="form-label">{{ __('Date') }} <span class="text-danger">*</span></label>
                        <input type="text" id="date" name="date" class="form-control expense-date @error('date') is-invalid @enderror" value="{{ old('date', now()->toDateString()) }}" required>
                        @error('date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="document_number" class="form-label">{{ __('Document number') }}</label>
                        <input type="text" id="document_number" name="document_number" class="form-control @error('document_number') is-invalid @enderror" value="{{ old('document_number') }}">
                        @error('document_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="expense_category" class="form-label">{{ __('Expense category') }}</label>
                        <input
                            type="text"
                            id="expense_category"
                            name="expense_category"
                            class="form-control @error('expense_category') is-invalid @enderror"
                            value="{{ old('expense_category') }}"
                            list="expense-category-options"
                            placeholder="{{ __('Purchases, subscriptions, supplies...') }}"
                        >
                        <datalist id="expense-category-options">
                            <option value="Office supplies"></option>
                            <option value="Software subscriptions"></option>
                            <option value="Transport"></option>
                            <option value="Marketing"></option>
                            <option value="Utilities"></option>
                            <option value="Professional services"></option>
                        </datalist>
                        @error('expense_category')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="row g-3 align-items-end">
            <div class="col-lg-8">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Concept') }}</th>
                                <th class="text-end">{{ __('Base') }}</th>
                                <th class="text-end">{{ __('VAT %') }}</th>
                                <th class="text-end">{{ __('Retention %') }}</th>
                                <th class="text-end">{{ __('Allocate %') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <input type="text" id="concept" name="concept" class="form-control @error('concept') is-invalid @enderror" value="{{ old('concept') }}" required>
                                    @error('concept')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <input type="number" id="base_amount" name="base_amount" class="form-control text-end @error('base_amount') is-invalid @enderror" min="0.01" step="0.01" value="{{ old('base_amount', '0.00') }}" required>
                                    @error('base_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <input type="number" id="vat_percent" name="vat_percent" class="form-control text-end @error('vat_percent') is-invalid @enderror" min="0" max="100" step="0.01" value="{{ old('vat_percent', '0') }}">
                                    @error('vat_percent')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <input type="number" id="retention_percent" name="retention_percent" class="form-control text-end @error('retention_percent') is-invalid @enderror" min="0" max="100" step="0.01" value="{{ old('retention_percent', '0') }}">
                                    @error('retention_percent')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    <input type="number" id="allocation_percent" name="allocation_percent" class="form-control text-end @error('allocation_percent') is-invalid @enderror" min="0.01" max="100" step="0.01" value="{{ old('allocation_percent', '100') }}">
                                    @error('allocation_percent')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card bg-label-warning mb-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ __('Tax base') }}</span>
                            <strong id="summary-base">0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ __('VAT amount') }}</span>
                            <strong id="summary-vat">0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ __('Retention') }}</span>
                            <strong id="summary-retention">0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ __('Allocated total') }}</span>
                            <strong id="summary-total">0.00</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_investment" name="is_investment" value="1" {{ old('is_investment') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_investment">{{ __('This is an investment') }}</label>
                </div>
            </div>
            <div class="col-md-8">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="cash_criteria" name="cash_criteria" value="1" {{ old('cash_criteria') ? 'checked' : '' }}>
                    <label class="form-check-label" for="cash_criteria">{{ __('Expense subject to cash accounting criteria') }}</label>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">{{ __('Payment') }}</h5>
        <div class="p-3 rounded bg-label-warning">
            <div class="row g-3">
                <div class="col-md-2">
                    <label for="payment_date" class="form-label">{{ __('Payment date') }} <span class="text-danger">*</span></label>
                    <input type="text" id="payment_date" name="payment_date" class="form-control expense-date @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', now()->toDateString()) }}" required>
                    @error('payment_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="payment_amount" class="form-label">{{ __('Amount') }}</label>
                    <input type="number" id="payment_amount" name="payment_amount" class="form-control text-end @error('payment_amount') is-invalid @enderror" step="0.01" min="0.01" value="{{ old('payment_amount') }}" placeholder="{{ __('Auto from totals') }}">
                    @error('payment_amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="type_id" class="form-label">{{ __('Payment method') }} <span class="text-danger">*</span></label>
                    <select id="type_id" name="type_id" class="form-select select2 @error('type_id') is-invalid @enderror" required>
                        <option value="">{{ __('Select payment method') }}</option>
                        @foreach ($paymentTypes as $paymentType)
                            <option value="{{ $paymentType->id }}" {{ old('type_id') == $paymentType->id ? 'selected' : '' }}>{{ $paymentType->name }}</option>
                        @endforeach
                    </select>
                    @error('type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="account_id" class="form-label">{{ __('Company account') }} <span class="text-danger">*</span></label>
                    <select id="account_id" name="account_id" class="form-select select2 @error('account_id') is-invalid @enderror" required>
                        <option value="">{{ __('Select account') }}</option>
                        @foreach ($paymentAccounts as $account)
                            <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }} ({{ strtoupper((string) ($account->currency->code ?? 'USD')) }})
                            </option>
                        @endforeach
                    </select>
                    @error('account_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">{{ __('Status') }} <span class="text-danger">*</span></label>
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
                <label for="remarks" class="form-label">{{ __('Personal comment') }}</label>
                <textarea id="remarks" name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3">{{ old('remarks') }}</textarea>
                @error('remarks')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="tags" class="form-label">{{ __('Tags / Project') }}</label>
                <input type="text" id="tags" name="tags" class="form-control @error('tags') is-invalid @enderror" value="{{ old('tags') }}" placeholder="{{ __('e.g. operations, q2, project-x') }}">
                @error('tags')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <a href="{{ route('expense.index') }}" class="btn btn-label-secondary">{{ __('Cancel') }}</a>
            <div class="d-flex gap-2">
                <button type="submit" name="submit_action" value="draft" class="btn btn-label-primary">{{ __('Save Draft') }}</button>
                <button type="submit" name="submit_action" value="save" class="btn btn-primary">{{ __('Save Expense') }}</button>
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
                width: '100%'
            });
        });

        $('.expense-date').flatpickr({
            dateFormat: 'Y-m-d',
            allowInput: true
        });

        $('.document-type-btn').on('click', function () {
            var selectedType = $(this).data('document-type');
            $('#document_type').val(selectedType);
            $('.document-type-btn').removeClass('btn-primary').addClass('btn-outline-secondary');
            $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
        });

        var $baseInput = $('#base_amount');
        var $vatPercentInput = $('#vat_percent');
        var $retentionPercentInput = $('#retention_percent');
        var $allocationPercentInput = $('#allocation_percent');

        function asNumber(value) {
            var parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function formatAmount(value) {
            return asNumber(value).toFixed(2);
        }

        function refreshSummary() {
            var base = asNumber($baseInput.val());
            var vatPercent = asNumber($vatPercentInput.val());
            var retentionPercent = asNumber($retentionPercentInput.val());
            var allocationPercent = asNumber($allocationPercentInput.val());

            var vatAmount = base * (vatPercent / 100);
            var retentionAmount = base * (retentionPercent / 100);
            var total = (base + vatAmount - retentionAmount) * (allocationPercent / 100);

            $('#summary-base').text(formatAmount(base));
            $('#summary-vat').text(formatAmount(vatAmount));
            $('#summary-retention').text(formatAmount(retentionAmount));
            $('#summary-total').text(formatAmount(total));
        }

        $baseInput.on('input', refreshSummary);
        $vatPercentInput.on('input', refreshSummary);
        $retentionPercentInput.on('input', refreshSummary);
        $allocationPercentInput.on('input', refreshSummary);
        refreshSummary();
    });
</script>
@endsection
