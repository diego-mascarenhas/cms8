@props(['name' => 'country', 'id' => null, 'value' => null, 'label' => 'País', 'valueKey' => 'id'])

@php
    $fieldId = $id ?? $name;
    $selectedValue = old($name, $value);

    if ($valueKey === 'code' && is_string($selectedValue)) {
        $selectedValue = strtoupper($selectedValue);
    }
@endphp

<div>
    <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
    <select id="{{ $fieldId }}" name="{{ $name }}" class="form-select select2 @error($name) is-invalid @enderror" data-placeholder="Seleccione un país">
        <option value="">Seleccione un país</option>
        @foreach($countries as $country)
            @php
                $optionValue = $valueKey === 'code' ? strtoupper($country->code) : $country->id;
            @endphp
            <option value="{{ $optionValue }}" data-flag="{{ strtolower($country->code) }}" @selected((string) $selectedValue === (string) $optionValue)>
                {{ $country->name }}
            </option>
        @endforeach
    </select>
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var $el = $('#{{ $fieldId }}');
    if (! $el.length) {
        return;
    }

    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }

    $el.select2({
        placeholder: 'Seleccione un país',
        allowClear: true,
        width: '100%',
        dropdownParent: $el.closest('.modal, .offcanvas, body').first(),
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
