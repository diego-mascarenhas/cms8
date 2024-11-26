<div class="row mb-2">
    <div class="col-sm-4">
        <label for="social_network_{{ $source->id }}" class="form-label">Red Social</label>
        <select id="social_network_{{ $source->id }}" class="form-select" name="source_id[]">
            <option value="">Selecciona una red social</option>
            @foreach ($socialSources as $socialSource)
                <option value="{{ $socialSource['id'] }}" {{ $socialSource['id'] == $source->id ? 'selected' : '' }}>
                    {{ $socialSource['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-6">
        <label for="social_link_{{ $source->id }}" class="form-label">Enlace de la red social</label>
        <input type="text" class="form-control" id="social_link_{{ $source->id }}" name="source_value[]" value="{{ $source->pivot->value ?? '' }}">
    </div>
    <div class="col-sm-2">
        <br><button type="button" class="btn btn-danger remove-social-link">Eliminar</button>
    </div>
</div>