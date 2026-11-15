@extends('layouts/layoutMaster')

@section('title', __('Conciliación').' · '.$account->name)

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Cuentas de pago') }}/{{ $account->name }}/</span>
            {{ __('Conciliación') }} {{ $statement->periodLabel() }}
        </h4>
        <p class="text-muted mb-0">
            {{ $statement->original_filename ?? __('Extracto') }}
            · {{ __('Revisá movimiento a movimiento, registrá traspasos o ignorá lo que no aplica.') }}
        </p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('update', $account)
            <button type="button" class="btn btn-success waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#uploadStatementModal">
                <i class="ti ti-upload me-1"></i>{{ __('Subir extracto') }}
            </button>
        @endcan
        <a href="{{ route('payment-account.show', $account) }}#statement-files" class="btn btn-label-info waves-effect waves-light">
            <i class="ti ti-files me-1"></i>{{ __('Archivos subidos') }}
        </a>
        @if ($statement->isDownloadable())
            <a href="{{ route('payment-account.statements.download', [$account, $statement]) }}" class="btn btn-label-secondary waves-effect waves-light">
                <i class="ti ti-download me-1"></i>{{ __('Descargar extracto') }}
            </a>
        @endif
        <a href="{{ route('payment-account.show', $account) }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Volver a la cuenta') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-muted small">{{ __('Líneas') }}</div>
                <div class="fw-semibold">{{ $counts['all'] }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">{{ __('Pendientes') }}</div>
                <div class="fw-semibold text-warning">{{ $counts['open'] }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">{{ __('Asociados') }}</div>
                <div class="fw-semibold text-success">{{ $counts['matched'] }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">{{ __('Ignorados') }}</div>
                <div class="fw-semibold">{{ $counts['dismissed'] }}</div>
            </div>
        </div>
        @if (! empty($summary))
            <div class="border-top mt-3 pt-3 small text-muted">
                {{ __('Libro') }}:
                {{ isset($summary['book_total']) ? number_format((float) $summary['book_total'], 2, ',', '.') : '—' }}
                · {{ __('Extracto') }}:
                {{ isset($summary['statement_total']) && $summary['statement_total'] !== null ? number_format((float) $summary['statement_total'], 2, ',', '.') : '—' }}
                · {{ __('Diferencia') }}:
                @if (($summary['balanced'] ?? false) === true)
                    <span class="text-success">{{ __('Empatado') }}</span>
                @elseif (isset($summary['difference']) && $summary['difference'] !== null)
                    <span class="text-danger">{{ number_format((float) $summary['difference'], 2, ',', '.') }}</span>
                @else
                    —
                @endif
            </div>
        @endif
    </div>
</div>

<ul class="nav nav-pills mb-3 gap-1">
    @foreach ([
        'open' => __('Pendientes').' ('.$counts['open'].')',
        'matched' => __('Asociados').' ('.$counts['matched'].')',
        'dismissed' => __('Ignorados').' ('.$counts['dismissed'].')',
        'all' => __('Todos').' ('.$counts['all'].')',
    ] as $key => $label)
        <li class="nav-item">
            <a
                class="nav-link {{ $filter === $key ? 'active' : '' }}"
                href="{{ route('payment-account.statements.reconcile', [$account, $statement, 'filter' => $key]) }}"
            >{{ $label }}</a>
        </li>
    @endforeach
</ul>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Fecha') }}</th>
                        <th>{{ __('Concepto') }}</th>
                        <th class="text-end">{{ __('Importe') }}</th>
                        <th>{{ __('Estado') }}</th>
                        <th class="text-end">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        @php
                            $amount = (float) $line->amount;
                            $isCredit = $amount > 0;
                            $concept = $line->reference ?: $line->description ?: '—';
                        @endphp
                        <tr>
                            <td class="text-nowrap">
                                {{ $line->occurred_at?->format('d/m/Y') ?? '—' }}
                                @if ($line->external_id)
                                    <div class="small text-muted">#{{ $line->external_id }}</div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $concept }}</div>
                                @if ($line->payment)
                                    <div class="small text-success">
                                        {{ __('Pago') }} #{{ $line->payment_id }}
                                        · {{ $line->payment->remarks ? \Illuminate\Support\Str::limit($line->payment->remarks, 60) : '' }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap fw-medium {{ $isCredit ? 'text-success' : 'text-danger' }}">
                                {{ number_format($amount, 2, ',', '.') }}
                                {{ strtoupper((string) ($line->currency ?: $account->currency?->code ?: '')) }}
                            </td>
                            <td>
                                @if ($line->isDismissed())
                                    <span class="badge bg-label-secondary">{{ __('Ignorado') }}</span>
                                @elseif ($line->payment_id)
                                    <span class="badge bg-label-success">{{ __('Asociado') }}</span>
                                @else
                                    <span class="badge bg-label-warning">{{ __('Pendiente') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                    @can('update', $account)
                                        @if ($line->isDismissed())
                                            <form method="POST" action="{{ route('payment-account.statements.lines.undismiss', [$account, $statement, $line]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-label-secondary">{{ __('Restaurar') }}</button>
                                            </form>
                                        @elseif ($line->payment_id)
                                            @php
                                                $sourceReference = (string) ($line->payment?->source_reference_id ?? '');
                                                $canUnlink = $sourceReference === 'statement-line:'.$line->id
                                                    || (
                                                        (string) ($line->payment?->source_provider ?? '') === 'transfer'
                                                        && str_starts_with($sourceReference, 'transfer:')
                                                        && str_ends_with($sourceReference, ':in')
                                                    );
                                            @endphp
                                            @if ($line->payment?->invoice_id)
                                                <a
                                                    href="{{ route('invoice.show', $line->payment->invoice_id) }}"
                                                    class="btn btn-sm btn-label-primary"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >{{ __('Ver factura') }}</a>
                                            @endif
                                            @if ($canUnlink)
                                                <form method="POST" action="{{ route('payment-account.statements.lines.unlink', [$account, $statement, $line]) }}" onsubmit="return confirm(@json(__('¿Desasociar este movimiento y revertir el pago?')))">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-label-warning">{{ __('Desasociar') }}</button>
                                                </form>
                                            @endif
                                        @elseif (! $line->payment_id)
                                            @if ($isCredit)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#transferModal{{ $line->id }}"
                                                >{{ __('Traspaso') }}</button>
                                            @else
                                                <a
                                                    href="{{ route('expense.create', [
                                                        'account_id' => $account->id,
                                                        'amount' => abs($amount),
                                                        'base_amount' => abs($amount),
                                                        'date' => $line->occurred_at?->format('Y-m-d'),
                                                        'payment_date' => $line->occurred_at?->format('Y-m-d'),
                                                        'remarks' => $concept,
                                                        'concept' => $concept,
                                                    ]) }}"
                                                    class="btn btn-sm btn-label-danger"
                                                >{{ __('Crear gasto') }}</a>
                                            @endif
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-label-info"
                                                data-bs-toggle="modal"
                                                data-bs-target="#linkInvoiceModal{{ $line->id }}"
                                            >{{ __('Asociar factura') }}</button>
                                            <form method="POST" action="{{ route('payment-account.statements.lines.dismiss', [$account, $statement, $line]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-label-secondary">{{ __('Ignorar') }}</button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                {{ __('No hay movimientos en este filtro.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@can('update', $account)
    @foreach ($lines as $line)
        @if (! $line->payment_id && ! $line->isDismissed() && (float) $line->amount > 0)
            <div class="modal fade" id="transferModal{{ $line->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('payment-account.statements.lines.transfer', [$account, $statement, $line]) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Registrar traspaso') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">
                                {{ __('Se crea una salida en la cuenta de origen y una entrada en') }}
                                <strong>{{ $account->name }}</strong>
                                {{ __('por') }}
                                <strong>{{ number_format((float) $line->amount, 2, ',', '.') }}</strong>
                                ({{ $line->occurred_at?->format('d/m/Y') ?? '—' }}).
                            </p>
                            <div class="mb-3">
                                <label class="form-label" for="from_account_id_{{ $line->id }}">{{ __('Desde cuenta') }} (*)</label>
                                <select name="from_account_id" id="from_account_id_{{ $line->id }}" class="form-select" required>
                                    <option value="">{{ __('Elegir…') }}</option>
                                    @foreach ($transferAccounts as $fromAccount)
                                        <option value="{{ $fromAccount->id }}">
                                            {{ $fromAccount->name }}
                                            @if ($fromAccount->currency?->code)
                                                ({{ strtoupper($fromAccount->currency->code) }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ __('Ej.: Stripe → Caja Rural') }}</div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="remarks_{{ $line->id }}">{{ __('Nota') }}</label>
                                <input
                                    type="text"
                                    name="remarks"
                                    id="remarks_{{ $line->id }}"
                                    class="form-control"
                                    value="{{ old('remarks', $line->reference ?: $line->description) }}"
                                    maxlength="1000"
                                >
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Registrar traspaso') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if (! $line->payment_id && ! $line->isDismissed())
            @php
                $candidates = $invoiceCandidatesByLine[(int) $line->id] ?? [
                    'invoices' => [],
                    'suggested_invoice_id' => null,
                    'suggested_remarks' => $line->reference ?: $line->description,
                    'amount' => abs((float) $line->amount),
                ];
                $lineInvoiceOptions = collect($candidates['invoices'] ?? []);
                $suggestedInvoiceId = $candidates['suggested_invoice_id'] ?? null;
                $suggestedRemarks = $candidates['suggested_remarks']
                    ?? ($line->reference ?: $line->description);
                $lineCurrency = strtoupper((string) ($line->currency ?: $account->currency?->code ?: ''));
                $bankAmount = abs((float) $line->amount);
            @endphp
            <div class="modal fade" id="linkInvoiceModal{{ $line->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('payment-account.statements.lines.link-invoice', [$account, $statement, $line]) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Asociar a factura existente') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">
                                {{ __('Se creará un pago por el importe del movimiento. Si la factura está en otra divisa, se salda el saldo completo de la factura y se guarda el tipo de cambio implícito.') }}
                            </p>
                            <p class="mb-3 text-muted small">
                                {{ $line->occurred_at?->format('d/m/Y') ?? '—' }}
                                · {{ number_format($bankAmount, 2, ',', '.') }} {{ $lineCurrency }}
                                · {{ $line->reference ?: $line->description ?: '—' }}
                            </p>
                            <div class="mb-3">
                                <label class="form-label" for="invoice_id_{{ $line->id }}">{{ __('Factura') }} (*)</label>
                                <select
                                    name="invoice_id"
                                    id="invoice_id_{{ $line->id }}"
                                    class="form-select js-link-invoice-select"
                                    required
                                    data-line-currency="{{ $lineCurrency }}"
                                    data-bank-amount="{{ $bankAmount }}"
                                    data-fx-target="link_fx_{{ $line->id }}"
                                    data-placeholder="{{ __('Buscar número o empresa…') }}"
                                >
                                    <option value="">{{ __('Elegir…') }}</option>
                                    @forelse ($lineInvoiceOptions as $invoiceOption)
                                        @php
                                            $optionCurrency = strtoupper((string) ($invoiceOption['currency_code'] ?? ''));
                                            $optionBalance = (float) ($invoiceOption['balance'] ?? 0);
                                            $optionDate = filled($invoiceOption['date'] ?? null)
                                                ? \Carbon\Carbon::parse($invoiceOption['date'])->format('d/m/Y')
                                                : '';
                                            $optionEnterprise = $invoiceOption['enterprise_name'] !== ''
                                                ? $invoiceOption['enterprise_name']
                                                : __('Sin empresa');
                                            $matchedByEnterprise = ! empty($invoiceOption['matched_by_enterprise']);
                                        @endphp
                                        <option
                                            value="{{ $invoiceOption['id'] }}"
                                            @selected((int) ($suggestedInvoiceId ?? 0) === (int) $invoiceOption['id'])
                                            data-number="{{ $invoiceOption['number'] }}"
                                            data-date="{{ $optionDate }}"
                                            data-enterprise="{{ $optionEnterprise }}"
                                            data-matched="{{ $matchedByEnterprise ? '1' : '0' }}"
                                            data-currency="{{ $optionCurrency }}"
                                            data-balance="{{ $optionBalance }}"
                                        >
                                            {{ $invoiceOption['number'] }}
                                            @if ($optionDate !== '')
                                                · {{ $optionDate }}
                                            @endif
                                            @if ($matchedByEnterprise)
                                                · {{ __('empresa') }}
                                            @endif
                                            · {{ $optionEnterprise }}
                                            · {{ __('saldo') }} {{ number_format($optionBalance, 2, ',', '.') }}
                                            {{ $optionCurrency }}
                                        </option>
                                    @empty
                                        <option value="" disabled>{{ __('No hay facturas con saldo pendiente') }}</option>
                                    @endforelse
                                </select>
                            </div>
                            <div id="link_fx_{{ $line->id }}" class="alert alert-info d-none mb-3" role="alert"></div>
                            <div class="mb-0">
                                <label class="form-label" for="link_remarks_{{ $line->id }}">{{ __('Nota') }}</label>
                                <input
                                    type="text"
                                    name="remarks"
                                    id="link_remarks_{{ $line->id }}"
                                    class="form-control"
                                    value="{{ old('remarks', $suggestedRemarks) }}"
                                    maxlength="1000"
                                >
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                            <button type="submit" class="btn btn-primary" @disabled($lineInvoiceOptions->isEmpty())>{{ __('Asociar') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

    @php
        $reconcileSummary = $statement->validation_summary ?? [];
        $reconcileDefaultPeriod = ($reconcileSummary['period_kind'] ?? '') === 'quarter'
            ? 'q:'.(int) ($reconcileSummary['period_quarter'] ?? 1)
            : 'm:'.(int) ($statement->period_month ?? now()->month);
    @endphp
    @include('payment-account.partials.upload-statement-modal', [
        'account' => $account,
        'defaultYear' => (int) ($statement->period_year ?? now()->year),
        'defaultPeriod' => $reconcileDefaultPeriod,
    ])
@endcan
@endsection

@section('page-script')
<script>
$(function () {
    function formatMoney(amount, currency) {
        try {
            return new Intl.NumberFormat('es-ES', {
                style: 'currency',
                currency: currency || 'EUR',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        } catch (error) {
            return Number(amount).toFixed(2) + ' ' + (currency || '');
        }
    }

    function optionMeta($option) {
        return {
            number: String($option.data('number') || $option.text() || ''),
            date: String($option.data('date') || ''),
            enterprise: String($option.data('enterprise') || ''),
            matched: String($option.data('matched') || '') === '1',
            currency: String($option.data('currency') || '').toUpperCase(),
            balance: Number($option.data('balance') || 0)
        };
    }

    function formatInvoiceOption(data, compact) {
        if (! data.id) {
            return data.text;
        }

        var meta = optionMeta($(data.element));
        var titleParts = [meta.number];

        if (meta.date) {
            titleParts.push(meta.date);
        }

        if (meta.matched) {
            titleParts.push(@json(__('empresa')));
        }

        var subtitle = [meta.enterprise || @json(__('Sin empresa'))]
            .concat([@json(__('saldo')) + ' ' + formatMoney(meta.balance, meta.currency || 'EUR')])
            .join(' · ');

        if (compact) {
            return $('<span class="text-truncate d-inline-block w-100"></span>').text(
                titleParts.concat([meta.enterprise, @json(__('saldo')) + ' ' + formatMoney(meta.balance, meta.currency || 'EUR')]).join(' · ')
            );
        }

        return $(
            '<div class="py-1">' +
                '<div class="fw-semibold text-truncate"></div>' +
                '<div class="small text-muted text-truncate"></div>' +
            '</div>'
        )
            .find('.fw-semibold').text(titleParts.join(' · ')).end()
            .find('.small').text(subtitle).end();
    }

    function updateFxHelper($select) {
        var targetId = $select.data('fx-target');
        var $box = targetId ? $('#' + targetId) : $();
        if (! $box.length) {
            return;
        }

        var $option = $select.find('option:selected');
        var lineCurrency = String($select.data('line-currency') || '').toUpperCase();
        var bankAmount = Number($select.data('bank-amount') || 0);
        var meta = optionMeta($option);

        if (! $option.val() || ! meta.currency || ! lineCurrency || meta.currency === lineCurrency || meta.balance <= 0) {
            $box.addClass('d-none').text('');
            return;
        }

        var rate = bankAmount / meta.balance;
        $box
            .removeClass('d-none')
            .text(
                'Divisas distintas: pago ' + formatMoney(bankAmount, lineCurrency)
                + ' → se aplica ' + formatMoney(meta.balance, meta.currency)
                + ' a la factura (TC ≈ ' + rate.toFixed(6) + ' ' + lineCurrency + '/' + meta.currency + ').'
            );
    }

    $('.js-link-invoice-select').each(function () {
        var $select = $(this);
        var $modal = $select.closest('.modal');

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.wrap('<div class="position-relative"></div>');
        $select.select2({
            dropdownParent: $modal.length ? $modal : $select.parent(),
            width: '100%',
            placeholder: $select.data('placeholder') || @json(__('Elegir…')),
            allowClear: true,
            minimumResultsForSearch: 0,
            templateResult: function (data) {
                return formatInvoiceOption(data, false);
            },
            templateSelection: function (data) {
                return formatInvoiceOption(data, true);
            }
        });

        updateFxHelper($select);
        $select.on('change', function () {
            updateFxHelper($select);
        });
    });
});
</script>
@endsection
