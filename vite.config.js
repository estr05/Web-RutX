import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    /*
     * Las fuentes Inter y JetBrains Mono viven en public/fonts/ como activos
     * públicos servidos directamente por el servidor web. Se desactiva la
     * resolución de URLs en CSS para que Vite no intente procesarlas en build
     * time y emita advertencias de referencias no resueltas.
     * Sin CDN, sin tipografías adicionales, sin modificar la identidad visual.
     */
    css: {
        url: false,
    },
});
