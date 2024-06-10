@props(['id', 'label', 'value' => ''])

<div class="form-group">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <input type="text" id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror" value="{{ old($id, $value?? '') }}" />
    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>