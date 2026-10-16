import { defineConfig } from 'vite';

export default defineConfig({
    base: './',
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        cssCodeSplit: false,
        target: 'esnext',
        rollupOptions: {
            input: 'src/main.js',
            output: {
                entryFileNames: 'monaco.js',
                chunkFileNames: '[name]-[hash].js',
                assetFileNames: '[name]-[hash][extname]',
            },
        },
    },
});
