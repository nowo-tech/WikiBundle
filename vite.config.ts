import { defineConfig } from 'vite';

/**
 * Vite IIFE build for wiki UI helpers. Output: `src/Resources/public/wiki.js` for `assets:install`.
 * Styles live in `wiki.css` and are loaded via Twig `<link>` (not bundled as ESM import).
 */
export default defineConfig({
    build: {
        outDir: 'src/Resources/public',
        emptyOutDir: false,
        rollupOptions: {
            input: 'src/Resources/assets/src/wiki.ts',
            output: {
                format: 'iife',
                name: 'NowoWiki',
                entryFileNames: 'wiki.js',
                assetFileNames: 'wiki.[ext]',
            },
        },
        minify: true,
        sourcemap: false,
    },
});
