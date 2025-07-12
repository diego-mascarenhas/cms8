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
    <div style="margin-bottom:10px;">
      <input type="text" id="import-url-input" placeholder="Pega la URL para importar HTML" style="width:300px;"/>
      <button id="import-url-btn">Importar HTML</button>
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
