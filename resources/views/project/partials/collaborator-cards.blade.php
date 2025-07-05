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
                               name="collaborator_ids[]" value="{{ $collaborator->id }}">
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