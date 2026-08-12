import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const devServerHost = process.env.VITE_DEV_SERVER_HOST || '127.0.0.1';
const devServerPort = Number(process.env.VITE_PORT || 5173);
const devServerUrl = (process.env.VITE_DEV_SERVER_URL || '').replace(/\/$/, '');
const devServerOrigin = devServerUrl || undefined;
const devServerHmr = devServerUrl
    ? (() => {
        const url = new URL(devServerUrl);
        return {
            protocol: url.protocol === 'https:' ? 'wss' : 'ws',
            host: url.hostname,
            clientPort: url.port ? Number(url.port) : (url.protocol === 'https:' ? 443 : 80),
        };
    })()
    : undefined;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: devServerHost,
        port: devServerPort,
        origin: devServerOrigin,
        hmr: devServerHmr,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
