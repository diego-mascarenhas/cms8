@php
    $selectedPlan = old('plan', $selectedPlan ?? '');
    $selectedServerId = old('server_id', $selectedServerId ?? '');
@endphp

<div class="col-md-6">
    <label for="plan" class="form-label">Plan</label>
    <select class="form-select @error('plan') is-invalid @enderror"
        id="plan"
        name="plan"
        @if(! $selectedServerId) disabled @endif>
        @if($selectedServerId)
            <option value="">Cargando planes...</option>
        @else
            <option value="">Seleccionar servidor primero</option>
        @endif
    </select>
    <div id="plan-help" class="form-text d-none"></div>
    @error('plan')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
