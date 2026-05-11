@extends('layouts/layoutMaster')

@section('title', __('Editor GrapesJS'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendor/laravel-grapesjs/assets/editor.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('vendor/laravel-grapesjs/assets/editor.js') }}"></script>
@endsection

@section('page-script')
<script>
    $(function ()
    {
        const bodyInput = $('#body');
        const defaultBodyHtml = @json($defaultBody);

        const grapesEditor = grapesjs.init({
            container: '#gjs-editor',
            fromElement: false,
            height: 'calc(100vh - 320px)',
            width: 'auto',
            storageManager: false,
            selectorManager: {
                componentFirst: true,
            },
            plugins: ['gjs-plugin-ckeditor'],
            pluginsOpts: {
                'gjs-plugin-ckeditor': {
                    position: 'left',
                },
            },
            components: defaultBodyHtml,
        });

        const syncGrapesContent = function ()
        {
            const css = grapesEditor.getCss();
            const html = grapesEditor.getHtml();
            bodyInput.val('<style>' + css + '</style>' + html);
        };

        grapesEditor.on('load', function ()
        {
            syncGrapesContent();
        });

        grapesEditor.on('update', function ()
        {
            syncGrapesContent();
        });
    });
</script>
@endsection

@section('content')
<form action="#" method="POST" class="mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Editor visual de correo') }}</h4>
            <p class="text-muted mb-0">{{ __('Editando plantilla con GrapesJS') }}</p>
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a
                href="{{ route('campaigns.classic-editor', ['type' => $selectedType, 'title' => $selectedTitle, 'template_id' => $selectedTemplateId]) }}"
                class="btn btn-label-secondary"
            >
                {{ __('Volver') }}
            </a>
            <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="gjs-editor" class="border rounded overflow-hidden"></div>
            <textarea id="body" name="body" class="d-none"></textarea>
        </div>
    </div>
</form>
@endsection
