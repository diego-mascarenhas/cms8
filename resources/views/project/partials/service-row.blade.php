<div class="service-row mb-3" data-index="{{ $index }}">
    <div class="row g-3">
        <div class="col-md-2">
            <x-variant-language-select 
                name="services[{{ $index }}][source_language_code]" 
                id="source_language_{{ $index }}" 
                label="{{ __('Origin') }}" 
                :value="$projectFare->source_language_code ?? ''" 
                :required="true"
                placeholder="{{ __('Select origin language') }}"
            />
        </div>
        <div class="col-md-2">
            <x-variant-language-select 
                name="services[{{ $index }}][target_language_code]" 
                id="target_language_{{ $index }}" 
                label="{{ __('Target') }}" 
                :value="$projectFare->target_language_code ?? ''" 
                :required="true"
                placeholder="{{ __('Select target language') }}"
            />
        </div>
        <div class="col-md-3">
            <label class="form-label">{{ __('Service Type') }}</label>
            <select name="services[{{ $index }}][fare_id]" id="fare_{{ $index }}" class="form-select" required>
                <option value="">{{ __('Select service') }}</option>
                @php
                    $faresByType = \App\Models\Fare::with('type')
                        ->where(function($query) {
                            $query->whereNull('team_id')
                                ->orWhere('team_id', auth()->user()->currentTeam->id);
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
            <label class="form-label">{{ __('Quantity') }}</label>
            <input type="number" name="services[{{ $index }}][quantity]" class="form-control" 
                   value="{{ $projectFare->quantity ?? '' }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('Unit') }}</label>
            <select name="services[{{ $index }}][unit]" class="form-select">
                <option value="min/pag" {{ ($projectFare->unit ?? '') == 'min/pag' ? 'selected' : '' }}>min/pag</option>
                <option value="words" {{ ($projectFare->unit ?? '') == 'words' ? 'selected' : '' }}>words</option>
                <option value="pages" {{ ($projectFare->unit ?? '') == 'pages' ? 'selected' : '' }}>pages</option>
                <option value="hours" {{ ($projectFare->unit ?? '') == 'hours' ? 'selected' : '' }}>hours</option>
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