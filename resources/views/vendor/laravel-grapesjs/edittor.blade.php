<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit {{ $model->editor_page_title }}</title>

    @foreach ($editorConfig->getStyles() as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach

    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
        }
    </style>
    <script>
        window.editorConfig = @json($editorConfig ?? []);

        Object.defineProperty(window, 'grapesjs', {
            value: {
                plugins: {
                    plugins: [],

                    /**
                     * Add new plugin. Plugins could not be overwritten
                     * @param {string} id Plugin ID
                     * @param {Function} plugin Function which contains all plugin logic
                     * @return {Function} The plugin function
                     * @example
                     * PluginManager.add('some-plugin', function(editor){
                     *   editor.Commands.add('new-command', {
                     *     run:  function(editor, senderBtn){
                     *       console.log('Executed new-command');
                     *     }
                     *   })
                     * });
                     */
                    add(id, plugin) {
                        if (this.plugins[id]) {
                            return this.plugins[id];
                        }

                        this.plugins[id] = plugin;

                        return plugin;
                    },

                    /**
                     * Returns plugin by ID
                     * @param  {string} id Plugin ID
                     * @return {Function|undefined} Plugin
                     * @example
                     * var plugin = PluginManager.get('some-plugin');
                     * plugin(editor);
                     */
                    get(id) {
                        return this.plugins[id];
                    },

                    /**
                     * Returns object with all plugins
                     * @return {Object}
                     */
                    getAll() {
                        return this.plugins;
                    },
                }
            }
        })
    </script>
</head>

<body>
    {{-- Temporarily hidden: AI assistant bar above GrapesJS editor --}}
    <div id="editor-assistant-bar" data-template-hashed-id="{{ $model->getHashedId() }}" style="display: none; padding: 6px 32px; background: #f8f9fa; border-radius: 8px; margin-bottom: 12px;">
      <div class="d-flex align-items-center gap-2" style="flex-wrap: wrap;">
        <img src="{{ asset('assets/logo-dark.svg') }}" alt="Humano" style="height: 36px; width: auto; min-width: 100px; object-fit: contain;" />
        <textarea id="generate-ai-prompt" rows="2" placeholder="Ej: Cambiar el color del botón, renombrar la plantilla, o describir contenido para generar"
          style="font-family: 'Public Sans', Arial, Helvetica, sans-serif; flex: 1 1 75%; min-width: 840px; max-width: 1200px; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px; resize: vertical;"></textarea>
        <button type="button" id="generate-ai-btn"
          style="padding: 0.5rem 1.25rem; background: #7367f0; color: #fff; border: none; border-radius: 0.375rem; font-size: 1rem; font-weight: 500; cursor: pointer; font-family: 'Public Sans', Arial, Helvetica, sans-serif; white-space: nowrap; box-shadow: 0 4px 8px rgba(115, 103, 240, 0.35);">
          Crear con el Asistente Humano
        </button>
      </div>
    </div>
    <div id="{{ str_replace('#', '', $editorConfig->container ?? 'editor') }}"></div>

    @foreach ($editorConfig->getScripts() as $script)
        <script src="{{ $script }}"></script>
    @endforeach

    <script>
    function getGrapesEditorInstance() {
        if (window.gjsEditor && typeof window.gjsEditor.setComponents === 'function') return window.gjsEditor;
        if (window.editor && typeof window.editor.setComponents === 'function') return window.editor;
        if (window.grapesjs && window.grapesjs.editors && window.grapesjs.editors.length) {
            for (let ed of window.grapesjs.editors) {
                if (typeof ed.setComponents === 'function') return ed;
            }
        }
        return null;
    }

    function openBlocksPanel(editor) {
        if (!editor) {
            return;
        }

        try {
            if (editor.Panels && typeof editor.Panels.getButton === 'function') {
                var openBlocksBtn = editor.Panels.getButton('views', 'open-blocks');
                if (openBlocksBtn && typeof openBlocksBtn.set === 'function') {
                    openBlocksBtn.set('active', true);

                    return;
                }
            }
        } catch (e1) {}

        try {
            if (typeof editor.runCommand === 'function') {
                editor.runCommand('open-blocks');

                return;
            }
        } catch (e2) {}

        var fallback = document.querySelector('.gjs-pn-views .gjs-pn-btn[title="Open Blocks"]')
            || document.querySelector('.gjs-pn-btn.fa-th-large');
        if (fallback && typeof fallback.click === 'function') {
            fallback.click();
        }
    }

    /**
     * Open the Blocks sidebar on editor load (same as clicking Open Blocks once).
     */
    function wireOpenBlocksPanelOnEditorLoad(editor) {
        if (!editor || typeof editor.on !== 'function') {
            return;
        }

        var blocksSidebarOpenedOnce = false;

        function scheduleOpenBlocksPanel() {
            if (blocksSidebarOpenedOnce) {
                return;
            }

            blocksSidebarOpenedOnce = true;
            requestAnimationFrame(function () {
                openBlocksPanel(editor);
            });
        }

        editor.on('load', scheduleOpenBlocksPanel);

        window.setTimeout(function () {
            try {
                if (!blocksSidebarOpenedOnce && editor.Canvas && editor.Canvas.getBody && editor.Canvas.getBody()) {
                    scheduleOpenBlocksPanel();
                }
            } catch (e) {}
        }, 250);
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        var match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    document.addEventListener('DOMContentLoaded', function() {
        var blocksPanelWired = false;
        var blocksPollAttempts = 0;
        var blocksPollId = window.setInterval(function () {
            if (blocksPanelWired) {
                window.clearInterval(blocksPollId);

                return;
            }

            blocksPollAttempts += 1;
            var grapesEditorInstance = getGrapesEditorInstance();

            if (grapesEditorInstance) {
                blocksPanelWired = true;
                wireOpenBlocksPanelOnEditorLoad(grapesEditorInstance);
                window.clearInterval(blocksPollId);
            }

            if (blocksPollAttempts > 200) {
                window.clearInterval(blocksPollId);
            }
        }, 25);

        setTimeout(function() {
            var generateAiBtn = document.getElementById('generate-ai-btn');
            if (generateAiBtn) {
                generateAiBtn.addEventListener('click', async function() {
                    var promptInput = document.getElementById('generate-ai-prompt');
                    var prompt = promptInput && promptInput.value ? promptInput.value.trim() : '';
                    if (!prompt) {
                        alert('Escribe tu solicitud (cambios, renombrar, generar contenido, etc.).');
                        return;
                    }
                    var bar = document.getElementById('editor-assistant-bar');
                    var templateHashedId = bar && bar.getAttribute ? bar.getAttribute('data-template-hashed-id') : '';
                    var btn = this;
                    var originalText = btn.textContent;
                    btn.disabled = true;
                    btn.textContent = 'Enviando...';
                    promptInput.value = '';
                    try {
                        var body = { message: prompt };
                        if (templateHashedId) body.template_hashed_id = templateHashedId;
                        var response = await fetch('{{ route("chat.assistant") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': getCsrfToken(),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(body)
                        });
                        var rawText = await response.text();
                        var data = rawText ? (function(){ try { return JSON.parse(rawText); } catch(e) { return {}; } })() : {};
                        if (data.success && data.response) {
                            alert(data.response);
                        } else {
                            alert(data.message || data.error || 'No se pudo enviar. Intenta de nuevo.');
                        }
                    } catch (e) {
                        alert('Error: ' + (e.message || 'Error de conexión'));
                    } finally {
                        btn.disabled = false;
                        btn.textContent = originalText;
                    }
                });
            }
        }, 1500); // Espera 1.5s para asegurar que el editor esté inicializado
    });
    </script>
</body>
</html>
