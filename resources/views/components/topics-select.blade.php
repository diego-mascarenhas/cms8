@props(['id', 'label', 'selected' => [], 'showNull' => true, 'onchange' => null])

<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="topic_ids[]" class="form-select @error($id) is-invalid @enderror" multiple>
        @if($showNull)
            <option value="">Seleccione temáticas</option>
        @endif
        
        @php
            // Obtener todos los topics del equipo actual
            $topics = \App\Models\Topic::where('team_id', auth()->user()->currentTeam->id)
                ->orderBy('name')
                ->get();
        @endphp
        
        @foreach($topics as $topic)
            <option value="{{ $topic->id }}" {{ in_array($topic->id, old('topic_ids', $selected)) ? 'selected' : '' }}>
                {{ $topic->name }}
            </option>
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
            placeholder: 'Seleccionar temáticas',
            allowClear: true,
            closeOnSelect: false,
            width: '100%'
        });
    });
</script>
@endpush 