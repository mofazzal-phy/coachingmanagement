import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Increase max listeners to suppress MaxListenersExceededWarning
// from Vite's internal stream handling (resize events on WriteStream)
process.stdout.setMaxListeners(0);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
