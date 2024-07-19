@props(['id', 'label', 'value' => '', 'rows' => 3])

<div class="form-group">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <textarea id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror" rows="{{ $rows?? 3 }}">{{ old($id, $value?? '') }}</textarea>
    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>