@props(['id', 'label', 'selected' => [], 'showNull' => true, 'onchange' => null])

<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="software_ids[]" class="form-select @error($id) is-invalid @enderror" multiple>
        @if($showNull)
            <option value="">Seleccione software</option>
        @endif
        
        @php
            // Obtener todos los software agrupados por tipo
            $softwareByType = \App\Models\Software::with('type')
                ->where(function($query) {
                    $query->whereNull('team_id')
                        ->orWhere('team_id', auth()->user()->currentTeam->id);
                })
                ->orderBy('name')
                ->get()
                ->groupBy(function($software) {
                    return $software->type ? $software->type->name : 'Sin categoría';
                });
        @endphp
        
        @foreach($softwareByType as $typeName => $softwareList)
            <optgroup label="{{ $typeName }}">
                @foreach($softwareList as $software)
                    <option value="{{ $software->id }}" {{ in_array($software->id, old('software_ids', $selected)) ? 'selected' : '' }}>
                        {{ $software->name }}
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
            placeholder: 'Seleccionar software',
            allowClear: true,
            closeOnSelect: false,
            width: '100%'
        });
    });
</script>
@endpush 