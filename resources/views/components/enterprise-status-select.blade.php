<div class="form-group">
    <span class="form-label d-block">{{ $label }}</span>
    <div class="@error($id) is-invalid @enderror">
        @foreach ($options as $option)
            <label class="form-check form-check-inline mt-2" for="{{ $id }}_{{ $option['id'] }}">
                <input type="radio" name="{{ $id }}" id="{{ $id }}_{{ $option['id'] }}" value="{{ $option['id'] }}"
                    class="form-check-input @error($id) is-invalid @enderror"
                    @checked($selected === (int) $option['id'])>
                <span class="form-check-label">{{ $option['name'] }}</span>
            </label>
        @endforeach
    </div>
    @error($id)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
