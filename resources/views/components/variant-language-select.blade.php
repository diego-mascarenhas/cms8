@props(['name' => 'language_variant', 'id' => null, 'value' => null, 'label' => 'Variante de idioma'])

<div>
    <label for="{{ $id ?? $name }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id ?? $name }}" name="{{ $name }}" class="select2 form-select @error($name) is-invalid @enderror" required>
        <option value="">Seleccione una variante de idioma</option>
        @foreach($variants as $variant)
            <option value="{{ $variant->code }}" 
                    {{ old($name, $value) == $variant->code ? 'selected' : '' }}
                    data-country="{{ $variant->country_code ?? '' }}"
                    data-flag="{{ strtolower($variant->country_code ?? '') }}"
                    data-base="{{ $variant->base_language }}">
                {{ $variant->name }}
            </option>
        @endforeach
    </select>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@push('page-script')
<script>
    $(function () {
        const select = $('#{{ $id ?? $name }}');
        if (select.length) {
            select.select2({
                dropdownParent: select.parent(),
                templateResult: formatLanguage,
                templateSelection: formatLanguage,
                width: '100%'
            });
        }
        
        // Format language options with flags
        function formatLanguage(language) {
            if (!language.id) {
                return language.text;
            }
            
            const $option = $(language.element);
            let flag = $option.data('flag');
            
            // If no flag specified, try to get it from base language code
            if (!flag) {
                const baseCode = $option.data('base')?.toLowerCase();
                if (baseCode) {
                    // Map language codes to country codes for flags
                    const languageMap = {
                        'ja': 'jp', // Japanese -> Japan
                        'ko': 'kr', // Korean -> South Korea
                        'zh': 'cn', // Chinese -> China
                        'en': 'gb', // English -> Great Britain
                        'ar': 'sa'  // Arabic -> Saudi Arabia
                    };
                    
                    flag = languageMap[baseCode] || baseCode;
                }
            }
            
            if (!flag) {
                return language.text;
            }
            
            return $('<span><i class="fi fi-' + flag + ' me-2"></i>' + language.text + '</span>');
        }
    });
</script>
@endpush 