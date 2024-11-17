@props(['id', 'label' => null, 'options', 'value', 'placeholder' => null])

<div class="form-group">
    @if($label)
        <label for="{{ $id }}">{{ $label }}</label>
    @endif
    <select id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror">
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $option)
            <option value="{{ $option['id'] }}" 
                {{ old($id, $value ?? ($placeholder ? '' : $options[0]['id'])) == $option['id'] ? 'selected' : '' }}>
                {{ $option['name'] }}
            </option>
        @endforeach
    </select>
    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>