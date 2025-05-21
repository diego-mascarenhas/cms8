@props(['id', 'label', 'selected' => null, 'showNull' => false])

<div class="form-group">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $id }}" class="select2 form-select @error($id) is-invalid @enderror" data-allow-clear="true" required>
        @if($showNull)
            <option value="">Select {{ $label }}</option>
        @endif
        
        @foreach(auth()->user()->currentTeam->allUsers() as $user)
            <option value="{{ $user->id }}" {{ $selected == $user->id ? 'selected' : '' }}>
                {{ $user->name }}
            </option>
        @endforeach
    </select>
    
    @error($id)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div> 