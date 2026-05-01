@props(['id', 'label', 'value' => '', 'maxlength' => null])

<div class="form-group">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1" style="min-height: 2.25rem;">
        <label for="{{ $id }}" class="form-label mb-0">{{ $label }}</label>
    </div>
    <input type="text" id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror" value="{{ old($id, $value?? '') }}" @if($maxlength) maxlength="{{ $maxlength }}" @endif />
    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>