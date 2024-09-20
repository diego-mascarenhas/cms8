@props(['id', 'label' => null, 'options', 'value', 'placeholder' => 'Select an option'])

<div class="form-group">
    @if($label)
        <label for="{{ $id }}">{{ $label }}</label>
    @endif
    <select id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror">
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $option)
            <option value="{{ $option['id'] }}" {{ old($id, $value) == $option['id'] ? 'selected' : '' }}>
                {{ $option['name'] }}
            </option>
        @endforeach
    </select>
    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>