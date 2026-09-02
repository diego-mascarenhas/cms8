@extends('layouts/layoutMaster')

@section('title', 'Tarifas')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Cuentas/</span> {{ $team->name }}</h4>
        <p class="text-muted">Tarifas de consumo</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('help.team-billing') }}" class="btn btn-label-primary waves-effect waves-light" target="_blank" rel="noopener">
            <i class="ti ti-help me-1"></i>Ayuda
        </a>
        <a href="{{ route('account-management') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-1">Preview de factura</h5>
        <p class="text-muted mb-0">
            Consumo {{ mb_strtolower($invoicePreview['frequency_label']) }}
            · {{ $invoicePreview['period_label'] }}
            · Se cierra el {{ $invoicePreview['closes_on'] }}.
            @if(!empty($invoicePreview['has_adjustments']))
                Incluye ajuste del ciclo anterior.
            @endif
            Aún no se emite.
        </p>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-muted">A facturar</span>
                        <h4 class="mb-0 mt-1">{{ $invoicePreview['formatted']['total_billed'] ?? $invoicePreview['formatted']['billed'] }}</h4>
                    </div>
                    <span class="avatar">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-receipt ti-sm"></i>
                        </span>
                    </span>
                </div>
                <small class="text-muted">Tokens, WhatsApp y mail</small>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-muted">Coste</span>
                        <h4 class="mb-0 mt-1">{{ $invoicePreview['formatted']['total_cost'] ?? $invoicePreview['formatted']['cost'] }}</h4>
                    </div>
                    <span class="avatar">
                        <span class="avatar-initial rounded bg-label-secondary">
                            <i class="ti ti-building-store ti-sm"></i>
                        </span>
                    </span>
                </div>
                <small class="text-muted">Tokens a tarifa OpenRouter</small>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-muted">Markup</span>
                        <h4 class="mb-0 mt-1 text-success">{{ $invoicePreview['formatted']['total_markup'] ?? $invoicePreview['formatted']['markup'] }}</h4>
                    </div>
                    <span class="avatar">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="ti ti-chart-arrows-vertical ti-sm"></i>
                        </span>
                    </span>
                </div>
                <small class="text-muted">×{{ $invoicePreview['formatted']['multiplier'] }} sobre tokens reales</small>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-muted">Tokens</span>
                        <h4 class="mb-0 mt-1">{{ $invoicePreview['formatted']['total_tokens'] ?? $invoicePreview['formatted']['tokens'] }}</h4>
                    </div>
                    <span class="avatar">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="ti ti-cpu ti-sm"></i>
                        </span>
                    </span>
                </div>
                <small class="text-muted">
                    Facturados
                    @if(!empty($invoicePreview['formatted']['tokens_by_module']))
                        · {{ $invoicePreview['formatted']['tokens_by_module'] }}
                    @endif
                </small>
            </div>
        </div>

        <div class="table-responsive mt-4">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th class="text-start">Ítem</th>
                        <th class="text-start">Detalle</th>
                        <th class="text-end text-nowrap">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoicePreview['invoices'] ?? [] as $invoice)
                        <tr>
                            <td colspan="3" class="text-muted pt-3">
                                {{ $invoice['title'] }} · {{ $invoice['period_label'] }}
                                <span class="fw-normal">· {{ $invoice['note'] }}</span>
                                <span class="fw-normal">· Tokens {{ $invoice['formatted_tokens'] }}</span>
                            </td>
                        </tr>
                        @foreach($invoice['lines'] as $line)
                            <tr>
                                <td @class(['ps-4 text-muted' => ($line['kind'] ?? '') === 'token_source'])>{{ $line['description'] }}</td>
                                <td @class(['text-muted' => ($line['kind'] ?? '') === 'token_source'])>{{ $line['detail'] }}</td>
                                <td class="text-end text-nowrap">{{ $line['formatted_amount'] }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td>Total factura</td>
                            <td></td>
                            <td class="text-end text-nowrap">{{ $invoice['formatted_total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th class="text-start">Total pendiente</th>
                        <th></th>
                        <th class="text-end text-nowrap">{{ $invoicePreview['formatted']['total_billed'] ?? $invoicePreview['formatted']['billed'] }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Tarifas</h5>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <p class="text-muted">Al cambiar una tarifa, la anterior se conserva para el consumo ya facturado. El consumo se factura aparte, no en la cuota del plan.</p>
        <form id="account-rates-form" action="{{ route('account.rates.update', $team->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="invoice_frequency">Frecuencia de facturación</label>
                    <select
                        id="invoice_frequency"
                        name="invoice_frequency"
                        class="form-select @error('invoice_frequency') is-invalid @enderror"
                        data-original="{{ $invoiceFrequency->value }}"
                    >
                        @foreach(\App\Enums\TeamBillingFrequency::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('invoice_frequency', $invoiceFrequency->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('invoice_frequency')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Al cambiar, se cierra el ciclo en curso ese día y el nuevo arranca ahí (miércoles a miércoles, o del 15 al 15).</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="tokens_multiplier">Multiplicador de tokens</label>
                    <input type="number" step="any" min="1" class="form-control @error('tokens_multiplier') is-invalid @enderror" id="tokens_multiplier" name="tokens_multiplier" value="{{ old('tokens_multiplier', $billingRates['tokens_multiplier'] ?? '10') }}">
                    @error('tokens_multiplier')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">El cliente ve este × tokens reales.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="whatsapp_send">Envío WhatsApp (€)</label>
                    <input type="number" step="any" min="0" class="form-control @error('whatsapp_send') is-invalid @enderror" id="whatsapp_send" name="whatsapp_send" value="{{ old('whatsapp_send', $billingRates['whatsapp_send'] ?? '0.003') }}">
                    @error('whatsapp_send')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">EUR por mensaje saliente.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="mailer_send">Envío mail (€)</label>
                    <input type="number" step="any" min="0" class="form-control @error('mailer_send') is-invalid @enderror" id="mailer_send" name="mailer_send" value="{{ old('mailer_send', $billingRates['mailer_send'] ?? '0.01') }}">
                    @error('mailer_send')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">EUR por email enviado. El plan no incluye envíos.</small>
                </div>
            </div>

            @if(isset($billingRateHistory) && $billingRateHistory->isNotEmpty())
                <div class="table-responsive mt-4">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tarifa</th>
                                <th class="text-center">Importe</th>
                                <th>Desde</th>
                                <th>Hasta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($billingRateHistory as $rate)
                                <tr>
                                    <td>{{ $rate->product->label() }}</td>
                                    <td class="text-center">{{ $rate->formattedAmount() }}@if($rate->currency) {{ $rate->currency }}@endif</td>
                                    <td>{{ $rate->effective_from?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $rate->effective_to?->format('d/m/Y H:i') ?? 'Actual' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="pt-4">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">Guardar</button>
                <a href="{{ route('account-management') }}" class="btn btn-label-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="mb-0">Meses anteriores</h5>
        </div>
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
    </div>
</div>
@endsection

@push('scripts')
    <script type="application/json" id="frequency-change-previews">{!! json_encode($frequencyChangePreviews ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    <script>
        (function () {
            const form = document.getElementById('account-rates-form');
            const frequency = document.getElementById('invoice_frequency');
            if (!form || !frequency) {
                return;
            }

            form.addEventListener('submit', function (event) {
                if (frequency.value === frequency.dataset.original) {
                    return;
                }

                event.preventDefault();

                let previews = {};
                const payload = document.getElementById('frequency-change-previews');
                try {
                    previews = JSON.parse(payload ? payload.textContent : '{}');
                } catch (error) {
                    previews = {};
                }

                const preview = previews[frequency.value];
                const escapeHtml = function (value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                };
                const invoices = preview && preview.invoices ? preview.invoices : [];
                const rows = invoices.map(function (invoice) {
                    const header = '<tr><td colspan="3" class="text-muted pt-2">' + escapeHtml(invoice.title) + ' · ' + escapeHtml(invoice.period_label) + ' · Tokens ' + escapeHtml(invoice.formatted_tokens || '') + '</td></tr>';
                    const lines = (invoice.lines || []).map(function (line) {
                        const isSource = line.kind === 'token_source';
                        const itemClass = isSource ? ' class="ps-3 text-muted"' : '';
                        const detailClass = isSource ? ' class="text-muted"' : '';
                        return '<tr><td' + itemClass + '>' + escapeHtml(line.description) + '</td><td' + detailClass + '>' + escapeHtml(line.detail) + '</td><td class="text-end text-nowrap">' + escapeHtml(line.formatted_amount || '') + '</td></tr>';
                    }).join('');
                    return header + lines;
                }).join('');
                const total = preview && preview.formatted_total ? preview.formatted_total : '0,00 EUR';
                const tokens = preview && preview.formatted_tokens ? preview.formatted_tokens : '';
                const fromLabel = preview && preview.from_frequency ? preview.from_frequency : '';
                const toLabel = preview && preview.to_frequency ? preview.to_frequency : frequency.options[frequency.selectedIndex].text;

                const html = '<p class="mb-2 text-start">Se cierra el ciclo ' + fromLabel.toLowerCase() + ' y arranca ' + toLabel.toLowerCase() + ' hoy. Aún no se emite en Stripe.</p>'
                    + (tokens ? '<p class="mb-2 text-start"><strong>Tokens totales:</strong> ' + escapeHtml(tokens) + '</p>' : '')
                    + '<div class="table-responsive text-start"><table class="table table-sm mb-0 text-start"><thead><tr><th class="text-start">Ítem</th><th class="text-start">Detalle</th><th class="text-end text-nowrap">Importe</th></tr></thead><tbody>'
                    + rows
                    + '</tbody><tfoot><tr><th class="text-start">Total</th><th></th><th class="text-end text-nowrap">' + total + '</th></tr></tfoot></table></div>';

                if (typeof Swal === 'undefined') {
                    if (window.confirm('¿Cambiar la frecuencia de facturación? El ciclo anterior se cierra como ajuste.')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: '¿Cambiar facturación?',
                    html: html,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Cambiar y guardar',
                    cancelButtonText: 'Cancelar',
                    buttonsStyling: false,
                    width: '48rem',
                    customClass: {
                        htmlContainer: 'text-start',
                        confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
                        cancelButton: 'btn btn-label-secondary waves-effect waves-light'
                    }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        })();
    </script>
@endpush
