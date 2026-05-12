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

        .gjs-pn-panel.gjs-pn-options .gjs-pn-buttons {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.25rem;
        }

        .gjs-pn-panel.gjs-pn-options .gjs-pn-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            width: auto;
            min-height: 30px;
            padding: 0 0.5rem;
        }

        .gjs-pn-panel.gjs-pn-options .gjs-pn-btn .gjs-pn-btn-text {
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1;
            white-space: nowrap;
        }
    </style>
    <script>
        window.grapesJsToolbarLabels = {
            save: @json(__('app.grapesjs_toolbar_save')),
            back: @json(__('app.grapesjs_toolbar_back')),
        };

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
    <div id="{{ str_replace('#', '', $editorConfig->container ?? 'editor') }}"></div>

    @foreach ($editorConfig->getScripts() as $script)
        <script src="{{ $script }}"></script>
    @endforeach
    <script>
        (function ()
        {
            function enhanceHumanoGrapesToolbar(editor)
            {
                if (! editor || editor.get('_humanoToolbarEnhanced'))
                {
                    return;
                }

                var labels = window.grapesJsToolbarLabels || { save: 'Save', back: 'Back' };

                try
                {
                    var cancel = editor.Panels.getButton('options', 'cancel');
                    var save = editor.Panels.getButton('options', 'save');

                    if (! cancel || ! save)
                    {
                        return;
                    }

                    var cancelView = cancel.get('view');
                    var saveView = save.get('view');
                    var cancelEl = cancelView && cancelView.el;
                    var saveEl = saveView && saveView.el;

                    if (! cancelEl || ! saveEl)
                    {
                        return;
                    }

                    if (! cancelEl.querySelector('.gjs-pn-btn-text'))
                    {
                        var cancelLabel = document.createElement('span');
                        cancelLabel.className = 'gjs-pn-btn-text';
                        cancelLabel.textContent = labels.back;
                        cancelEl.appendChild(cancelLabel);
                    }

                    if (! saveEl.querySelector('.gjs-pn-btn-text'))
                    {
                        var saveLabel = document.createElement('span');
                        saveLabel.className = 'gjs-pn-btn-text';
                        saveLabel.textContent = labels.save;
                        saveEl.appendChild(saveLabel);
                    }

                    var parent = cancelEl.parentElement;
                    if (parent && saveEl.parentElement === parent)
                    {
                        parent.appendChild(cancelEl);
                        parent.appendChild(saveEl);
                    }

                    editor.set('_humanoToolbarEnhanced', true);
                }
                catch (e)
                {
                    console.error(e);
                }
            }

            function runToolbarEnhancement()
            {
                var editor = window.gjsEditor;
                if (! editor)
                {
                    return false;
                }

                enhanceHumanoGrapesToolbar(editor);

                if (! editor.get('_humanoToolbarRetryScheduled'))
                {
                    editor.set('_humanoToolbarRetryScheduled', true);
                    [120, 350, 700].forEach(function (delay)
                    {
                        setTimeout(function ()
                        {
                            editor.unset('_humanoToolbarEnhanced');
                            enhanceHumanoGrapesToolbar(editor);
                        }, delay);
                    });
                }

                return editor.get('_humanoToolbarEnhanced') === true;
            }

            var toolbarAttempts = 0;
            var toolbarTimer = setInterval(function ()
            {
                toolbarAttempts++;
                if (runToolbarEnhancement() || toolbarAttempts > 400)
                {
                    clearInterval(toolbarTimer);
                }
            }, 50);
        })();
    </script>
    <script>
        (function ()
        {
            function bindDefaultBlocksPanel(editor)
            {
                if (! editor || editor.get('_humanoOpenBlocksHook'))
                {
                    return;
                }

                editor.set('_humanoOpenBlocksHook', true);

                var ran = false;

                function runOpenBlocksOnce()
                {
                    if (ran)
                    {
                        return;
                    }

                    try
                    {
                        editor.runCommand('open-blocks');
                        ran = true;
                    }
                    catch (e)
                    {
                        console.error(e);
                    }
                }

                editor.on('load', function ()
                {
                    setTimeout(runOpenBlocksOnce, 100);
                });

                setTimeout(runOpenBlocksOnce, 450);
                setTimeout(runOpenBlocksOnce, 900);
            }

            var blocksAttempts = 0;
            var blocksTimer = setInterval(function ()
            {
                blocksAttempts++;
                var editor = window.gjsEditor;
                if (editor)
                {
                    bindDefaultBlocksPanel(editor);
                }
                if (blocksAttempts > 400)
                {
                    clearInterval(blocksTimer);
                }
            }, 50);
        })();
    </script>
    @if (isset($returnUrl) && filled($returnUrl))
        <script>
            (function ()
            {
                var returnUrl = @json($returnUrl);

                function goReturn()
                {
                    window.location.href = returnUrl;
                }

                function patchEditor()
                {
                    var editor = window.gjsEditor;
                    if (! editor)
                    {
                        return false;
                    }

                    try
                    {
                        var cancel = editor.Panels.getButton('options', 'cancel');
                        if (cancel)
                        {
                            cancel.set('command', function ()
                            {
                                goReturn();
                            });
                        }

                        var save = editor.Panels.getButton('options', 'save');
                        if (save)
                        {
                            save.set('command', function (ed)
                            {
                                ed.store(function (err)
                                {
                                    if (err)
                                    {
                                        return;
                                    }
                                    goReturn();
                                });
                            });
                        }
                    }
                    catch (e)
                    {
                        console.error(e);
                    }

                    return true;
                }

                var attempts = 0;
                var timer = setInterval(function ()
                {
                    attempts++;
                    if (patchEditor() || attempts > 300)
                    {
                        clearInterval(timer);
                    }
                }, 50);
            })();
        </script>
    @endif
</body>
</html>
