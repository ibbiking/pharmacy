import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.tsx'], refresh: true }),
        react(),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    build: {
        chunkSizeWarningLimit: 1200, // echarts is large; it is split into its own chunk below
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/echarts') || id.includes('node_modules/zrender')) return 'charts';
                    if (id.includes('node_modules/react-select')) return 'select';
                },
            },
        },
    },
});
