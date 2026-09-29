import { fileURLToPath } from 'node:url'
import Symfony from '@symfony/reprise/vite'
import prefixCustomProperties from 'postcss-prefix-custom-properties'
import { defineConfig } from 'vite'

const assets = fileURLToPath(new URL('./assets', import.meta.url))
const scss = fileURLToPath(new URL('./assets/scss/', import.meta.url))
const moment = fileURLToPath(new URL('./node_modules/moment/moment.js', import.meta.url))

// Tabler's Sass sources write custom properties bare (--primary, var(--btn-bg)), and so do ours: its own build adds
// the prefix afterwards, with this plugin. We keep Bootstrap's --bs-, which the Bootstrap themes of Tom Select and
// summernote read. Only the stylesheets of assets/scss are rewritten: the vendor CSS the JS imports on its own
// (Algolia autocomplete, fancybox…) reads its variables by their own name.
const prefixScssCustomProperties = () => {
    const plugin = prefixCustomProperties({
        prefix: 'bs-',
        ignore: [
            /^--bs-/, // already prefixed
            /^--ts-/, // Tom Select
            /^--aa-/, // Algolia autocomplete, themed from components/_autocomplete.scss
        ],
    })

    return {
        postcssPlugin: 'prefix-scss-custom-properties',
        Once(root, helpers) {
            if (root.source?.input.file?.startsWith(scss)) {
                plugin.Once(root, helpers)
            }
        },
    }
}
prefixScssCustomProperties.postcss = true

const pages = [
    'index',
    'admin_infos',
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
                    // Country flags of the portals (components/Flag.html.twig): emoji flags render as two
                    // letters on Windows
                    {
                        from: 'node_modules/@tabler/core/dist/img/flags',
                        to: 'images/flags',
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

        css: {
            postcss: {
                plugins: [prefixScssCustomProperties()],
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
            // Tabler's browser baseline (1.5+ relies on light-dark(), color-mix() and :has() with no fallback). Below it,
            // Lightning CSS polyfills light-dark() with variables resolved once on :root, so the dark islands
            // (data-bs-theme="dark" inside a light page) would inherit the light colours.
            cssTarget: ['chrome123', 'edge123', 'firefox128', 'safari17.5'],

            // Encore's addEntry() equivalent. Reprise turns each key into an
            // entrypoints.json entry consumed by reprise_entry_*_tags().
            rollupOptions: {
                input: {
                    app: `${assets}/js/app.js`,
                    // EasyAdmin back office, see DashboardController::configureAssets()
                    admin: `${assets}/js/admin.js`,
                    ...Object.fromEntries(pages.map((page) => [page, `${assets}/js/pages/${page}.js`])),
                },
                // Rolldown does not keep side-effect import order across chunks by default;
                // jquery-global.js must run before Bootstrap and the jQuery plugins evaluate.
                output: { strictExecutionOrder: true },
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
