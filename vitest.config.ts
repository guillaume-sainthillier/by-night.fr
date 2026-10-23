import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

// Kept separate from vite.config.ts so test runs don't load the @symfony/reprise plugin,
// which writes entrypoints.json and manifest.json into public/build/.
export default defineConfig({
    resolve: {
        // Same "@" alias as vite.config.ts
        alias: {
            '@': fileURLToPath(new URL('./assets', import.meta.url)),
        },
    },

    test: {
        environment: 'node',
        include: ['assets/**/*.{test,spec}.{ts,tsx}'],
        coverage: {
            provider: 'v8',
            include: ['assets/**/*.{ts,tsx}'],
            exclude: ['assets/**/*.{test,spec}.{ts,tsx}'],
            reportsDirectory: 'coverage',
        },
    },
})
