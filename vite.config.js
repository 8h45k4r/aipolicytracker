import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.jsx', 'resources/css/public.css', 'resources/css/admin.css', 'resources/js/public.js', 'resources/js/admin.js'],
            refresh: true,
        }),
        react(),
    ],
    // Writes public/build/.vite/license.md: the licence of every package that ends
    // up in the committed bundle, which the minified files no longer carry.
    build: {
        license: true,
    },
});


