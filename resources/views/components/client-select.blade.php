@props(['id', 'label', 'selected' => null, 'allowNull' => true])

<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror">
        @if($allowNull)
            <option value="">Select {{ $label }}</option>
        @endif
        
        @foreach($options as $clientId => $clientName)
            <option value="{{ $clientId }}" {{ $selected == $clientId ? 'selected' : '' }}>
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