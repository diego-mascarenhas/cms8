@extends('layouts/layoutMaster')

@section('title', 'Tarifas de ' . $collaborator->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flag-icons/flag-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <!-- Collaborator Sidebar -->
    @include('collaborator.partials.sidebar')
    <!--/ Collaborator Sidebar -->

    <!-- Rates Content -->
    <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Tabs -->
        @include('collaborator.partials.tabs')
        
        <div class="card mb-4">
            <div class="card-body">
                <form id="rates-form" method="POST" action="{{ route('collaborator.rates.save', $collaborator->id) }}">
                    @csrf
                    <!-- Selección de divisa -->
                    <div class="mb-3 row">
                        <label class="col-form-label col-md-2">Divisa *</label>
                        <div class="col-md-4">
                            <select class="form-select" name="currency" required>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->code }}" {{ $currency->code === 'EUR' ? 'selected' : '' }}>
                                        {{ $currency->code }} - {{ $currency->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <hr>

                    <!-- Dynamic Fares by Type -->
                    @if($allFares && $allFares->count() > 0)
                        @foreach($allFares as $typeName => $fares)
                            <h5 class="mt-4 mb-3">{{ $typeName ?: 'Sin categoría' }}</h5>
                            
                            @php
                                $fareChunks = $fares->chunk(2);
                            @endphp
                            
                            @foreach($fareChunks as $fareChunk)
                                <div class="row mb-3">
                                    @foreach($fareChunk as $fare)
                                        @php
                                            // Get current collaborator's rate for this fare
                                            $currentRate = $collaborator->fares->where('id', $fare->id)->first();
                                            $currentPrice = $currentRate ? $currentRate->pivot->price : 0;
                                            $currentUnitId = $currentRate ? $currentRate->pivot->unit_id : null;
                                        @endphp
                                        <div class="col-md-6">
                                            <label class="form-label">{{ $fare->name }}</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text currency-symbol">€</span>
                                                <input type="number" 
                                                       class="form-control" 
                                                       name="rates[{{ $fare->id }}]" 
                                                       value="{{ number_format($currentPrice, 2, '.', '') }}" 
                                                       step="0.01" 
                                                       min="0"
                                                       placeholder="0.00">
                                                
                                                @if($fare->units && $fare->units->count() > 0)
                                                    <select class="form-select" name="units[{{ $fare->id }}]" style="max-width: 120px;">
                                                        <option value="">Unidad</option>
                                                        @foreach($fare->units as $unit)
                                                            <option value="{{ $unit->id }}" 
                                                                {{ $currentUnitId == $unit->id ? 'selected' : '' }}>
                                                                /{{ $unit->type }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="input-group-text">/unidad</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        @endforeach
                    @else
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <i class="ti ti-info-circle me-2"></i>
                                <span>No hay tarifas disponibles para configurar.</span>
                            </div>
                        </div>
                    @endif

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary">Guardar tarifas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include Valoration Modal -->
@include('collaborator.partials.valoration-modal')

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Currency symbol mapping
        const currencySymbols = {
            'EUR': '€',
            'USD': '$',
            'GBP': '£',
            'ARS': '$'
        };
        
        // Handle currency change
        $('select[name="currency"]').on('change', function() {
            const selectedCurrency = $(this).val();
            const symbol = currencySymbols[selectedCurrency] || '€';
            
            // Update all currency symbols in the form
            $('.currency-symbol').text(symbol);
        });
        
        // Initialize with current currency
        $('select[name="currency"]').trigger('change');
        
        // Form submission validation
        $('#rates-form').on('submit', function(e) {
            let hasRates = false;
            
            // Check if at least one rate is filled
            $('input[name^="rates["]').each(function() {
                if ($(this).val() && parseFloat($(this).val()) > 0) {
                    hasRates = true;
                    return false; // break the loop
                }
            });
            
            if (!hasRates) {
                e.preventDefault();
                alert('Debe especificar al menos una tarifa.');
                return false;
            }
        });
    });
</script>
@endpush 