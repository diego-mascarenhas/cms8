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
    <div style="padding: 6px 32px; background: #f8f9fa; border-radius: 8px; margin-bottom: 12px;">
      <div class="d-flex align-items-center gap-2" style="flex-wrap: wrap;">
        <span style="font-size: 14px; color: #444; font-family: 'Public Sans', Arial, Helvetica, sans-serif; font-weight: 400; min-width: 140px;">Generar con IA</span>
        <textarea id="generate-ai-prompt" rows="2" placeholder="Ej: Newsletter de bienvenida con logo, título y botón CTA"
          style="font-family: 'Public Sans', Arial, Helvetica, sans-serif; flex: 1; min-width: 200px; max-width: 500px; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; resize: vertical;"></textarea>
        <button type="button" id="generate-ai-btn"
          style="padding: 6px 16px; background: #28a745; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; font-family: 'Public Sans', Arial, Helvetica, sans-serif;">
          Generar con IA
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

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        var match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var generateAiBtn = document.getElementById('generate-ai-btn');
            if (generateAiBtn) {
                generateAiBtn.addEventListener('click', async function() {
                    var promptInput = document.getElementById('generate-ai-prompt');
                    var prompt = promptInput && promptInput.value ? promptInput.value.trim() : '';
                    if (!prompt) {
                        alert('Describe el template que quieres generar.');
                        return;
                    }
                    var btn = this;
                    var originalText = btn.textContent;
                    btn.disabled = true;
                    btn.textContent = 'Generando...';
                    try {
                        var response = await fetch('/template/generate-html', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': getCsrfToken(),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ prompt: prompt })
                        });
                        var rawText = await response.text();
                        var data = rawText ? (function(){ try { return JSON.parse(rawText); } catch(e) { return {}; } })() : {};
                        if (response.status !== 202 || !data.token) {
                            alert('Error al iniciar: ' + (data.error || 'Respuesta inesperada'));
                            return;
                        }
                        var token = data.token;
                        var pollUrl = '/template/generate-html/result/' + encodeURIComponent(token);
                        var maxWait = 120000;
                        var interval = 2000;
                        var start = Date.now();
                        var editor = getGrapesEditorInstance();
                        if (!editor) {
                            alert('No se encontró la instancia de GrapesJS');
                            return;
                        }
                        for (;;) {
                            var res = await fetch(pollUrl, { headers: { 'Accept': 'application/json' } });
                            var text = await res.text();
                            var result = text ? (function(){ try { return JSON.parse(text); } catch(e) { return null; } })() : null;
                            if (res.status === 404 || !result) {
                                alert('Error: token no válido o expirado.');
                                break;
                            }
                            if (result.status === 'completed' && result.html) {
                                editor.setComponents(result.html);
                                alert('HTML generado. Ahora puedes editar y guardar el template.');
                                break;
                            }
                            if (result.status === 'failed') {
                                alert('No se pudo generar el HTML: ' + (result.error || 'Error desconocido'));
                                break;
                            }
                            if (Date.now() - start >= maxWait) {
                                alert('Tiempo de espera agotado. Intenta de nuevo.');
                                break;
                            }
                            await new Promise(function(r) { setTimeout(r, interval); });
                        }
                    } catch (e) {
                        alert('Error al generar: ' + (e.message || 'Error de conexión'));
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
