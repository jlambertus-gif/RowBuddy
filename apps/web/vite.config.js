import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],

    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,

        hmr: {
            host: 'localhost',
            port: 5173,
            clientPort: 5173,
        },

        watch: {
            // Windows-host bind mount into a Linux container: native
            // filesystem change events (inotify) don't propagate across
            // that boundary, so chokidar's default watcher silently never
            // fires — Vite serves stale transforms indefinitely (new
            // files never appear in import.meta.glob results, edited
            // files never get HMR'd) until the dev server process is
            // restarted. Polling works regardless of how the mount
            // delivers (or fails to deliver) native FS events.
            usePolling: true,
            interval: 300,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});