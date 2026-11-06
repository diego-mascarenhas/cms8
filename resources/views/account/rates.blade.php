@extends('layouts/layoutMaster')

@section('title', 'Tarifas')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
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
            Al cerrar el ciclo se crea un draft en Stripe. No se cobra hasta que se finalice.
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
                <small class="text-muted">Tokens, reuniones, transcripción, WhatsApp, mail, prospección y almacenamiento</small>
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
        @php
            $usageCurrency = \App\Services\TokenBillingRateService::displayCurrency();
        @endphp
        <p class="text-muted">Al cambiar una tarifa, la anterior se conserva para el consumo ya facturado. El consumo se factura aparte, no en la cuota del plan. Los importes de esta página están en {{ $usageCurrency }}.</p>
        <form id="account-rates-form" action="{{ route('account.rates.update', $team->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="tokens_multiplier">Multiplicador de tokens</label>
                    <input type="number" step="any" min="1" class="form-control @error('tokens_multiplier') is-invalid @enderror" id="tokens_multiplier" name="tokens_multiplier" value="{{ old('tokens_multiplier', $billingRates['tokens_multiplier'] ?? '10') }}">
                    @error('tokens_multiplier')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">El cliente ve este × tokens reales. Incluye las reuniones.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="whatsapp_send">Envío WhatsApp ({{ $usageCurrency }})</label>
                    <input type="number" step="any" min="0" class="form-control @error('whatsapp_send') is-invalid @enderror" id="whatsapp_send" name="whatsapp_send" value="{{ old('whatsapp_send', $billingRates['whatsapp_send'] ?? '0.05') }}" {{-- en €: 0.003 --}}>
                    @error('whatsapp_send')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">{{ $usageCurrency }} por mensaje saliente.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mailer_send">Envío mail ({{ $usageCurrency }})</label>
                    <input type="number" step="any" min="0" class="form-control @error('mailer_send') is-invalid @enderror" id="mailer_send" name="mailer_send" value="{{ old('mailer_send', $billingRates['mailer_send'] ?? '0.003') }}" {{-- en €: 0.002 --}}>
                    @error('mailer_send')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">{{ $usageCurrency }} por email enviado. El plan no incluye envíos.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="prospect_credit">Prospección ({{ $usageCurrency }})</label>
                    <input type="number" step="any" min="0" class="form-control @error('prospect_credit') is-invalid @enderror" id="prospect_credit" name="prospect_credit" value="{{ old('prospect_credit', $billingRates['prospect_credit'] ?? '0.15') }}">
                    @error('prospect_credit')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">{{ $usageCurrency }} por crédito consumido al importar un prospecto.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="storage_megabyte">Almacenamiento ({{ $usageCurrency }}/MB)</label>
                    <input type="number" step="any" min="0" class="form-control @error('storage_megabyte') is-invalid @enderror" id="storage_megabyte" name="storage_megabyte" value="{{ old('storage_megabyte', $billingRates['storage_megabyte'] ?? '0.02') }}">
                    @error('storage_megabyte')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">{{ $usageCurrency }} por MB ocupado (ficheros, multimedia, adjuntos y fotos de producto).</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="transcription_minute">Transcripción ({{ $usageCurrency }}/min)</label>
                    <input type="number" step="any" min="0" class="form-control @error('transcription_minute') is-invalid @enderror" id="transcription_minute" name="transcription_minute" value="{{ old('transcription_minute', $billingRates['transcription_minute'] ?? '0.037') }}">
                    @error('transcription_minute')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">{{ $usageCurrency }} por minuto de audio transcrito.</small>
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
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
