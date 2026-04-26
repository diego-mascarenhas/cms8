@extends('layouts/layoutMaster')

@section('title', isset($prompt) ? __('Editar prompt') : __('Crear prompt'))

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">{{ __('Prompts') }}/</span>
            {{ isset($prompt) ? __('Editar') : __('Crear') }}
        </h4>
        <p class="text-muted">{{ __('Instrucciones por módulo para IA') }}</p>
    </div>
    @if(isset($prompt))
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('view', $prompt)
        <a href="{{ route('prompt.show', $prompt) }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-sparkles me-1"></i>{{ __('Probar') }}
        </a>
        @endcan
        @can('delete', $prompt)
        <form action="{{ route('prompt.destroy', $prompt) }}" method="POST" class="d-inline btn-delete-form">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger waves-effect waves-light btn-delete">
                <i class="ti ti-trash me-1"></i>{{ __('Eliminar') }}
            </button>
        </form>
        @endcan
    </div>
    @endif
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ isset($prompt) ? __('Editar prompt') : __('Nuevo prompt') }}</h5>
    <form class="card-body" action="{{ isset($prompt) ? route('prompt.update', $prompt) : route('prompt.store') }}" method="POST">
        @csrf
        @if(isset($prompt))
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="module_id">{{ __('Módulo') }} (*)</label>
                <select name="module_id" id="module_id" class="form-select @error('module_id') is-invalid @enderror" required>
                    <option value="">{{ __('Seleccione módulo') }}</option>
                    @foreach($modules as $module)
                        <option value="{{ $module->id }}" {{ old('module_id', $prompt->module_id ?? '') == $module->id ? 'selected' : '' }}>
                            {{ $module->name }}
                        </option>
                    @endforeach
                </select>
                @error('module_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="section_label">{{ __('Etiqueta de sección') }} (*)</label>
                <input type="text" class="form-control @error('section_label') is-invalid @enderror" id="section_label" name="section_label"
                    value="{{ old('section_label', $prompt->section_label ?? '') }}" maxlength="255" required>
                <div class="form-text">
                    {{ __('Si el chat del equipo tiene activo el enrutado por palabras clave, y esta etiqueta tiene al menos 12 caracteres, el mensaje del usuario también se compara contra esta frase (además de la clave): misma lógica de palabras/frases, sin inferencia semántica por IA.') }}
                </div>
                @error('section_label')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label class="form-label" for="section_key">{{ __('Clave de sección') }} (*)</label>
                <input type="text" class="form-control @error('section_key') is-invalid @enderror" id="section_key" name="section_key"
                    value="{{ old('section_key', $prompt->section_key ?? '') }}" placeholder="ej: estrategia, human_3_0" maxlength="255" required>
                <div class="form-text">
                    {{ __('Por defecto el enrutado es por la IA: el asistente ve la lista de claves (módulo + esta clave, p. ej. «chat:mi_flujo») y confirma la intención con el usuario cuando hace falta, luego usa la herramienta de flujo con esa clave.') }}
                    {{ __('Esta clave es el identificador del flujo; mantenela estable y sin espacios (usá guiones bajos).') }}
                    {{ __('Solo si en Configuración del equipo → Chat / Asistente activás «Enrutado automático por palabras clave a flujos», se puede asociar el mensaje a un flujo sin que el modelo elija, comparando además con esta clave (guiones bajos como espacio; palabras sueltas).') }}
                </div>
                @error('section_key')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label class="form-label" for="prompt_instruction">{{ __('Instrucción para la IA') }} (*)</label>
                <textarea class="form-control @error('prompt_instruction') is-invalid @enderror" id="prompt_instruction" name="prompt_instruction"
                    rows="12" required>{{ old('prompt_instruction', $prompt->prompt_instruction ?? '') }}</textarea>
                <div class="form-text">{{ __('Puedes usar formato Markdown.') }}</div>
                @error('prompt_instruction')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label class="form-label" for="helper_text">{{ __('Texto de ayuda') }}</label>
                <textarea class="form-control @error('helper_text') is-invalid @enderror" id="helper_text" name="helper_text"
                    rows="6">{{ old('helper_text', $prompt->helper_text ?? '') }}</textarea>
                @error('helper_text')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="order">{{ __('Orden') }}</label>
                <input type="number" class="form-control @error('order') is-invalid @enderror" id="order" name="order"
                    value="{{ old('order', $prompt->order ?? 0) }}" min="0" step="1">
                @error('order')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                        {{ old('is_active', $prompt->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">{{ __('Activo') }}</label>
                </div>
            </div>
        </div>

        <div class="pt-4">
            <button type="submit" class="btn btn-primary me-sm-3 me-1">{{ __('Guardar') }}</button>
            <button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('prompt-list') }}'">{{ __('Cancelar') }}</button>
        </div>
    </form>
</div>

@endsection

@if(isset($prompt))
@push('scripts')
<script>
    $(function() {
        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            Swal.fire({
                title: '{{ __("¿Estás seguro?") }}',
                text: "{{ __("No podrás revertir esto.") }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '{{ __("Sí, eliminar") }}',
                cancelButtonText: '{{ __("Cancelar") }}',
                customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
                buttonsStyling: false
            }).then(function(result) {
                if (result.value) form.submit();
            });
        });
    });
</script>
@endpush
@endif
