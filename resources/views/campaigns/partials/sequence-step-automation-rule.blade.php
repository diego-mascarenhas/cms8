@php
    $defaults = is_array($defaults ?? null) ? $defaults : [];
    $t = old('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.trigger', $defaults['trigger'] ?? '');
    $dh = old('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.delay_hours', $defaults['delay_hours'] ?? '');
    $ch = old('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.channel_type_id', $defaults['channel_type_id'] ?? '');
    $lm = old('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.linked_message_id', $defaults['message_id'] ?? '');
    $nt = old('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.notes', $defaults['notes'] ?? '');
@endphp
<div class="step-automation-rule mb-2" data-step-rule-row data-step-index="{{ $stepIndex }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small">{{ __('Regla') }} <span data-rule-label>{{ $ruleIndex + 1 }}</span></span>
        <button
            type="button"
            class="step-automation-remove cursor-pointer shadow-none text-danger bg-transparent border-0 p-0 lh-1 d-inline-flex align-items-center justify-content-center"
            title="{{ __('Quitar regla') }}"
            aria-label="{{ __('Quitar regla') }}"
        >
            <i class="ti ti-trash ti-sm"></i>
        </button>
    </div>
    <div class="border rounded p-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label small mb-0">{{ __('Disparador') }}</label>
            <select
                name="sequence[{{ $stepIndex }}][automations][{{ $ruleIndex }}][trigger]"
                class="form-select form-select-sm @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.trigger') is-invalid @enderror"
            >
                <option value="" @selected($t === '' || $t === null)>{{ __('Selecciona…') }}</option>
                <option value="after_previous_sent" @selected($t === 'after_previous_sent')>{{ __('Tras enviar el paso anterior') }}</option>
                <option value="if_opened_previous" @selected($t === 'if_opened_previous')>{{ __('Si abrió el paso anterior') }}</option>
                <option value="if_not_opened_previous" @selected($t === 'if_not_opened_previous')>{{ __('Si no abrió el paso anterior') }}</option>
                <option value="delay_after_enrollment" @selected($t === 'delay_after_enrollment')>{{ __('Tras el alta en la secuencia') }}</option>
            </select>
            @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.trigger')
                <div class="invalid-feedback d-block">{{ $errors->first('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.trigger') }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0">{{ __('Espera (h)') }}</label>
            <input
                type="number"
                name="sequence[{{ $stepIndex }}][automations][{{ $ruleIndex }}][delay_hours]"
                class="form-control form-control-sm @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.delay_hours') is-invalid @enderror"
                min="0"
                max="8760"
                placeholder="0"
                value="{{ $dh !== '' && $dh !== null ? $dh : '' }}"
            >
            @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.delay_hours')
                <div class="invalid-feedback d-block">{{ $errors->first('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.delay_hours') }}</div>
            @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0">{{ __('Canal (tipo)') }}</label>
            <select
                name="sequence[{{ $stepIndex }}][automations][{{ $ruleIndex }}][channel_type_id]"
                class="form-select form-select-sm @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.channel_type_id') is-invalid @enderror"
            >
                <option value="" @selected($ch === '' || $ch === null)>{{ __('Selecciona…') }}</option>
                @foreach ($messageTypes as $mt)
                    <option value="{{ $mt->id }}" @selected((string) $ch === (string) $mt->id)>{{ $mt->name }}</option>
                @endforeach
            </select>
            @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.channel_type_id')
                <div class="invalid-feedback d-block">{{ $errors->first('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.channel_type_id') }}</div>
            @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-0">{{ __('Mensaje (opcional)') }}</label>
            <select
                name="sequence[{{ $stepIndex }}][automations][{{ $ruleIndex }}][linked_message_id]"
                class="form-select form-select-sm @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.linked_message_id') is-invalid @enderror"
            >
                <option value="" @selected($lm === '' || $lm === null)>{{ __('Ninguno') }}</option>
                @foreach ($automationMessages as $am)
                    <option value="{{ $am->id }}" @selected((string) $lm === (string) $am->id)>{{ $am->name }} ({{ $am->type?->name ?? '—' }})</option>
                @endforeach
            </select>
            @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.linked_message_id')
                <div class="invalid-feedback d-block">{{ $errors->first('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.linked_message_id') }}</div>
            @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-0">{{ __('Notas') }}</label>
            <input
                type="text"
                name="sequence[{{ $stepIndex }}][automations][{{ $ruleIndex }}][notes]"
                class="form-control form-control-sm @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.notes') is-invalid @enderror"
                maxlength="500"
                placeholder="{{ __('Uso interno') }}"
                value="{{ $nt }}"
            >
            @error('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.notes')
                <div class="invalid-feedback d-block">{{ $errors->first('sequence.'.$stepIndex.'.automations.'.$ruleIndex.'.notes') }}</div>
            @enderror
        </div>
    </div>
    </div>
</div>
