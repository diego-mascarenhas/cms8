@props([
    'label',
    'type' => 'text',
    'required' => false,
    'hint' => null,
    'adminOnly' => false,
    'sample' => '',
    'col' => 'col-md-6',
])

<div class="{{ $col }}">
    <label class="form-label">
        {{ __($label) }}
        @if ($required)
            <span class="text-danger">*</span>
        @endif
        @if ($adminOnly)
            <span class="badge bg-label-primary ms-1">Admin</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea class="form-control" rows="3" disabled placeholder="{{ $sample }}">{{ $sample }}</textarea>
    @elseif ($type === 'select')
        <select class="form-select" disabled>
            <option>{{ $sample !== '' ? $sample : __('Seleccionar…') }}</option>
        </select>
    @elseif ($type === 'checkbox')
        <div class="form-check">
            <input type="checkbox" class="form-check-input" disabled @checked($sample === '1' || $sample === true)>
            <label class="form-check-label text-muted">{{ $hint ?? $label }}</label>
        </div>
    @elseif ($type === 'date')
        <input type="text" class="form-control" disabled value="{{ $sample !== '' ? $sample : 'YYYY-MM-DD' }}">
    @else
        <input type="{{ $type }}" class="form-control" disabled value="{{ $sample }}" placeholder="{{ $sample }}">
    @endif

    @if ($hint && $type !== 'checkbox')
        <div class="form-text">{{ __($hint) }}</div>
    @endif
</div>
