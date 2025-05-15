@props(['name' => 'language_variant', 'id' => null, 'value' => null, 'label' => 'Variante de idioma'])

<div>
    <label for="{{ $id ?? $name }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id ?? $name }}" name="{{ $name }}" class="form-select select2">
        <option value="">Seleccione una variante de idioma</option>
        @foreach($variants as $variant)
            <option value="{{ $variant->code }}" 
                    {{ old($name, $value) == $variant->code ? 'selected' : '' }}
                    data-country="{{ $variant->country_code ?? '' }}"
                    data-base="{{ $variant->base_language }}">
                @if($variant->flag)
                    <span class="fi fi-{{ strtolower($variant->flag) }} me-1"></span>
                @endif
                {{ $variant->name }}
            </option>
        @endforeach
    </select>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('{{ $id ?? $name }}');
        if (select) {
            // Initialize Select2
            $(select).select2({
                templateResult: formatLanguage,
                templateSelection: formatLanguage
            });
        }
        
        // Format language options with flags
        function formatLanguage(language) {
            if (!language.id) {
                return language.text;
            }
            
            const $option = $(language.element);
            const country = $option.data('country');
            
            if (!country) {
                return language.text;
            }
            
            return $('<span><span class="fi fi-' + country.toLowerCase() + ' me-2"></span>' + language.text + '</span>');
        }
    });
</script> 