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

        /* Email template fonts - ensure they're available in the editor */
        body, .gjs-frame body {
            font-family: helvetica, arial, verdana, sans-serif !important;
        }

        /* Override GrapesJS default fonts for email templates */
        .gjs-frame * {
            font-family: inherit !important;
        }

        .gjs-frame h1, .gjs-frame h2, .gjs-frame h3, .gjs-frame h4, .gjs-frame h5, .gjs-frame h6, .gjs-frame strong {
            font-weight: 600 !important;
        }

        .gjs-frame p, .gjs-frame span, .gjs-frame a, .gjs-frame td {
            font-size: 14px !important;
            font-weight: 300 !important;
            color: #777777 !important;
        }

        .gjs-frame a {
            text-decoration: none !important;
        }

        .gjs-frame a:hover {
            text-decoration: underline !important;
        }

        /* Footer specific styles - High priority to prevent GrapesJS override */
        .gjs-frame table[bgcolor="#2A333D"] {
            background-color: #2A333D !important;
        }

        .gjs-frame table[bgcolor="#2A333D"] * {
            color: #ffffff !important;
        }

        .gjs-frame table[bgcolor="#2A333D"] span {
            color: #ffffff !important;
        }

        .gjs-frame table[bgcolor="#2A333D"] strong {
            color: #ffffff !important;
        }

        .gjs-frame table[bgcolor="#2A333D"] a {
            color: #ffffff !important;
            text-decoration: none !important;
        }

        .gjs-frame table[bgcolor="#2A333D"] a:hover {
            color: #ffffff !important;
            text-decoration: underline !important;
        }

        /* Force styles even after GrapesJS loads */
        .gjs-frame table[bgcolor="#2A333D"] td,
        .gjs-frame table[bgcolor="#2A333D"] td * {
            color: #ffffff !important;
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
        // Function to apply persistent styles
        function applyPersistentStyles() {
            const editor = getGrapesEditorInstance();
            if (editor && editor.Canvas) {
                const canvasDoc = editor.Canvas.getDocument();
                if (canvasDoc) {
                    // Remove existing style if present
                    const existingStyle = canvasDoc.getElementById('persistent-email-styles');
                    if (existingStyle) {
                        existingStyle.remove();
                    }

                    const style = canvasDoc.createElement('style');
                    style.id = 'persistent-email-styles';
                    style.textContent = `
                        * {
                            font-family: helvetica, arial, verdana, sans-serif !important;
                        }
                        body {
                            font-family: helvetica, arial, verdana, sans-serif !important;
                        }
                        h1, h2, h3, h4, h5, h6, strong {
                            font-weight: 600 !important;
                        }
                        p, span, a, td {
                            font-size: 14px !important;
                            font-weight: 300 !important;
                            color: #777777 !important;
                        }
                        a {
                            text-decoration: none !important;
                        }
                        a:hover {
                            text-decoration: underline !important;
                        }

                        /* Footer styles - Maximum priority */
                        table[bgcolor="#2A333D"] {
                            background-color: #2A333D !important;
                        }
                        table[bgcolor="#2A333D"] *,
                        table[bgcolor="#2A333D"] span,
                        table[bgcolor="#2A333D"] strong,
                        table[bgcolor="#2A333D"] td,
                        table[bgcolor="#2A333D"] td * {
                            color: #ffffff !important;
                        }
                        table[bgcolor="#2A333D"] a {
                            color: #ffffff !important;
                            text-decoration: none !important;
                        }
                        table[bgcolor="#2A333D"] a:hover {
                            color: #ffffff !important;
                            text-decoration: underline !important;
                        }
                    `;
                    canvasDoc.head.appendChild(style);
                    console.log('Persistent email template styles applied to GrapesJS canvas');
                    return true;
                }
            }
            return false;
        }

        // Apply styles initially
        setTimeout(applyPersistentStyles, 2000);

        // Re-apply styles periodically to prevent GrapesJS from overriding them
        setInterval(function() {
            const editor = getGrapesEditorInstance();
            if (editor && editor.Canvas) {
                applyPersistentStyles();
            }
        }, 5000); // Re-apply every 5 seconds

        // Apply styles on editor events
        setTimeout(function() {
            const editor = getGrapesEditorInstance();
            if (editor) {
                editor.on('load', applyPersistentStyles);
                editor.on('component:update', applyPersistentStyles);
                editor.on('style:update', applyPersistentStyles);
            }
        }, 3000);

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
