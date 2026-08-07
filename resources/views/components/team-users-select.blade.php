@props([
    'id',
    'label',
    'selected' => null,
    'name' => null,
    'showNull' => false,
    'compact' => false,
    'disabled' => false,
])

@php
    $selectName = $name ?? $id;
@endphp

<div @class(['form-group' => ! $compact, 'w-100' => $compact])>
    @unless($compact)
        <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
            <label for="{{ $id }}" class="form-label mb-0">{{ $label }}</label>
        </div>
    @endunless
    <select
        id="{{ $id }}"
        name="{{ $selectName }}"
        class="select2 form-select {{ $compact ? 'form-select-sm' : '' }} @error($selectName) is-invalid @enderror"
        data-placeholder="{{ $compact ? __('Select') : __('Select').' '.$label }}"
        data-allow-clear="true"
        @disabled($disabled)
        required
    >
        @if($showNull || $compact)
            <option value="">{{ $compact ? __('Select') : __('Select').' '.$label }}</option>
        @endif

        @foreach($options as $userId => $userName)
            <option value="{{ $userId }}" {{ (string) $selected === (string) $userId ? 'selected' : '' }}>
                {{ $userName }}
            </option>
        @endforeach
    </select>

    @error($selectName)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div>

@push('scripts')
<script>
    $(function () {
        const select = $('#{{ $id }}');
        if (select.length && !select.hasClass('select2-hidden-accessible') && $.fn.select2) {
            // Body parent avoids clipping inside .table-responsive overflow
            const parent = select.closest('.table-responsive').length
                ? $(document.body)
                : select.parent();

            select.select2({
                dropdownParent: parent,
                width: '100%',
                placeholder: select.data('placeholder') || '',
                allowClear: true,
            });
        }
    });
</script>
@endpush
