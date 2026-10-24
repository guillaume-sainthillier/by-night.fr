import type { App, Cleanup, LazyListener, MaybeCleanup } from '@silarhi/app-js'
import { fake } from '@/js/test/fake'

/** Connects as `app.mount()` would; a synchronous test cannot wait for an async connect() */
const connectNow = <E extends Element>(listener: LazyListener<E>, element: E, app: App): MaybeCleanup => {
    const cleanup = listener.connect(element, { app })

    if (cleanup instanceof Promise) {
        throw new TypeError('The listener connects asynchronously: await its connect() in the test instead')
    }

    return cleanup
}

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
    const cleanup = connectNow(listener, element, app)

    if (typeof cleanup !== 'function') {
        throw new TypeError('The listener returned no cleanup')
    }

    return cleanup
}

/**
 * Connects a listener that leaves nothing behind on this element (a one-off change, or nothing to do: a text that
 * already fits), as `app.mount()` would. It throws when the listener returns a cleanup after all, which the test should
 * then call through `connectListener()`, and when connect() is async.
 */
export const connectListenerWithoutCleanup = <E extends Element>(
    listener: LazyListener<E>,
    element: E,
    app: App = fake<App>({})
): void => {
    if (typeof connectNow(listener, element, app) === 'function') {
        throw new TypeError('The listener returned a cleanup: connect it with connectListener() and call it')
    }
}
