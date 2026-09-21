import { fileURLToPath } from 'node:url'
import Symfony from '@symfony/reprise/vite'
import { defineConfig } from 'vite'

const assets = fileURLToPath(new URL('./assets', import.meta.url))
const moment = fileURLToPath(new URL('./node_modules/moment/moment.js', import.meta.url))

const pages = [
    'index',
    'admin_infos',
    'event_index',
    'event_details',
    'agenda',
    'profile',
    'user',
    'personal_space_list',
    'personal_space_event',
]

export default defineConfig(({ mode }) => {
    const isDev = mode === 'development'

    return {
        plugins: [
            Symfony({
                // Registers controllers.json + assets/controllers/ behind "virtual:symfony/controllers"
                stimulus: 'assets/controllers.json',
                // Replaces Encore's copyFiles(): files land in public/build/images/ and are
                // registered in manifest.json, so asset('build/images/…') keeps resolving.
                copy: [
                    {
                        from: 'assets/images',
                        to: 'images',
                        // Skip any path with a dot-prefixed segment (macOS .DS_Store files) and the
                        // sites/originals/ sources: only the resized sites/<slug>.jpg are referenced
                        // (templates/location/index.html.twig), the originals would add 40 MB to the build.
                        pattern: /^(?!(?:.*\/)?\.)(?!sites\/originals\/)/,
                    },
                ],
            }),
        ],

        // JSX is compiled for Preact with the automatic runtime, as the Babel config did.
        // The generated icons in assets/js/icons rely on it: they use JSX without importing h.
        oxc: {
            jsx: {
                runtime: 'automatic',
                importSource: 'preact',
            },
        },

        resolve: {
            alias: [
                { find: '@', replacement: assets },
                // Exact match only, like Encore's `moment$`. Vite prefers moment's ESM build
                // (jsnext:main), but moment/locale/fr and daterangepicker require() the UMD
                // build: without this they would get a second moment instance, and the French
                // locale would be registered on the one the app does not use.
                { find: /^moment$/, replacement: moment },
            ],
        },

        build: {
            // Encore's addEntry() equivalent. Reprise turns each key into an
            // entrypoints.json entry consumed by reprise_entry_*_tags().
            rollupOptions: {
                input: {
                    app: `${assets}/js/app.js`,
                    // EasyAdmin back office, see DashboardController::configureAssets()
                    admin: `${assets}/js/admin.js`,
                    ...Object.fromEntries(pages.map((page) => [page, `${assets}/js/pages/${page}.js`])),
                },
            },

            // Dev build profile, used by `yarn dev` and `yarn watch`.
            // Vite minifies and omits sourcemaps in build mode whatever the --mode,
            // so restore Encore's enableSourceMaps(!isProduction) behaviour explicitly.
            ...(isDev && {
                sourcemap: true,
                minify: false,
            }),
        },
    }
})
