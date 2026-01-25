@props([
	'name' => 'language_variant',
	'id' => null,
	'value' => null,
	'label' => 'Variante de idioma',
	'required' => false,
	'placeholder' => null,
])

<div>
	@if ($label)
		<label for="{{ $id ?? $name }}" class="form-label">{{ $label }}</label>
	@endif
	<select id="{{ $id ?? $name }}" name="{{ $name }}"
		class="select2 form-select @error($name) is-invalid @enderror" data-placeholder="{{ $placeholder ?? 'Seleccione una variante de idioma' }}" {{ $required ? 'required' : '' }}>
		<option value="">{{ $placeholder ?? 'Seleccione una variante de idioma' }}</option>
				@foreach ($options as $option)
			<option value="{{ $option->value }}"
				{{ old($name, $value) == $option->value ? 'selected' : '' }}
				data-flag="{{ strtolower($option->flag ?? '') }}"
				data-base="{{ strtolower($option->base ?? '') }}">
				{{ $option->label }}
			</option>
		@endforeach
	</select>
	@error($name)
		<div class="invalid-feedback">{{ $message }}</div>
	@enderror
</div>

@once
	@push('page-script')
		<script>
									// Función global para formatear idiomas con banderas (solo se define una vez)
			if (typeof window.formatVariantLanguage === 'undefined') {
				window.formatVariantLanguage = function(language) {
					if (!language.id) {
						return language.text;
					}

					// Try to get flag from the option element
					let flag = null;
					let baseCode = null;

					if (language.element) {
						const $option = $(language.element);
						flag = $option.data('flag');
						baseCode = $option.data('base');
					}

					// If no flag specified, try to get it from base language code
					if (!flag && baseCode) {
						// Map language codes to country codes for flags
						const languageMap = {
							'ja': 'jp', 'ko': 'kr', 'zh': 'cn', 'en': 'gb', 'ar': 'sa',
							'fr': 'fr', 'es': 'es', 'de': 'de', 'it': 'it', 'pt': 'pt',
							'ru': 'ru', 'ca': 'es', 'eu': 'es', 'gl': 'es', 'val': 'es',
							'nl': 'nl', 'sv': 'se', 'da': 'dk', 'no': 'no', 'fi': 'fi',
							'pl': 'pl', 'cs': 'cz', 'sk': 'sk', 'hu': 'hu', 'ro': 'ro',
							'bg': 'bg', 'hr': 'hr', 'sl': 'si', 'et': 'ee', 'lv': 'lv',
							'lt': 'lt', 'mt': 'mt', 'ga': 'ie', 'cy': 'gb', 'gd': 'gb',
							'kw': 'gb', 'br': 'fr', 'oc': 'fr', 'co': 'fr', 'wa': 'be',
							'lb': 'lu', 'rm': 'ch'
						};

						flag = languageMap[baseCode] || baseCode;
					}

					if (!flag) {
						return language.text;
					}

					// Ensure flag is lowercase for CSS class
					flag = flag.toString().toLowerCase();

					return $('<span><i class="fi fi-' + flag + ' me-2"></i>' + language.text + '</span>');
				};
			}
		</script>
	@endpush
@endonce

@push('page-script')
	<script>
		$(function() {
			// Inicializar Select2 solo si no está ya inicializado
			const select = $('#{{ $id ?? $name }}');
			if (select.length && !select.hasClass('select2-hidden-accessible')) {
				select.select2({
					dropdownParent: select.parent(),
					templateResult: window.formatVariantLanguage || function(lang) {
						return lang.text;
					},
					templateSelection: window.formatVariantLanguage || function(lang) {
						return lang.text;
					},
					width: '100%'
				});
			}
		});
	</script>
@endpush
