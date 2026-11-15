@php
    $defaultYear = (int) old('period_year', $defaultYear ?? now()->year);
    $defaultPeriod = old('period', $defaultPeriod ?? ('m:'.now()->month));
    $yearOptions = [
        now()->year,
        now()->year - 1,
        now()->year - 2,
    ];
@endphp

<div class="modal fade" id="uploadStatementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('payment-account.statements.store', $account) }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Subir extracto bancario') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    {{ __('CSV/TXT se concilian automáticamente con los pagos. PDF o Excel usan el saldo informado.') }}
                </p>

                <div class="mb-3">
                    <label class="form-label">{{ __('Cuenta') }}</label>
                    <input type="text" class="form-control" value="{{ $account->name }}{{ $account->currency?->code ? ' ('.strtoupper((string) $account->currency->code).')' : '' }}" disabled>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="period_year" class="form-label">{{ __('Año') }}</label>
                        <select name="period_year" id="period_year" class="form-select" required>
                            @foreach ($yearOptions as $year)
                                <option value="{{ $year }}" @selected($defaultYear === (int) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="period" class="form-label">{{ __('Periodo') }}</label>
                        <select name="period" id="period" class="form-select" required>
                            <optgroup label="{{ __('Mes') }}">
                                @for ($month = 1; $month <= 12; $month++)
                                    <option value="m:{{ $month }}" @selected($defaultPeriod === 'm:'.$month)>
                                        {{ \Carbon\Carbon::create(null, $month, 1)->translatedFormat('F') }}
                                    </option>
                                @endfor
                            </optgroup>
                            <optgroup label="{{ __('Trimestre') }}">
                                @for ($quarter = 1; $quarter <= 4; $quarter++)
                                    <option value="q:{{ $quarter }}" @selected($defaultPeriod === 'q:'.$quarter)>Q{{ $quarter }}</option>
                                @endfor
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="statement_balance" class="form-label">{{ __('Saldo del extracto (opcional)') }}</label>
                    <input
                        type="number"
                        step="0.01"
                        name="statement_balance"
                        id="statement_balance"
                        class="form-control"
                        value="{{ old('statement_balance') }}"
                        placeholder="0.00"
                    >
                    <div class="form-text">{{ __('Necesario para PDF/Excel sin líneas parseadas') }}</div>
                </div>

                <div class="mb-0">
                    <label for="statement-files" class="form-label">{{ __('Archivos') }} <span class="text-danger">*</span></label>
                    <input
                        type="file"
                        name="files[]"
                        id="statement-files"
                        class="form-control"
                        multiple
                        required
                        accept=".csv,.txt,.pdf,.xlsx,.xls,text/csv,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    >
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-upload me-1"></i>{{ __('Subir y conciliar') }}
                </button>
            </div>
        </form>
    </div>
</div>
