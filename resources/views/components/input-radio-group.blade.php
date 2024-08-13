@props(['id', 'label', 'name', 'options', 'value' => ''])

<label class="d-block form-label">{{ $label }}</label>
@foreach ($options as $option)
    <div class="form-check d-inline-block mb-2">
        <input type="radio" id="{{ $id }}-{{ $option }}" name="{{ $name }}" value="{{ $option }}" class="form-check-input" {{ old($name, $value) == $option? 'checked' : '' }} />
        <label class="form-check-label" for="{{ $id }}-{{ $option }}">{{ $option }}</label>
    </div>
@endforeach
@error($name)
    <div class="invalid-feedback d-block">{{ $message }}</div>
@enderror