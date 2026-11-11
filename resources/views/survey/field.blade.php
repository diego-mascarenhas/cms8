<div class="card mt-3 survey-field">
    <div class="card-body">
        <input type="hidden" name="fields[{{ $index }}][key]" value="{{ $field['key'] ?? '' }}">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">{{ __('Pregunta') }}</label>
                <input type="text" class="form-control" name="fields[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('Ayuda') }}</label>
                <input type="text" class="form-control" name="fields[{{ $index }}][help]" value="{{ $field['help'] ?? '' }}">
            </div>
            <div class="col-md-12 d-flex gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="fields[{{ $index }}][required]" value="1" @checked($field['required'] ?? false)>
                    <label class="form-check-label">{{ __('Obligatoria') }}</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="fields[{{ $index }}][anonymous]" value="1" @checked($field['anonymous'] ?? false)>
                    <label class="form-check-label">{{ __('Anónima') }}</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="fields[{{ $index }}][for_certificate]" value="1" @checked($field['for_certificate'] ?? false)>
                    <label class="form-check-label">{{ __('Certificación') }}</label>
                </div>
            </div>
            <div class="col-md-8">
                <label class="form-label">{{ __('Opciones') }}</label>
                @foreach (($field['options'] ?? [['label' => '', 'value' => '']]) as $optionIndex => $option)
                <div class="input-group mb-2">
                    <input type="text" class="form-control" name="fields[{{ $index }}][options][{{ $optionIndex }}][label]" value="{{ $option['label'] ?? '' }}" placeholder="{{ __('Opción') }}">
                    <input type="hidden" name="fields[{{ $index }}][options][{{ $optionIndex }}][value]" value="{{ $option['value'] ?? '' }}">
                </div>
                @endforeach
                <textarea class="form-control" name="fields[{{ $index }}][options_text]" rows="2" placeholder="{{ __('Nuevas opciones, una por línea') }}"></textarea>
            </div>
        </div>
    </div>
</div>
