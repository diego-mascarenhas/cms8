<div class="form-group">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $id }}" class="form-select @error($id) is-invalid @enderror" data-allow-clear="false">
        <option value="">Seleccione una opción</option>
        @foreach ($options as $option)
            <option value="{{ $option['id'] }}" @if (old($id, $value) == $option['id']) selected @endif>
                {{ $option['name'] }}
            </option>
        @endforeach
    </select>
    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#{{ $id }}').select2({
        minimumResultsForSearch: Infinity,
        allowClear: false,
        placeholder: 'Seleccione una opción'
    });
});
</script>
@endpush