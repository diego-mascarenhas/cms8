<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="">
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
    <div class="d-flex align-items-center gap-2 mb-3" style="padding: 6px 32px; background: #f8f9fa; border-radius: 8px;">
      <span style="font-size: 14px; color: #444; font-family: 'Public Sans', Arial, Helvetica, sans-serif; font-weight: 400; min-width: 260px; margin-right: 18px;">¿Tienes tu diseño publicado en la web?</span>
      <input type="text" id="import-url-input" placeholder="Pega la URL para importar HTML"
        style="font-family: 'Public Sans', Arial, Helvetica, sans-serif; max-width: 600px; width: 100%; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
      <button id="import-url-btn"
        style="padding: 6px 16px; background: #7367f0; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; font-family: 'Public Sans', Arial, Helvetica, sans-serif;">
        Importar HTML
      </button>
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

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            document.getElementById('import-url-btn').addEventListener('click', async function() {
                const url = document.getElementById('import-url-input').value;
                if (!url) return alert('Ingresa una URL');
                try {
                    const response = await fetch('/api/fetch-html?url=' + encodeURIComponent(url));
                    const data = await response.json();
                    const editor = getGrapesEditorInstance();
                    if (!editor) return alert('No se encontró la instancia de GrapesJS');
                    if (data.html) {
                        editor.setComponents(data.html);
                        alert('HTML importado. Ahora puedes editar y guardar el template.');
                    } else {
                        alert('No se pudo importar el HTML: ' + (data.error || 'Error desconocido'));
                    }
                } catch (e) {
                    alert('Error al importar: ' + e.message);
                }
            });
        }, 1500); // Espera 1.5s para asegurar que el editor esté inicializado
    });
    </script>
</body>
</html>
