@props(['id', 'label', 'name' => null, 'value' => ''])

@php
    $dateValue = $value ? \Carbon\Carbon::parse($value) : null;
    $locale = App::getLocale();
    $dateFormat = [
        'en' => 'Y-m-d',
        'es' => 'd-m-Y',
        'fr' => 'd-m-Y',
        'de' => 'd.m.Y',
        'it' => 'd/m/Y',
        'pt' => 'd/m/Y',
    ][$locale] ?? 'Y-m-d';
@endphp

<div class="form-group">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <input 
        type="text" 
        id="{{ $id }}" 
        name="{{ $name ?? $id }}" 
        class="form-control @error($name ?? $id) is-invalid @enderror" 
        value="{{ old($name ?? $id, $dateValue ? $dateValue->format('Y-m-d') : '') }}" 
        autocomplete="off"
    />
    @error($name ?? $id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const locale = '{{ $locale }}';
        const dateFormat = '{{ $dateFormat }}';
        const initialValue = '{{ $dateValue ? $dateValue->format('Y-m-d') : '' }}';
        
        function loadLocale(locale, callback) {
            if (locale === 'en') {
                callback();
            } else {
                const script = document.createElement('script');
                script.src = `https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/${locale}.js`;
                script.onload = callback;
                script.onerror = () => {
                    console.warn(`Failed to load ${locale} locale for Flatpickr. Falling back to default.`);
                    callback();
                };
                document.head.appendChild(script);
            }
        }

        loadLocale(locale, function() {
            if (locale !== 'en' && flatpickr.l10ns[locale]) {
                flatpickr.localize(flatpickr.l10ns[locale]);
            }
            
            flatpickr('#{{ $id }}', {
                dateFormat: 'Y-m-d',
                allowInput: true,
                altInput: true,
                altFormat: dateFormat,
                defaultDate: initialValue,
                onReady: function(selectedDates, dateStr, instance) {
                    if (initialValue) {
                        instance.setDate(initialValue, true);
                    }
                }
            });
        });
    });
</script>
@endpush
