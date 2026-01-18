import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Main application JS files
                'resources/js/laravel-user-management.js',
                'resources/js/openai.js',
                'resources/js/helpdesk-chat.js',
            ],
            refresh: true,
        }),
        // Copy vendor assets as static files (they're already compiled)
        viteStaticCopy({
            targets: [
                {
                    src: 'resources/assets/vendor',
                    dest: 'assets'
                },
                {
                    src: 'resources/assets/js',
                    dest: 'assets'
                },
                {
                    src: 'resources/assets/css',
                    dest: 'assets'
                },
                {
                    src: 'lang/datatables',
                    dest: 'js'
                }
            ]
        })
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources'),
            '~': resolve(__dirname, 'node_modules'),
        },
    },
    build: {
        manifest: 'manifest.json',
        outDir: 'public/build',
        rollupOptions: {
            output: {
                manualChunks: undefined,
            }
        }
    },
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
    },
});
