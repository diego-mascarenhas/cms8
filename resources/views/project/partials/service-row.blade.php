<div class="service-row mb-3" data-index="{{ $index }}">
    <div class="row g-3">
        <div class="col-md-2">
            <x-variant-language-select 
                name="services[{{ $index }}][source_language_code]" 
                id="source_language_{{ $index }}" 
                label="Idioma origen" 
                :value="$projectFare->source_language_code ?? ''" 
                :required="true"
                placeholder="Seleccionar idioma origen"
            />
        </div>
        <div class="col-md-2">
            <x-variant-language-select 
                name="services[{{ $index }}][target_language_code]" 
                id="target_language_{{ $index }}" 
                label="Idioma destino" 
                :value="$projectFare->target_language_code ?? ''" 
                :required="true"
                placeholder="Seleccionar idioma destino"
            />
        </div>
        <div class="col-md-3">
            <label class="form-label">Tipo de servicio</label>
            <select name="services[{{ $index }}][fare_id]" id="fare_{{ $index }}" class="form-select" required>
                <option value="">Seleccionar servicio</option>
                @php
                    $faresByType = \App\Models\Fare::with('type')
                        ->where(function($query) {
                            $query->whereNull('team_id');
                            // If user is authenticated, also include team-specific fares
                            if (auth()->check() && auth()->user()->currentTeam) {
                                $query->orWhere('team_id', auth()->user()->currentTeam->id);
                            }
                        })
                        ->orderBy('name')
                        ->get()
                        ->groupBy(function($fare) {
                            return $fare->type ? $fare->type->name : 'Sin categoría';
                        });
                @endphp
                
                @foreach($faresByType as $typeName => $fareList)
                    <optgroup label="{{ $typeName }}">
                        @foreach($fareList as $fare)
                            <option value="{{ $fare->id }}" {{ (isset($projectFare) && $projectFare->fare_id == $fare->id) ? 'selected' : '' }}>
                                {{ $fare->name }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Cantidad</label>
            <input type="number" name="services[{{ $index }}][quantity]" class="form-control" 
                   value="{{ $projectFare->quantity ?? '1' }}" min="1" step="1" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Unidad</label>
            <select name="services[{{ $index }}][unit]" id="unit_{{ $index }}" class="form-select unit-select" data-index="{{ $index }}">
                <option value="">Seleccionar unidad</option>
                @if(isset($projectFare) && $projectFare->fare && $projectFare->fare->units->count() > 0)
                    @foreach($projectFare->fare->units as $unit)
                        <option value="{{ $unit->type }}" {{ ($projectFare->unit ?? '') == $unit->type ? 'selected' : '' }}>
                            {{ $unit->type }}
                        </option>
                    @endforeach
                @endif
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">&nbsp;</label>
            <button type="button" class="btn btn-outline-danger btn-sm d-block remove-service">
                <i class="ti ti-x"></i>
            </button>
        </div>
    </div>
</div> 