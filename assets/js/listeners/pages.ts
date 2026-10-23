import type { Cleanup, Listener } from '@/js/types'

/**
 * Invoke the page initializers declared by a `<div data-page="…">` marker
 * (emitted by the `load_page()` Twig function) and collect their cleanups.
 *
 * Pages load lazily — `app.loadPage()` returns a promise that may resolve
 * after the element has been disconnected. The `disconnected` flag handles
 * that race by running late-arriving cleanups immediately.
 */
export default {
    selector: '[data-page]',
    connect(element, { app }) {
        const cleanups: Cleanup[] = []
        let disconnected = false

        const ids: string[] = JSON.parse(element.dataset.page ?? '[]')
        const params: Record<string, unknown> = JSON.parse(element.dataset.pageParams || '{}')

        ids.forEach((id) => {
            app.loadPage(id, params, element).then((cleanup) => {
                if (typeof cleanup !== 'function') return
                if (disconnected) cleanup()
                else cleanups.push(cleanup)
            })
        })

        return () => {
            disconnected = true
            cleanups.forEach((cleanup) => cleanup())
        }
    },
} satisfies Listener
