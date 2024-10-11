<div>
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" class="form-select">
        <option value="">Seleccione un país</option>
        @foreach($countries as $country)
            <option value="{{ $country->id }}" {{ $country->id == $selected ? 'selected' : '' }}>
                {{ $country->name }}
            </option>
        @endforeach
    </select>
</div>