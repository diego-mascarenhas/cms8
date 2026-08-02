@props(['id', 'label', 'selected' => null, 'showNull' => false])

<div class="form-group">
    <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
        <label for="{{ $id }}" class="form-label mb-0">{{ $label }}</label>
    </div>
    <select id="{{ $id }}" name="{{ $id }}" class="select2 form-select @error($id) is-invalid @enderror" data-placeholder="Seleccione {{ $label }}" data-allow-clear="true" required>
        @if($showNull)
            <option value="">Seleccione {{ $label }}</option>
        @endif
        
        @foreach($options as $userId => $userName)
            <option value="{{ $userId }}" {{ $selected == $userId ? 'selected' : '' }}>
                {{ $userName }}
            </option>
        @endforeach
    </select>
    
    @error($id)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div>

@push('page-script')
<script>
    $(function () {
        const select = $('#{{ $id }}');
        if (select.length) {
            select.select2({
                dropdownParent: select.parent(),
                width: '100%'
            });
        }
    });
</script>
@endpush 