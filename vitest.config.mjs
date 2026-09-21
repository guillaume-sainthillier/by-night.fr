import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

// Kept separate from vite.config.mjs so test runs don't load the @symfony/reprise plugin,
// which writes entrypoints.json and manifest.json into public/build/.
export default defineConfig({
    resolve: {
        // Same "@" alias as vite.config.mjs. Its moment alias isn't needed here: tests load
        // dependencies through Node, which resolves moment's `main` for every importer.
        alias: {
            '@': fileURLToPath(new URL('./assets', import.meta.url)),
        },
    },

    test: {
        environment: 'node',
        include: ['assets/**/*.{test,spec}.{js,jsx}'],
        coverage: {
            provider: 'v8',
            include: ['assets/**/*.{js,jsx}'],
            exclude: ['assets/**/*.{test,spec}.{js,jsx}'],
            reportsDirectory: 'coverage',
        },
    },
})
