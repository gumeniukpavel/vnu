import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: ['resources/js/app.js', 'resources/css/app.css'],
            refresh: true,
        }),
    ],
    server: {
        host: true,         // потрібно в Docker
        port: 5173,         // у Sail вже проброшено 5173
        hmr: { host: 'localhost' },
    },
})
