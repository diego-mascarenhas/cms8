@props(['name', 'label', 'value' => false])

<label class="switch switch-secondary">
    <input type="checkbox" class="switch-input" name="{{ $name }}" {{ old($name, $value) ? 'checked' : '' }} />
    <span class="switch-toggle-slider">
        <span class="switch-on">
            <i class="ti ti-check"></i>
        </span>
        <span class="switch-off">
            <i class="ti ti-x"></i>
        </span>
    </span>
    <span class="switch-label">{{ $label }}</span>
</label>