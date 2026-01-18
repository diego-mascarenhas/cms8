@props(['name' => 'language', 'id' => null, 'value' => null, 'label' => 'Idioma', 'required' => false])

<div>
    <label for="{{ $id ?? $name }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id ?? $name }}" name="{{ $name }}" class="form-select select2 @error($name) is-invalid @enderror" {{ $required ? 'required' : '' }}>
        <option value="">Seleccione un idioma</option>
        @foreach($languages as $language)
            <option value="{{ $language->code }}" data-flag="{{ $language->flag }}" {{ old($name, $value) == $language->code ? 'selected' : '' }}>
                {{ $language->name }}
            </option>
        @endforeach
    </select>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#{{ $id ?? $name }}').select2({
        placeholder: 'Seleccione un idioma',
        allowClear: true,
        templateResult: function(data) {
            if (!data.id) {
                return data.text;
            }
            var $result = $('<span><span class="fi fi-' + $(data.element).data('flag') + ' me-2"></span>' + data.text + '</span>');
            return $result;
        },
        templateSelection: function(data) {
            if (!data.id) {
                return data.text;
            }
            var $selection = $('<span><span class="fi fi-' + $(data.element).data('flag') + ' me-2"></span>' + data.text + '</span>');
            return $selection;
        }
    });
});
</script>
@endpush
