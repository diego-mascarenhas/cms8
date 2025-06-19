@props(['id', 'label', 'name' => 'fare_ids[]', 'selected' => [], 'required' => false, 'showNull' => true, 'onchange' => null, 'placeholder' => 'Seleccione servicios'])

<div>
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" class="select2 form-select @error(str_replace('[]', '', $name)) is-invalid @enderror" @if($required) required @endif @if(str_contains($name, '[]')) multiple="multiple" @endif>
        <option value="">{{ $placeholder }}</option>
        
        @php
            // Use fares from the component class if available, otherwise fetch them here
            $faresByType = $fares ?? \App\Models\Fare::with('type')
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
                    <option value="{{ $fare->id }}" {{ in_array($fare->id, old(str_replace('[]', '', $name), $selected)) ? 'selected' : '' }}>
                        {{ $fare->name }}
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    
    @error(str_replace('[]', '', $name))
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div>

@push('page-script')
<script>
    $(function() {
        const select = $('#{{ $id }}');
        if (select.length) {
            select.select2({
                dropdownParent: select.parent(),
                placeholder: "{{ $placeholder }}",
                allowClear: true,
                closeOnSelect: false,
                width: '100%'
            });
            
            @if($onchange)
            select.on('change', function() {
                {!! $onchange !!}
            });
            @endif
        }
    });
</script>
@endpush 