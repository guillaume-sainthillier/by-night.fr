import { createRouter } from '@symfony/ux-router'
import { defaultLocale, routes } from '@/js/routes'

/**
 * The URLs of the routes the scripts call, as `path()` gives them in Twig (the router's `url()` too, if a script needs
 * one): exposed by their `expose` option, or listed in `config/packages/ux_router.yaml`, and dumped into
 * `assets/js/routes` on every cache rebuild (or with `bin/console ux:router:warm-cache`). Route names and parameters
 * are type-checked.
 */
export const { path } = createRouter({
    routes,
    defaultLocale,
    // No localized route: the locale is the app's, never read from the page
    context: { parameters: { _locale: defaultLocale } },
})
