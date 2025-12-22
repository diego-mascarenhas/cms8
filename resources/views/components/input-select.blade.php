@props(['id', 'label' => null, 'options', 'value', 'placeholder' => null, 'required' => false, 'helpText' => null, 'disabled' => false])

<div class="form-group">
    @if($label)
        <label for="{{ $id }}">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
    @endif
    <select id="{{ $id }}" name="{{ $id }}" class="form-control select2 @error($id) is-invalid @enderror" @if($required) required @endif @if($disabled) disabled @endif>
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionKey => $option)
            @if(is_array($option))
                {{-- Format: [['id' => 1, 'name' => 'Lead']] --}}
                <option value="{{ $option['id'] }}"
                    {{ old($id, $value) == $option['id'] ? 'selected' : '' }}>
                    {{ $option['name'] }}
                </option>
            @else
                {{-- Format: [1 => 'Lead', 2 => 'Customer'] --}}
                <option value="{{ $optionKey }}"
                    {{ old($id, $value) == $optionKey ? 'selected' : '' }}>
                    {{ $option }}
                </option>
            @endif
        @endforeach
    </select>

    @if($helpText)
        <div class="form-text">{{ $helpText }}</div>
    @endif

    @error($id)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        $('#{{ $id }}').select2({
            placeholder: '{{ $placeholder ?? "Seleccionar" }}',
            allowClear: {{ $required ? 'false' : 'true' }},
            width: '100%'
        });
    });
</script>
@endpush
