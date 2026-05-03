import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [react()],
    root: path.resolve(__dirname, 'app/dashboard/resources'),
    base: '/',
    build: {
        outDir: path.resolve(__dirname, 'public/build'),
        manifest: true,
        emptyOutDir: true,
        rollupOptions: {
            input: {
                app: path.resolve(__dirname, 'app/dashboard/resources/js/app.jsx'),
            },
        },
    },
    server: {
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173',
    },
});
