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
                    dest: '../assets'
                },
                {
                    src: 'resources/assets/js',
                    dest: '../assets'
                },
                {
                    src: 'resources/assets/css',
                    dest: '../assets'
                },
                // Copy FontAwesome from node_modules with specific structure
                {
                    src: 'node_modules/@fortawesome/fontawesome-free/css',
                    dest: '../assets/vendor/libs/fontawesome'
                },
                {
                    src: 'node_modules/@fortawesome/fontawesome-free/webfonts',
                    dest: '../assets/vendor/libs/fontawesome'
                },
                // Copy DataTables language files
                {
                    src: 'lang/datatables',
                    dest: '../js/datatables'
                },
                // Copy compiled JS libraries from node_modules (override ES6 module wrappers)
                {
                    src: 'node_modules/flatpickr/dist/flatpickr.js',
                    dest: '../assets/vendor/libs/flatpickr'
                },
                {
                    src: 'node_modules/bootstrap-select/js/bootstrap-select.js',
                    dest: '../assets/vendor/libs/bootstrap-select'
                },
                {
                    src: 'node_modules/bs-stepper/dist/js/bs-stepper.js',
                    dest: '../assets/vendor/libs/bs-stepper'
                },
                {
                    src: 'node_modules/bs-stepper/dist/js/bs-stepper.js.map',
                    dest: '../assets/vendor/libs/bs-stepper'
                },
                {
                    src: 'node_modules/swiper/swiper-bundle.js',
                    dest: '../assets/vendor/libs/swiper',
                    rename: 'swiper.js'
                },
                {
                    src: 'node_modules/swiper/swiper-bundle.js.map',
                    dest: '../assets/vendor/libs/swiper',
                    rename: 'swiper.js.map'
                },
                {
                    src: 'node_modules/swiper/swiper-bundle.js.map',
                    dest: '../assets/vendor/libs/swiper'
                },
                {
                    src: 'node_modules/@simonwep/pickr/dist/pickr.min.js',
                    dest: '../assets/vendor/libs/pickr',
                    rename: 'pickr.js'
                },
                {
                    src: 'node_modules/node-waves/dist/waves.js',
                    dest: '../assets/vendor/libs/node-waves',
                    rename: 'node-waves.js'
                },
                {
                    src: 'node_modules/@popperjs/core/dist/umd/popper.min.js',
                    dest: '../assets/vendor/libs/popper',
                    rename: 'popper.js'
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
