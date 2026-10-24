import type { App, Cleanup, LazyListener } from '@silarhi/app-js'
import { fake } from '@/js/test/fake'

/**
 * Connects a listener to a double of its element, as `app.mount()` would, and returns its cleanup for the test to call.
 * `defineListener()` gives every listener the package's signature, a context to pass and a cleanup that may be missing
 * or a promise: this is the one place where a test turns that back into the cleanup it expects. It throws when there is
 * none, so a listener that stops cleaning up fails its test, and when connect() is async, which a synchronous test
 * cannot wait for. Without `app`, a listener that reads a service fails on the empty double.
 */
export const connectListener = <E extends Element>(
    listener: LazyListener<E>,
    element: E,
    app: App = fake<App>({})
): Cleanup => {
    const cleanup = listener.connect(element, { app })

    if (cleanup instanceof Promise) {
        throw new TypeError('The listener connects asynchronously: await its connect() in the test instead')
    }
    if (typeof cleanup !== 'function') {
        throw new TypeError('The listener returned no cleanup')
    }

    return cleanup
}
