<div class="row mb-2">
    <div class="col-sm-4">
        <label for="social_network_{{ $source->id }}" class="form-label">Red Social</label>
        <select id="social_network_{{ $source->id }}" class="form-select" name="sources[{{ $source->id }}]">
            <option value="">Selecciona una red social</option>
            @foreach ($socialSources as $socialSource)
                <option value="{{ $socialSource['id'] }}" {{ $socialSource['id'] == $source->id ? 'selected' : '' }}>
                    {{ $socialSource['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-6">
        <x-input-general id="social_link_{{ $source->id }}" label="Enlace de la red social" value="{{ $source->pivot->value ?? '' }}" name="pivot_value[{{ $source->id }}]" />
    </div>
    <div class="col-sm-2">
        <br><button type="button" class="btn btn-danger remove-social-link">Eliminar</button>
    </div>
</div>