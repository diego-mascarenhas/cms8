@extends('layouts/layoutMaster')

@section('title', 'Tarifas de ' . $collaborator->name)

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flag-icons/flag-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .btn-group .btn {
            transition: opacity 0.2s ease-in-out, background-color 0.2s ease-in-out;
        }
        
        .btn-group .btn.opacity-50 {
            opacity: 0.5;
        }
        
        .btn-group .btn:not(.opacity-50) {
            opacity: 1;
        }
        
        /* Ensure active buttons have proper styling */
        .btn-group .btn.active {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: white !important;
            opacity: 1 !important;
        }
        
        /* Same rates mode - all buttons should appear active */
        .btn-group .btn.same-rates-mode {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            color: white;
            opacity: 1;
        }
    </style>
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
                                    <option value="{{ $currency->code }}" {{ $currency->code === $currentCurrency ? 'selected' : '' }}>
                                        {{ $currency->code }} - {{ $currency->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Selección de idiomas -->
                    <div class="mb-3">
                        <h5 class="mb-3">Combinaciones de idiomas</h5>
                        
                        @if($collaborator->languageVariants && $collaborator->languageVariants->count() > 0)
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach($collaborator->languageVariants as $index => $variant)
                                    @php
                                        $sourceFlag = strtolower($variant->sourceLanguage ? $variant->sourceLanguage->country_code ?? '' : '');
                                        if (empty($sourceFlag) && $variant->sourceLanguage) {
                                            $sourceFlag = strtolower($variant->source_language_code);
                                        }
                                        
                                        $targetFlag = strtolower($variant->targetLanguage ? $variant->targetLanguage->country_code ?? '' : '');
                                        if (empty($targetFlag) && $variant->targetLanguage) {
                                            $targetFlag = strtolower($variant->target_language_code);
                                        }
                                        
                                        // Check if this combination should be active based on URL params or default
                                        $urlLanguagePair = request('language_pair');
                                        $isActive = false;
                                        
                                        if ($urlLanguagePair) {
                                            [$urlSource, $urlTarget] = explode('|', $urlLanguagePair);
                                            $isActive = ($variant->source_language_code === $urlSource && $variant->target_language_code === $urlTarget);
                                        } else {
                                            $isActive = $index === 0; // Primera combinación activa por defecto si no hay URL param
                                        }
                                    @endphp
                                    
                                    <div class="btn-group me-2">
                                        <button type="button" class="btn btn-outline-primary {{ $isActive ? 'active' : '' }} px-3"
                                                data-source="{{ $variant->source_language_code }}" 
                                                data-target="{{ $variant->target_language_code }}">
                                            @if(!empty($sourceFlag))
                                                <span class="fi fi-{{ $sourceFlag }} me-1"></span>
                                            @endif
                                            {{ $variant->sourceLanguage ? $variant->sourceLanguage->name : $variant->source_language_code }}
                                            <span class="mx-1"><i class="ti ti-arrow-right text-muted"></i></span>
                                            @if(!empty($targetFlag))
                                                <span class="fi fi-{{ $targetFlag }} me-1"></span>
                                            @endif
                                            {{ $variant->targetLanguage ? $variant->targetLanguage->name : $variant->target_language_code }}
                                            @if($variant->is_certified)
                                                <span class="badge bg-label-success ms-1">Nativo</span>
                                            @endif
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="sameRates" name="same_rates" 
                                       {{ request('same_rates') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sameRates">
                                    Usar las mismas tarifas para todas las combinaciones
                                </label>
                            </div>
                            
                            <input type="hidden" name="current_language_pair" id="current_language_pair" 
                                   value="{{ $currentLanguagePair ?? '' }}">
                        @else
                            <div class="alert alert-warning">
                                <div class="d-flex align-items-center">
                                    <i class="ti ti-alert-triangle me-2"></i>
                                    <span>No hay combinaciones de idiomas registradas para este colaborador.</span>
                                </div>
                                <a href="{{ route('collaborator.edit', ['id' => $collaborator->id ?? 0]) }}" class="btn btn-sm btn-warning mt-2">
                                    <i class="ti ti-plus me-1"></i>Añadir idiomas
                                </a>
                            </div>
                        @endif
                    </div>

                    <hr>

                    <!-- Dynamic Fares by Type -->
                    @if($allFares && $allFares->count() > 0 && $collaborator->languageVariants && $collaborator->languageVariants->count() > 0)
                        @php
                            $defaultLanguagePair = $collaborator->languageVariants->first();
                            $defaultSourceCode = $defaultLanguagePair->source_language_code;
                            $defaultTargetCode = $defaultLanguagePair->target_language_code;
                        @endphp
                        
                        @foreach($allFares as $typeName => $fares)
                            <h5 class="mt-4 mb-3">{{ $typeName ?: 'Sin categoría' }}</h5>
                            
                            @php
                                $fareChunks = $fares->chunk(2);
                            @endphp
                            
                            @foreach($fareChunks as $fareChunk)
                                <div class="row mb-3">
                                    @foreach($fareChunk as $fare)
                                        @php
                                            // Use specific rates data if available, otherwise default to empty
                                            $currentPrice = $currentRatesData[$fare->id]['price'] ?? 0;
                                            $currentUnitId = $currentRatesData[$fare->id]['unit_id'] ?? ($fare->units->count() > 0 ? $fare->units->first()->id : null);
                                        @endphp
                                        <div class="col-md-6">
                                            <label class="form-label">{{ $fare->name }}</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text currency-symbol">€</span>
                                                <input type="number" 
                                                       class="form-control fare-input" 
                                                       data-fare-id="{{ $fare->id }}"
                                                       name="rates[{{ $fare->id }}]" 
                                                       value="{{ number_format($currentPrice, 2, '.', '') }}" 
                                                       step="0.01" 
                                                       min="0"
                                                       placeholder="0.00">
                                                
                                                @if($fare->units && $fare->units->count() > 1)
                                                    <select class="form-select unit-select" 
                                                            data-fare-id="{{ $fare->id }}"
                                                            name="units[{{ $fare->id }}]" 
                                                            style="max-width: 120px;" 
                                                            required>
                                                        @foreach($fare->units as $unit)
                                                            <option value="{{ $unit->id }}" 
                                                                {{ $currentUnitId == $unit->id ? 'selected' : '' }}>
                                                                /{{ $unit->type }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @elseif($fare->units && $fare->units->count() == 1)
                                                    <span class="input-group-text">/{{ $fare->units->first()->type }}</span>
                                                    <input type="hidden" name="units[{{ $fare->id }}]" value="{{ $fare->units->first()->id }}">
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
                                <span>
                                    @if(!$collaborator->languageVariants || $collaborator->languageVariants->count() == 0)
                                        No hay combinaciones de idiomas registradas para este colaborador.
                                    @else
                                        No hay tarifas disponibles para configurar.
                                    @endif
                                </span>
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
        
        // Store rates data for each language combination
        let ratesData = {};
        
        // Function to save current form state
        function saveCurrentRatesState() {
            const currentPair = $('#current_language_pair').val();
            if (!currentPair) return;
            
            const [sourceCode, targetCode] = currentPair.split('|');
            const key = `${sourceCode}|${targetCode}`;
            
            ratesData[key] = {
                currency: $('select[name="currency"]').val(),
                rates: {},
                units: {}
            };
            
            $('.fare-input').each(function() {
                const fareId = $(this).data('fare-id');
                ratesData[key].rates[fareId] = $(this).val();
                
                const unitSelect = $(`.unit-select[data-fare-id="${fareId}"]`);
                if (unitSelect.length) {
                    ratesData[key].units[fareId] = unitSelect.val();
                }
                
                const unitHidden = $(`input[type="hidden"][name="units[${fareId}]"]`);
                if (unitHidden.length) {
                    ratesData[key].units[fareId] = unitHidden.val();
                }
            });
        }
        
        // Function to restore form state
        function restoreRatesState(sourceCode, targetCode) {
            const key = `${sourceCode}|${targetCode}`;
            
            if (ratesData[key]) {
                // Restore from stored data
                $('select[name="currency"]').val(ratesData[key].currency);
                // Don't trigger change to avoid loop
                updateCurrencySymbols(ratesData[key].currency);
                
                $('.fare-input').each(function() {
                    const fareId = $(this).data('fare-id');
                    const rate = ratesData[key].rates[fareId] || '0.00';
                    $(this).val(rate);
                    
                    const unitSelect = $(`.unit-select[data-fare-id="${fareId}"]`);
                    if (unitSelect.length && ratesData[key].units[fareId]) {
                        unitSelect.val(ratesData[key].units[fareId]);
                    }
                });
            } else {
                // Load from server
                loadRatesFromServer(sourceCode, targetCode);
            }
        }
        
        // Function to update currency symbols without triggering events
        function updateCurrencySymbols(currency) {
            const symbol = currencySymbols[currency] || '€';
            $('.currency-symbol').text(symbol);
        }
        
        // Function to load rates from server for specific language combination
        function loadRatesFromServer(sourceCode, targetCode) {
            const collaboratorId = {{ $collaborator->id }};
            console.log('Loading rates from server for:', sourceCode, '->', targetCode);
            
            $.ajax({
                url: `{{ route('collaborator.rates.get', ':id') }}`.replace(':id', collaboratorId),
                method: 'GET',
                data: {
                    source_language: sourceCode,
                    target_language: targetCode
                },
                success: function(response) {
                    console.log('Server response:', response);
                    
                    if (response.rates && response.rates.length > 0) {
                        const key = `${sourceCode}|${targetCode}`;
                        ratesData[key] = {
                            currency: response.rates[0].currency_code || 'EUR',
                            rates: {},
                            units: {}
                        };
                        
                        // Process the rates from server
                        response.rates.forEach(function(rate) {
                            ratesData[key].rates[rate.fare_id] = rate.price;
                            if (rate.unit_id) {
                                ratesData[key].units[rate.fare_id] = rate.unit_id;
                            }
                        });
                        
                        console.log('Processed rates data:', ratesData[key]);
                        
                        // Update the form with loaded data
                        $('select[name="currency"]').val(ratesData[key].currency).trigger('change');
                        
                        $('.fare-input').each(function() {
                            const fareId = $(this).data('fare-id');
                            const rate = ratesData[key].rates[fareId] || '0.00';
                            $(this).val(rate);
                            
                            const unitSelect = $(`.unit-select[data-fare-id="${fareId}"]`);
                            if (unitSelect.length && ratesData[key].units[fareId]) {
                                unitSelect.val(ratesData[key].units[fareId]);
                            }
                        });
                        
                        console.log('Form updated with server data');
                    } else {
                        console.log('No rates found for this combination, clearing form');
                        // No rates found, clear the form
                        $('.fare-input').val('0.00');
                        $('.unit-select').each(function() {
                            $(this).val($(this).find('option:first').val());
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading rates:', error, xhr.responseText);
                    // Clear the form on error
                    $('.fare-input').val('0.00');
                }
            });
        }
        
        // Language combination button click handler
        $('[data-source][data-target]').on('click', function() {
            const sourceCode = $(this).data('source');
            const targetCode = $(this).data('target');
            const isSameRates = $('#sameRates').is(':checked');
            
            console.log('Language combination clicked:', sourceCode, '->', targetCode, 'Same rates:', isSameRates);
            
            // If same rates mode, don't allow switching between combinations
            if (isSameRates) {
                console.log('Same rates mode - ignoring click');
                return;
            }
            
            // Save current state before switching
            saveCurrentRatesState();
            console.log('Saved current state before switching');
            
            // Update active state - act like radio buttons
            $('[data-source][data-target]').removeClass('active').addClass('opacity-50');
            $(this).removeClass('opacity-50').addClass('active');
            
            if (sourceCode && targetCode) {
                // Update hidden field
                $('#current_language_pair').val(sourceCode + '|' + targetCode);
                
                // Load rates for this combination
                console.log('Loading rates for combination:', sourceCode, '->', targetCode);
                restoreRatesState(sourceCode, targetCode);
            }
        });
        
        // Same rates checkbox handler
        $('#sameRates').on('change', function() {
            const isChecked = $(this).is(':checked');
            console.log('Checkbox changed. Checked:', isChecked);
            
            if (isChecked) {
                // Same rates for all combinations - show all as selected
                $('[data-source][data-target]').removeClass('opacity-50').addClass('active');
                
                // Save current state before switching to same rates mode
                saveCurrentRatesState();
                
                console.log('Same rates mode enabled - all combinations selected');
            } else {
                // Different rates for each combination - show only current active one
                $('[data-source][data-target]').removeClass('active').addClass('opacity-50');
                
                // Activate only the current language pair
                const currentPair = $('#current_language_pair').val();
                if (currentPair) {
                    const [sourceCode, targetCode] = currentPair.split('|');
                    const activeBtn = $(`[data-source="${sourceCode}"][data-target="${targetCode}"]`);
                    activeBtn.removeClass('opacity-50').addClass('active');
                }
                
                console.log('Different rates per combination mode enabled');
            }
        });
        
        // Handle currency change - Update ALL records for this collaborator
        $('select[name="currency"]').on('change', function() {
            const selectedCurrency = $(this).val();
            const symbol = currencySymbols[selectedCurrency] || '€';
            
            // Update all currency symbols in the form
            $('.currency-symbol').text(symbol);
            
            // Update stored data for ALL language combinations
            for (let key in ratesData) {
                if (ratesData[key]) {
                    ratesData[key].currency = selectedCurrency;
                }
            }
        });
        
        // Initialize with current currency
        $('select[name="currency"]').trigger('change');
        
        // Set initial language combination
        const activeBtn = $('[data-source][data-target].active');
        if (activeBtn.length) {
            const sourceCode = activeBtn.data('source');
            const targetCode = activeBtn.data('target');
            if (sourceCode && targetCode) {
                $('#current_language_pair').val(sourceCode + '|' + targetCode);
            }
        } else {
            // If no active button, activate the first one
            const firstBtn = $('[data-source][data-target]').first();
            if (firstBtn.length) {
                firstBtn.addClass('active');
                const sourceCode = firstBtn.data('source');
                const targetCode = firstBtn.data('target');
                if (sourceCode && targetCode) {
                    $('#current_language_pair').val(sourceCode + '|' + targetCode);
                }
            }
        }
        
        // Get parameters from URL for initial state
        const urlParams = new URLSearchParams(window.location.search);
        const urlCurrency = urlParams.get('currency');
        const urlLanguagePair = urlParams.get('language_pair');
        const urlSameRates = urlParams.get('same_rates');
        
        // Set currency from URL or existing rate
        if (urlCurrency) {
            $('select[name="currency"]').val(urlCurrency);
        } else {
            const firstRateWithCurrency = @json($collaborator->fares->first());
            if (firstRateWithCurrency && firstRateWithCurrency.pivot && firstRateWithCurrency.pivot.currency_code) {
                $('select[name="currency"]').val(firstRateWithCurrency.pivot.currency_code);
            }
        }
        
        // Set checkbox state from URL
        if (urlSameRates === '1') {
            $('#sameRates').prop('checked', true);
        }
        
        // Set active language combination from URL if provided
        if (urlLanguagePair) {
            const [urlSourceCode, urlTargetCode] = urlLanguagePair.split('|');
            if (urlSourceCode && urlTargetCode) {
                // Remove active from all and set to URL specified combination
                $('[data-source][data-target]').removeClass('active');
                const targetBtn = $(`[data-source="${urlSourceCode}"][data-target="${urlTargetCode}"]`);
                if (targetBtn.length) {
                    targetBtn.addClass('active');
                    $('#current_language_pair').val(urlLanguagePair);
                }
            }
        }
        
        // Initialize currency symbols
        const currentCurrency = $('select[name="currency"]').val();
        updateCurrencySymbols(currentCurrency);
        
        // Initialize checkbox state (this will trigger proper visual behavior)
        $('#sameRates').trigger('change');
        
        // Clean URL parameters immediately after setting initial state
        if (urlCurrency || urlLanguagePair || urlSameRates) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({path: cleanUrl}, '', cleanUrl);
            console.log('URL parameters cleaned after setting initial state');
        }
        
                                // Only load rates from server if we don't have initial data from PHP
        const hasInitialData = @json(!empty($currentRatesData));
        
        if (!hasInitialData) {
            // Load initial rates AFTER everything is set up (only in different rates mode)
            setTimeout(() => {
                const currentActiveBtn = $('[data-source][data-target].active');
                if (currentActiveBtn.length && !$('#sameRates').is(':checked')) {
                    const sourceCode = currentActiveBtn.data('source');
                    const targetCode = currentActiveBtn.data('target');
                    if (sourceCode && targetCode) {
                        loadRatesFromServer(sourceCode, targetCode);
                    }
                }
            }, 50); // Small delay to avoid flicker during initialization
        } else {
            console.log('Using initial data from PHP, skipping server load');
        }
        
        // Form submission handler
        $('#rates-form').on('submit', function(e) {
            const isSameRates = $('#sameRates').is(':checked');
            
            // Save current state before submitting (always do this)
            saveCurrentRatesState();
            
            let hasRates = false;
            
            // Check if at least one rate is filled
            $('.fare-input').each(function() {
                if ($(this).val() && parseFloat($(this).val()) > 0) {
                    hasRates = true;
                    return false;
                }
            });
            
            if (!hasRates) {
                e.preventDefault();
                alert('Debe especificar al menos una tarifa.');
                return false;
            }
            
            console.log('Form submission - Same rates mode:', isSameRates);
            console.log('Stored rates data:', ratesData);
            
            // If using different rates per combination, add stored data
            if (!isSameRates) {
                // Create hidden inputs for each language combination's rates
                for (let langPair in ratesData) {
                    const [sourceCode, targetCode] = langPair.split('|');
                    const data = ratesData[langPair];
                    
                    if (!data || !data.rates) continue;
                    
                    // Add hidden inputs for this language pair
                    for (let fareId in data.rates) {
                        if (data.rates[fareId] && parseFloat(data.rates[fareId]) > 0) {
                            $('<input>').attr({
                                type: 'hidden',
                                name: `language_rates[${sourceCode}|${targetCode}][rates][${fareId}]`,
                                value: data.rates[fareId]
                            }).appendTo(this);
                            
                            if (data.units && data.units[fareId]) {
                                $('<input>').attr({
                                    type: 'hidden',
                                    name: `language_rates[${sourceCode}|${targetCode}][units][${fareId}]`,
                                    value: data.units[fareId]
                                }).appendTo(this);
                            }
                            
                            $('<input>').attr({
                                type: 'hidden',
                                name: `language_rates[${sourceCode}|${targetCode}][currency]`,
                                value: data.currency || $('select[name="currency"]').val()
                            }).appendTo(this);
                        }
                    }
                }
                
                console.log('Added hidden inputs for different rates per language');
            } else {
                console.log('Same rates mode - using visible form data only');
            }
        });
    });
</script>
@endpush 