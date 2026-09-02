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
            Aún no se emite.
        </p>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-muted">A facturar</span>
                        <h4 class="mb-0 mt-1">{{ $invoicePreview['formatted']['billed'] }}</h4>
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
                        <h4 class="mb-0 mt-1">{{ $invoicePreview['formatted']['cost'] }}</h4>
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
                        <h4 class="mb-0 mt-1 text-success">{{ $invoicePreview['formatted']['markup'] }}</h4>
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
                        <h4 class="mb-0 mt-1">{{ $invoicePreview['formatted']['tokens'] }}</h4>
                    </div>
                    <span class="avatar">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="ti ti-cpu ti-sm"></i>
                        </span>
                    </span>
                </div>
                <small class="text-muted">Reales → facturados</small>
            </div>
        </div>

        <div class="table-responsive mt-4">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Línea</th>
                        <th class="text-center">Detalle</th>
                        <th class="text-center">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Tokens</td>
                        <td class="text-center">{{ $invoicePreview['formatted']['tokens'] }}</td>
                        <td class="text-center">{{ $invoicePreview['formatted']['token_billed'] }}</td>
                    </tr>
                    <tr>
                        <td>WhatsApp</td>
                        <td class="text-center">{{ number_format($invoicePreview['whatsapp_messages'], 0, ',', '.') }} envíos</td>
                        <td class="text-center">{{ $invoicePreview['formatted']['whatsapp_billed'] }}</td>
                    </tr>
                    <tr>
                        <td>Mail</td>
                        <td class="text-center">{{ number_format($invoicePreview['mailer_overage'], 0, ',', '.') }} excedente</td>
                        <td class="text-center">{{ $invoicePreview['formatted']['mailer_billed'] }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th></th>
                        <th class="text-center">{{ $invoicePreview['formatted']['billed'] }}</th>
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
        <form action="{{ route('account.rates.update', $team->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="invoice_frequency">Frecuencia de facturación</label>
                    <select id="invoice_frequency" name="invoice_frequency" class="form-select @error('invoice_frequency') is-invalid @enderror">
                        @foreach(\App\Enums\TeamBillingFrequency::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('invoice_frequency', $invoiceFrequency->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('invoice_frequency')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Periodo de la factura de consumo.</small>
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
                    <small class="text-muted">EUR por email de excedente.</small>
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
