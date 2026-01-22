@extends('layouts/layoutMaster')

@section('title', isset($sla) ? 'Editar SLA' : 'Crear SLA')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Wait a bit to ensure Quill is loaded
        if (typeof Quill === 'undefined') {
            console.error('Quill is not loaded');
            return;
        }

        // Initialize Quill editor with full toolbar
        var quill = new Quill('#snow-editor', {
            theme: 'snow',
            modules: {
                toolbar: '#snow-toolbar'
            },
            placeholder: 'Escribe el contenido del SLA aquí...'
        });

        // Load existing content if available
        var existingContent = document.querySelector('#content').value;
        if (existingContent && existingContent.trim() !== '' && existingContent.trim() !== '<p><br></p>' && existingContent.trim() !== '<p></p>') {
            quill.root.innerHTML = existingContent;
        }

        // Function to get text content (without HTML tags) for validation
        function getTextContent(html) {
            var div = document.createElement('div');
            div.innerHTML = html;
            return div.textContent || div.innerText || '';
        }

        // Update hidden input on content change
        quill.on('text-change', function() {
            var html = quill.root.innerHTML;
            var textContent = getTextContent(html).trim();

            // Only update if there's actual text content
            if (textContent.length > 0) {
                document.querySelector('#content').value = html;
            } else {
                document.querySelector('#content').value = '';
            }
        });

        // Form submission handler - ensure content is synced
        var form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                var html = quill.root.innerHTML;
                var textContent = getTextContent(html).trim();

                // Sync content before validation
                if (textContent.length > 0) {
                    document.querySelector('#content').value = html;
                } else {
                    document.querySelector('#content').value = '';
                }

                // Validate that content is not empty
                if (!textContent || textContent.length < 10) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Contenido requerido',
                        text: 'Por favor, ingresa el contenido del SLA (mínimo 10 caracteres).',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
            });
        }
    });
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">
            <span class="text-muted fw-light">Producto/</span>
            {{ $product->name }}
            <span class="text-muted fw-light">/</span>
            {{ isset($sla) ? 'Editar SLA' : 'Crear SLA' }}
        </h4>
        <p class="text-muted">Gestión de Acuerdo de Nivel de Servicio</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        <a href="{{ route('account.products.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ isset($sla) ? 'Editar SLA' : 'Crear SLA' }}</h5>
    <form class="card-body"
          action="{{ isset($sla) ? route('sla.update', ['productId' => $product->id, 'slaId' => $sla->id]) : route('sla.store', $product->id) }}"
          method="POST">
        @csrf
        @if(isset($sla))
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-12">
                <x-input-general
                    id="title"
                    label="Título del SLA (*)"
                    value="{{ old('title', $sla->title ?? '') }}"
                />
            </div>

            <div class="col-md-6">
                <x-input-general
                    id="version"
                    label="Versión"
                    value="{{ old('version', $sla->version ?? '1.0') }}"
                />
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input"
                               type="checkbox"
                               id="is_active"
                               name="is_active"
                               value="1"
                               {{ old('is_active', $sla->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            Activo
                        </label>
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <label for="content" class="form-label">Contenido del SLA (*)</label>
                <!-- Toolbar container -->
                <div id="snow-toolbar" class="mb-2">
                    <span class="ql-formats">
                        <button class="ql-bold"></button>
                        <button class="ql-italic"></button>
                        <button class="ql-underline"></button>
                        <button class="ql-strike"></button>
                    </span>
                    <span class="ql-formats">
                        <select class="ql-header"></select>
                        <select class="ql-size"></select>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-list" value="ordered"></button>
                        <button class="ql-list" value="bullet"></button>
                        <button class="ql-indent" value="-1"></button>
                        <button class="ql-indent" value="+1"></button>
                    </span>
                    <span class="ql-formats">
                        <select class="ql-color"></select>
                        <select class="ql-background"></select>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-link"></button>
                        <button class="ql-image"></button>
                        <button class="ql-blockquote"></button>
                        <button class="ql-code-block"></button>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-clean"></button>
                    </span>
                </div>
                <!-- Editor container -->
                <div id="snow-editor" style="height: 400px; background: white;">
                </div>
                <input type="hidden" id="content" name="content" value="{{ old('content', $sla->content ?? '') }}">
                @error('content')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="pt-4">
            <div class="col-12 d-flex">
                <button type="submit" class="btn btn-primary me-sm-3 me-1">
                    {{ isset($sla) ? 'Actualizar' : 'Crear' }} SLA
                </button>
                <a href="{{ route('account.products.index') }}" class="btn btn-label-secondary">
                    Cancelar
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
