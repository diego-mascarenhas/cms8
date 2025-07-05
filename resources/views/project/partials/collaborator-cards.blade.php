@forelse($collaborators as $index => $collaborator)
    @php
        // Build language combinations string for filtering
        $languageCombinations = [];
        foreach($collaborator->languageVariants as $variant) {
            $languageCombinations[] = $variant->source_language_code . '-' . $variant->target_language_code;
        }
        $languageString = implode(',', $languageCombinations);
        
        // Build services string for filtering (using unique fare IDs to match the selector)
        $serviceIds = [];
        foreach($collaborator->fares->unique('id') as $fare) {
            $serviceIds[] = $fare->id;
        }
        $servicesString = implode(',', $serviceIds);
        
        // Get the valoration for display
        $valorationIcon = 'ti-star-filled text-warning';
        $valorationText = 'Top';
        if ($collaborator->valoration) {
            switch($collaborator->valoration->name) {
                case 'Lista negra':
                    $valorationIcon = 'ti-x text-danger';
                    $valorationText = 'Lista negra';
                    break;
                case 'Validada':
                    $valorationIcon = 'ti-check text-success';
                    $valorationText = 'Validada';
                    break;
                case 'En espera':
                    $valorationIcon = 'ti-eye text-warning';
                    $valorationText = 'Ojo';
                    break;
                case 'Interesante':
                    $valorationIcon = 'ti-clock text-info';
                    $valorationText = 'Interesante';
                    break;
            }
        }
        
        // Get primary language combination for display
        $primaryLanguage = '';
        if ($collaborator->languageVariants->count() > 0) {
            $firstVariant = $collaborator->languageVariants->first();
            $sourceLang = $firstVariant->sourceLanguage ? $firstVariant->sourceLanguage->name : $firstVariant->source_language_code;
            $targetLang = $firstVariant->targetLanguage ? $firstVariant->targetLanguage->name : $firstVariant->target_language_code;
            $primaryLanguage = $sourceLang . ' → ' . $targetLang;
        }
    @endphp
    
    <div class="col-md-4 mb-3 collaborator-card" 
         data-languages="{{ $languageString }}" 
         data-services="{{ $servicesString }}">
        <div class="card border">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3">
                            <span class="avatar-initial rounded-circle bg-label-{{ ['primary', 'success', 'info', 'warning', 'danger'][($index % 5)] }}">{{ strtoupper(substr($collaborator->name, 0, 2)) }}</span>
                        </div>
                        <div>
                            <h6 class="mb-0">{{ $collaborator->name }}</h6>
                            <small class="text-muted">{{ $primaryLanguage }}</small>
                            <div class="d-flex align-items-center mt-1">
                                <i class="ti {{ $valorationIcon }} ti-xs me-1"></i>
                                <small class="text-muted">{{ $valorationText }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input collaborator-checkbox" type="checkbox"
                               name="collaborator_ids[]" value="{{ $collaborator->id }}"
                               data-collaborator-id="{{ $collaborator->id }}">
                    </div>
                </div>
                
                <!-- Tarifas del colaborador (inicialmente ocultas) -->
                <div class="collapse mt-3" id="fares-{{ $collaborator->id }}">
                    <div class="card bg-light">
                        <div class="card-body p-2">
                            <h6 class="card-title mb-2 text-muted">{{ __('Tarifas del colaborador') }}</h6>
                            
                            @if($collaborator->fares && $collaborator->fares->count() > 0)
                                {{-- DEBUG INFO --}}
                                <div class="alert alert-warning p-2 mb-2" style="font-size: 0.7rem;">
                                    <strong>DEBUG:</strong> Este colaborador tiene {{ $collaborator->fares->count() }} tarifas registradas
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0">
                                        <thead>
                                            <tr class="text-muted">
                                                <th style="font-size: 0.75rem;">{{ __('Servicio') }}</th>
                                                <th style="font-size: 0.75rem;">{{ __('Idiomas') }}</th>
                                                <th style="font-size: 0.75rem;" class="text-end">{{ __('Tarifa') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($collaborator->fares as $fare)
                                                @php
                                                    // Debug: Log fare data
                                                    \Log::info('Collaborator Fare Debug:', [
                                                        'collaborator_id' => $collaborator->id,
                                                        'collaborator_name' => $collaborator->name,
                                                        'fare_id' => $fare->id,
                                                        'fare_name' => $fare->name,
                                                        'pivot_data' => $fare->pivot->toArray(),
                                                        'pivot_price' => $fare->pivot->price,
                                                        'pivot_currency' => $fare->pivot->currency_code,
                                                        'pivot_source_lang' => $fare->pivot->source_language_code,
                                                        'pivot_target_lang' => $fare->pivot->target_language_code,
                                                    ]);
                                                    
                                                    // Get language names
                                                    $sourceLanguage = $fare->pivot->source_language_code ?? 'N/A';
                                                    $targetLanguage = $fare->pivot->target_language_code ?? 'N/A';
                                                    
                                                    // Get price and currency
                                                    $price = $fare->pivot->price ?? 'N/A';
                                                    $currency = $fare->pivot->currency_code ?? 'EUR';
                                                    $unit = $fare->pivot->unit_id ?? '';
                                                    
                                                    // Get unit name if available
                                                    $unitName = '';
                                                    if($unit) {
                                                        $unitModel = \App\Models\Unit::find($unit);
                                                        $unitName = $unitModel ? '/' . $unitModel->name : '';
                                                    }
                                                @endphp
                                                <tr style="font-size: 0.8rem;" class="fare-row" 
                                                    data-service-id="{{ $fare->id }}"
                                                    data-source-lang="{{ $sourceLanguage }}"
                                                    data-target-lang="{{ $targetLanguage }}">
                                                    <td>
                                                        <strong>{{ $fare->name }}</strong>
                                                        @if($fare->type)
                                                            <br><small class="text-muted">{{ $fare->type->name }}</small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($sourceLanguage !== 'N/A' && $targetLanguage !== 'N/A')
                                                            <span class="badge bg-label-info">{{ $sourceLanguage }} → {{ $targetLanguage }}</span>
                                                        @else
                                                            <span class="text-muted">{{ __('Cualquier idioma') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @if($price !== 'N/A')
                                                            <strong class="text-success">{{ number_format($price, 2) }} {{ $currency }}{{ $unitName }}</strong>
                                                        @else
                                                            <span class="text-muted">{{ __('Por consultar') }}</span>
                                                        @endif
                                                        {{-- DEBUG INFO --}}
                                                        <br><small class="text-warning">DEBUG: Price={{ $price }}, Currency={{ $currency }}, Unit={{ $unit }}</small>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">{{ __('No hay tarifas registradas') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="col-12">
        <div class="alert alert-info">
            <i class="ti ti-info-circle me-2"></i>
            @if(request()->has('source_language') || request()->has('target_language') || request()->has('servicio'))
                No hay colaboradores disponibles con los filtros seleccionados.
            @else
                Selecciona una combinación de idiomas o un servicio para ver colaboradores disponibles.
            @endif
        </div>
    </div>
@endforelse 