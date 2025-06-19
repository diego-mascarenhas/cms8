@props(['id', 'label', 'selected' => [], 'showNull' => true, 'onchange' => null])

<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="fare_ids[]" class="form-select @error($id) is-invalid @enderror" multiple>
        @if($showNull)
            <option value="">Seleccione servicios</option>
        @endif
        
        @php
            // Obtener todos los servicios agrupados por tipo
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
                    <option value="{{ $fare->id }}" {{ in_array($fare->id, old('fare_ids', $selected)) ? 'selected' : '' }}>
                        {{ $fare->name }}
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    
    @error($id)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        $('#{{ $id }}').select2({
            placeholder: 'Seleccionar servicios',
            allowClear: true,
            closeOnSelect: false,
            width: '100%'
        });
        
        @if($onchange)
            $('#{{ $id }}').on('change', function() {
                {!! $onchange !!}
            });
        @endif
    });
</script>
@endpush 