@props(['id', 'label', 'selected' => null, 'showNull' => false])

<div class="form-group">
    <label for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $id }}" class="form-control @error($id) is-invalid @enderror">
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