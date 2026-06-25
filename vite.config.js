import { defineConfig } from 'vite';
import { readFileSync, writeFileSync } from 'fs';
import autoprefixer from 'autoprefixer';
import { browserslistToTargets } from 'lightningcss';
import browserslist from 'browserslist';

/**
 * Wraps the built JS in an IIFE so top-level code
 * (Alpine.data, document.addEventListener) never
 * leaks into the global scope.
 */
const iifeWrapPlugin = (filename) => ({
    name: 'iife-wrap',
    async closeBundle() {
        try {
            const filePath = `dist/${filename}`;
            const data = readFileSync(filePath);

            writeFileSync(filePath, Buffer.from('(()=>{'));
            writeFileSync(filePath, data, { flag: 'a' });
            writeFileSync(filePath, Buffer.from('})()'), { flag: 'a' });
        } catch (e) {
            console.error(e);
        }
    },
});

export default defineConfig({
    build: {
        emptyOutDir: true,
        outDir: 'dist',
        rollupOptions: {
            input: 'resources/js/field.js',
            output: {
                entryFileNames: '[name].js',
                assetFileNames: '[name][extname]',
            },
            plugins: [iifeWrapPlugin('field.js')],
        },
        cssMinify: 'lightningcss',
        minify: true,
        target: 'es2017',
    },
    css: {
        lightningcss: {
            targets: browserslistToTargets(
                browserslist(['> 0.5%', 'last 2 versions', 'Firefox ESR', 'not dead'])
            ),
        },
        postcss: {
            plugins: [autoprefixer()],
        },
    },
});
