@props(['name' => 'language', 'id' => null, 'value' => null, 'label' => 'Idioma', 'required' => false])

<div>
    <label for="{{ $id ?? $name }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id ?? $name }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror" {{ $required ? 'required' : '' }}>
        <option value="">Seleccione un idioma</option>
        @foreach($languages as $language)
            <option value="{{ $language->code }}" {{ old($name, $value) == $language->code ? 'selected' : '' }}>
                {{ $language->name }}
            </option>
        @endforeach
    </select>
</div>
