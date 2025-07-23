@props(['id', 'label', 'selected' => [], 'showNull' => true, 'onchange' => null])

<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="software_ids[]" class="form-select @error($id) is-invalid @enderror" multiple>
        @if($showNull)
            <option value="">Seleccione software</option>
        @endif

        @php
            // Obtener todos los software agrupados por categoría
            $softwareByCategory = \App\Models\Software::with('category')
                ->where(function($query) {
                    $query->whereNull('team_id')
                        ->orWhere('team_id', auth()->user()->currentTeam->id);
                })
                ->orderBy('name')
                ->get()
                ->groupBy(function($software) {
                    return $software->category ? $software->category->name : 'Sin categoría';
                });
        @endphp

        @foreach($softwareByCategory as $categoryName => $softwareList)
            <optgroup label="{{ $categoryName }}">
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
