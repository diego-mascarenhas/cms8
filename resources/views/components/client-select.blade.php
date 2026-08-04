@props(['id', 'label', 'selected' => null, 'allowNull' => true])

<div class="form-group">
    <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
        <label for="{{ $id }}" class="form-label mb-0">{{ $label }}</label>
    </div>
    <select
        id="{{ $id }}"
        name="{{ $id }}"
        class="select2 form-select @error($id) is-invalid @enderror"
        data-placeholder="{{ __('Select') }} {{ $label }}"
        data-allow-clear="{{ $allowNull ? 'true' : 'false' }}"
        @if(! $allowNull) required @endif
    >
        @if($allowNull)
            <option value="">{{ __('Select') }} {{ $label }}</option>
        @endif

        @foreach($options as $clientId => $clientName)
            <option value="{{ $clientId }}" {{ (string) $selected === (string) $clientId ? 'selected' : '' }}>
                {{ $clientName }}
            </option>
        @endforeach
    </select>

    @error($id)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div>

@push('scripts')
<script>
    $(function () {
        const $select = $('#{{ $id }}');
        if (! $select.length || ! $.fn.select2 || $select.hasClass('select2-hidden-accessible')) {
            return;
        }

        $select.select2({
            placeholder: $select.data('placeholder') || '',
            allowClear: String($select.data('allow-clear')) === 'true',
            width: '100%',
            dropdownParent: $(document.body),
        });
    });
</script>
@endpush
