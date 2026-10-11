@props(['id', 'label' => null, 'options', 'value', 'placeholder' => null, 'required' => false, 'helpText' => null, 'disabled' => false, 'allowClear' => null])

@php
    $allowClearResolved = $allowClear !== null ? (bool) $allowClear : ! $required;
@endphp

<div class="form-group">
    @if($label)
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1" style="min-height: 2.25rem;">
            <label for="{{ $id }}" class="form-label mb-0">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
        </div>
    @endif
    @if($disabled)
        <input type="hidden" name="{{ $id }}" value="{{ old($id, $value) }}">
    @endif
    <select id="{{ $id }}" class="form-control select2 @error($id) is-invalid @enderror"
        @if(! $disabled) name="{{ $id }}" @endif
        data-placeholder="{{ $placeholder ?? 'Seleccionar' }}"
        @if($required && ! $disabled) required @endif
        @if($disabled) disabled @endif>
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionKey => $option)
            @if(is_array($option) && isset($option['label']))
                {{-- Format: [['value' => 'x', 'label' => 'Shown', 'disabled' => true]] --}}
                <option value="{{ $option['value'] ?? '' }}"
                    {{ (string) old($id, $value) === (string) ($option['value'] ?? '') ? 'selected' : '' }}
                    @if(! empty($option['disabled'])) disabled @endif>
                    {{ $option['label'] }}
                </option>
            @elseif(is_array($option) && isset($option['id'], $option['name']))
                {{-- Format: [['id' => 1, 'name' => 'Lead', 'flag' => 'ar']] --}}
                <option value="{{ $option['id'] }}"
                    @if(! empty($option['flag'])) data-flag="{{ strtolower((string) $option['flag']) }}" @endif
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
    $(function () {
        var $el = $('#{{ $id }}');
        if (! $el.length) {
            return;
        }
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
        var $parent = $el.parent();
        var dropdownParent = $parent.hasClass('position-relative') ? $parent : $(document.body);
        var select2Options = {
            placeholder: @json($placeholder ?? 'Seleccionar'),
            allowClear: {{ $allowClearResolved ? 'true' : 'false' }},
            width: '100%',
            dropdownParent: dropdownParent,
        };
        if ($el.find('option[data-flag]').length) {
            var formatFlagOption = function (data) {
                if (!data.id || !data.element) {
                    return data.text;
                }
                var flag = $(data.element).attr('data-flag');
                if (!flag) {
                    return data.text;
                }
                return $('<span><span class="fi fi-' + flag + ' me-2"></span>' + data.text + '</span>');
            };
            select2Options.templateResult = formatFlagOption;
            select2Options.templateSelection = formatFlagOption;
        }
        $el.select2(select2Options);
        $el.on('select2:opening', function (e)
        {
            if ($el.prop('disabled'))
            {
                e.preventDefault();
            }
        });
    });
</script>
@endpush
