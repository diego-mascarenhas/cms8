@props(['id', 'label', 'value' => '', 'maxlength' => null, 'type' => 'text'])

@php
    use App\Support\FormFieldValue;

    $fieldValue = FormFieldValue::normalize(old($id, $value ?? ''));
@endphp

<div class="form-group @if($type === 'password') form-password-toggle @endif">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1" style="min-height: 2.25rem;">
        <label for="{{ $id }}" class="form-label mb-0">{{ $label }}</label>
    </div>
    @if ($type === 'password')
        <div class="input-group input-group-merge">
            <input type="password" id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror" value="{{ $fieldValue }}" @if($maxlength) maxlength="{{ $maxlength }}" @endif autocomplete="new-password" />
            <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
        </div>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror" value="{{ $fieldValue }}" @if($maxlength) maxlength="{{ $maxlength }}" @endif />
    @endif
    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
