@props(['id', 'label', 'options', 'value'])

<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror">
        <option value="">Seleccione una opción</option>
        @foreach ($options as $option)
            <option value="{{ $option['id'] }}" {{ old($id, $value) == $option['id'] ? 'selected' : '' }}>
                {{ $option['full_name'] }}
            </option>
        @endforeach
    </select>
    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>