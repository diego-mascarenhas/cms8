<div>
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" class="form-select">
        <option value="">Seleccione un idioma</option>
        @foreach($languages as $language)
            <option value="{{ $language->code }}" {{ $language->code == $selected ? 'selected' : '' }}>
                {{ $language->name }}
            </option>
        @endforeach
    </select>
</div>