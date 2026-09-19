import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(() => {
    const publicPort = Number(process.env.VITE_DEV_SERVER_PORT ?? 5174);
    const appPort = Number(process.env.VITE_APP_PORT ?? 8010);
    const acceptanceAppPort = Number(process.env.VITE_ACCEPTANCE_APP_PORT ?? 8011);

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ],
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            origin: `http://127.0.0.1:${publicPort}`,
            hmr: {
                host: '127.0.0.1',
                clientPort: publicPort,
            },
            cors: {
                origin: [
                    `http://127.0.0.1:${appPort}`,
                    `http://localhost:${appPort}`,
                    `http://127.0.0.1:${acceptanceAppPort}`,
                    `http://localhost:${acceptanceAppPort}`,
                ],
            },
            watch: {
                usePolling: true,
                interval: 500,
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
