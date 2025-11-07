@extends('layouts/layoutMaster')

@section('title', __('Invoices'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('Invoices') }}</h4>
        <p class="text-muted">{{ __('Manage your invoices and billing') }}</p>
    </div>
    @can('invoice.create')
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-label-primary" data-bs-toggle="modal" data-bs-target="#calculatorModal">
            <i class="ti ti-calculator me-1"></i> {{ __('Calculator') }}
        </button>
        <a href="{{ route('invoice.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> {{ __('New Invoice') }}
        </a>
    </div>
    @endcan
</div>

<!-- Exchange Rates Cards -->
<div class="row g-4 mb-4">
    <!-- USD to ARS -->
    <div class="col-sm-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left w-100">
                        <span class="text-muted d-block mb-1">USD → ARS</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">
                                @if($exchangeRates['USD_ARS'])
                                    ${{ number_format($exchangeRates['USD_ARS'], 2) }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </h3>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">1 ARS = {{ $exchangeRates['USD_ARS'] ? number_format(1 / $exchangeRates['USD_ARS'], 6) : 'N/A' }} USD</small>
                            @if($lastUpdate)
                            <small class="text-muted ms-2 text-nowrap">
                                <i class="ti ti-clock ti-xs me-1"></i>{{ $lastUpdate->fetched_at->diffForHumans() }}
                            </small>
                            @endif
                        </div>
                    </div>
                    <span class="badge bg-label-success rounded p-2">
                        <i class="ti ti-currency-dollar ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- USD to EUR -->
    <div class="col-sm-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left w-100">
                        <span class="text-muted d-block mb-1">USD → EUR</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">
                                @if($exchangeRates['USD_EUR'])
                                    €{{ number_format($exchangeRates['USD_EUR'], 4) }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </h3>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">1 EUR = {{ $exchangeRates['USD_EUR'] ? number_format(1 / $exchangeRates['USD_EUR'], 4) : 'N/A' }} USD</small>
                            @if($lastUpdate)
                            <small class="text-muted ms-2 text-nowrap">
                                <i class="ti ti-clock ti-xs me-1"></i>{{ $lastUpdate->fetched_at->diffForHumans() }}
                            </small>
                            @endif
                        </div>
                    </div>
                    <span class="badge bg-label-primary rounded p-2">
                        <i class="ti ti-currency-euro ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ARS to EUR -->
    <div class="col-sm-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left w-100">
                        <span class="text-muted d-block mb-1">ARS → EUR</span>
                        <div class="d-flex align-items-center my-2">
                            <h3 class="mb-0 me-2">
                                @if($exchangeRates['ARS_EUR'])
                                    ${{ number_format(1 / $exchangeRates['ARS_EUR'], 2) }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </h3>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">1 ARS = {{ $exchangeRates['ARS_EUR'] ? number_format($exchangeRates['ARS_EUR'], 6) : 'N/A' }} EUR</small>
                            @if($lastUpdate)
                            <small class="text-muted ms-2 text-nowrap">
                                <i class="ti ti-clock ti-xs me-1"></i>{{ $lastUpdate->fetched_at->diffForHumans() }}
                            </small>
                            @endif
                        </div>
                    </div>
                    <span class="badge bg-label-info rounded p-2">
                        <i class="ti ti-currency ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        {{ $dataTable->table() }}
    </div>
</div>

<!-- Calculator Modal -->
<div class="modal fade" id="calculatorModal" tabindex="-1" aria-labelledby="calculatorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calculatorModalLabel">
                    <i class="ti ti-calculator me-2"></i>{{ __('Currency Calculator') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="ti ti-info-circle me-2"></i>
                    <div>
                        <strong>{{ __('Note:') }}</strong> {{ __('Put this value in the notes of the invoice to be issued from Stripe') }}
                    </div>
                </div>

                <form id="calculatorForm">
                    <div class="mb-4">
                        <label for="arsAmount" class="form-label">{{ __('Amount in ARS') }}</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   class="form-control" 
                                   id="arsAmount" 
                                   placeholder="0.00" 
                                   step="0.01"
                                   min="0">
                            <span class="input-group-text">ARS</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="card bg-label-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-muted">{{ __('Approximate value according to estimated exchange rate as of today') }}</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <h4 class="mb-0">
                                        <span id="arsDisplay">0,00</span> ARS
                                    </h4>
                                    <div class="text-end">
                                        <i class="ti ti-arrow-right ti-sm text-muted"></i>
                                    </div>
                                    <h4 class="mb-0 text-success">
                                        <span id="eurDisplay">0,00</span> EUR
                                    </h4>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="ti ti-info-circle ti-xs me-1"></i>
                                    {{ __('Exchange rate:') }} 1 ARS = <span id="exchangeRateDisplay">{{ $exchangeRates['ARS_EUR'] ? number_format($exchangeRates['ARS_EUR'], 6) : 'N/A' }}</span> EUR
                                </small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
                    <i class="ti ti-x me-1"></i>{{ __('Close') }}
                </button>
                <button type="button" class="btn btn-primary" id="copyButton">
                    <i class="ti ti-copy me-1"></i>{{ __('Copy Text') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const arsInput = document.getElementById('arsAmount');
            const arsDisplay = document.getElementById('arsDisplay');
            const eurDisplay = document.getElementById('eurDisplay');
            const copyButton = document.getElementById('copyButton');
            const exchangeRate = {{ $exchangeRates['ARS_EUR'] ?? 0 }};

            // Function to format number with Spanish locale (comma for decimals, dot for thousands)
            function formatNumber(num, decimals = 2) {
                return new Intl.NumberFormat('es-ES', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                }).format(num);
            }

            // Calculate conversion on input change
            arsInput.addEventListener('input', function() {
                const arsAmount = parseFloat(this.value) || 0;
                const eurAmount = arsAmount * exchangeRate;

                arsDisplay.textContent = formatNumber(arsAmount);
                eurDisplay.textContent = formatNumber(eurAmount);
            });

            // Copy text to clipboard
            copyButton.addEventListener('click', function() {
                const arsAmount = parseFloat(arsInput.value) || 0;
                const eurAmount = arsAmount * exchangeRate;
                
                const textToCopy = `Valor aproximado según tipo de cambio estimado a la fecha: ${formatNumber(arsAmount)} ARS equivalentes a ${formatNumber(eurAmount)} EUR.`;
                
                // Copy to clipboard
                navigator.clipboard.writeText(textToCopy).then(function() {
                    // Change button to success state
                    const originalHtml = copyButton.innerHTML;
                    copyButton.innerHTML = '<i class="ti ti-check me-1"></i>{{ __("Copied!") }}';
                    copyButton.classList.remove('btn-primary');
                    copyButton.classList.add('btn-success');
                    
                    // Reset button after 2 seconds
                    setTimeout(function() {
                        copyButton.innerHTML = originalHtml;
                        copyButton.classList.remove('btn-success');
                        copyButton.classList.add('btn-primary');
                    }, 2000);
                }).catch(function(err) {
                    console.error('Error copying text: ', err);
                    alert('{{ __("Error copying text") }}');
                });
            });

            // Reset form when modal is closed
            document.getElementById('calculatorModal').addEventListener('hidden.bs.modal', function() {
                arsInput.value = '';
                arsDisplay.textContent = '0,00';
                eurDisplay.textContent = '0,00';
            });
        });
    </script>
@endpush


