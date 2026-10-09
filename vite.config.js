import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // Separate builds: storefront CSS must not carry admin classes.
            input: ['resources/css/storefront.css', 'resources/js/storefront.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
            fonts: [
                // Two families, four weights. Nothing more. See §12.2.1.
                bunny('Fraunces', { weights: [400, 600] }),
                bunny('Inter', { weights: [400, 500] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
