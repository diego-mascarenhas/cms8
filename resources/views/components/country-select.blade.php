@props(['name' => 'country', 'id' => null, 'value' => null, 'label' => 'País'])

<div>
    <label for="{{ $id ?? $name }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id ?? $name }}" name="{{ $name }}" class="form-select">
        <option value="">Seleccione un país</option>
        @foreach($countries as $country)
            <option value="{{ $country->id }}" {{ old($name, $value) == $country->id ? 'selected' : '' }}>
                {{ $country->name }}
            </option>
        @endforeach
    </select>
</div>
