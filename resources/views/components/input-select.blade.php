@props(['id', 'label', 'options', 'value'])

<div class="form-group">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $id }}" class="select2 form-select @error($id) is-invalid @enderror" data-allow-clear="false">
        @foreach ($options as $option)
            <option value="{{ $option }}" @if (old($id, $value) == $option) selected @endif>{{ $option }}</option>
        @endforeach
    </select>
    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>