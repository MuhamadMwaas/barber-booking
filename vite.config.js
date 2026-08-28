import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Admin / app bundle.
                'resources/css/app.css',
                'resources/js/app.js',
                // Public marketing site (landing page + app download page).
                // Kept as its own entry so the landing never downloads the admin
                // stylesheet, and vice versa.
                'resources/css/landing.css',
                'resources/js/landing.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
