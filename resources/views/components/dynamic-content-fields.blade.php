@props(['fieldConfigs', 'content' => null])

@php
    $contentData = $content ? ($content->data ?? []) : [];
@endphp

@foreach($fieldConfigs as $config)
    <div class="mb-3">
        <label for="data_{{ $config->field_key }}" class="form-label">
            {{ $config->field_label }}
            @if($config->required)
                <span class="text-danger">*</span>
            @endif
        </label>

        @switch($config->field_type)
            @case('text')
                <input type="text"
                    class="form-control"
                    id="data_{{ $config->field_key }}"
                    name="data[{{ $config->field_key }}]"
                    value="{{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? '') }}"
                    @if($config->required) required @endif>
                @break

            @case('url')
                <input type="text"
                    class="form-control"
                    id="data_{{ $config->field_key }}"
                    name="data[{{ $config->field_key }}]"
                    value="{{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? '') }}"
                    autocomplete="off"
                    inputmode="url"
                    @if($config->required) required @endif>
                <div class="form-text">{{ __('app.Dynamic url field hint') }}</div>
                @break

            @case('email')
                <input type="email"
                    class="form-control"
                    id="data_{{ $config->field_key }}"
                    name="data[{{ $config->field_key }}]"
                    value="{{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? '') }}"
                    @if($config->required) required @endif>
                @break

            @case('textarea')
                <textarea class="form-control"
                    id="data_{{ $config->field_key }}"
                    name="data[{{ $config->field_key }}]"
                    rows="3"
                    @if($config->required) required @endif>{{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? '') }}</textarea>
                @break

            @case('number')
                <input type="number"
                    class="form-control"
                    id="data_{{ $config->field_key }}"
                    name="data[{{ $config->field_key }}]"
                    value="{{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? '') }}"
                    @if($config->required) required @endif>
                @break

            @case('date')
                <input type="date"
                    class="form-control"
                    id="data_{{ $config->field_key }}"
                    name="data[{{ $config->field_key }}]"
                    value="{{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? '') }}"
                    @if($config->required) required @endif>
                @break

            @case('datetime')
                <input type="datetime-local"
                    class="form-control"
                    id="data_{{ $config->field_key }}"
                    name="data[{{ $config->field_key }}]"
                    value="{{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? '') }}"
                    @if($config->required) required @endif>
                @break

            @case('checkbox')
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox"
                        id="data_{{ $config->field_key }}"
                        name="data[{{ $config->field_key }}]"
                        value="1"
                        {{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="data_{{ $config->field_key }}">
                        {{ __('app.Yes') }}
                    </label>
                </div>
                @break

            @case('select')
                <select class="form-select"
                    id="data_{{ $config->field_key }}"
                    name="data[{{ $config->field_key }}]"
                    @if($config->required) required @endif>
                    <option value="">{{ __('app.Select') }}...</option>
                    @if($config->field_options && isset($config->field_options['options']))
                        @foreach($config->field_options['options'] as $value => $label)
                            <option value="{{ $value }}"
                                {{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? '') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    @endif
                </select>
                @break

            @default
                <input type="text"
                    class="form-control"
                    id="data_{{ $config->field_key }}"
                    name="data[{{ $config->field_key }}]"
                    value="{{ old("data.{$config->field_key}", $contentData[$config->field_key] ?? '') }}"
                    @if($config->required) required @endif>
        @endswitch

        @error("data.{$config->field_key}")
            <div class="text-danger mt-1">{{ $message }}</div>
        @enderror
    </div>
@endforeach
