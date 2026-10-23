/**
 * Shared types for the App lifecycle: `Listener`, `Module`, `Page`, `Cleanup`.
 */
import type { App } from '@/js/app'

/**
 * Cleanup callback returned by a listener `connect` or a page initializer.
 * Run when the element (or the page's marker element) is unmounted.
 */
export type Cleanup = () => void

/**
 * Context passed to a listener's `connect` alongside the matched element.
 */
interface ConnectContext {
    app: App
}

/**
 * Per-element listener. `App.mount(container)` calls `connect` once per
 * element matching `selector` inside `container` (idempotent — an element
 * already connected is skipped). The returned cleanup runs when the element
 * is unmounted: when an `App.unmount(container)` covers it, or when it has
 * left the DOM by the time any unmount runs.
 *
 * `selector` must be decidable at mount time (avoid matching on mutable
 * state that JS toggles later — the element is only scanned when a container
 * mounts: the boot-time `mount(document)`, or a manual `mount(container)`
 * after an AJAX insert).
 *
 * `E` is the element type the selector guarantees (`'form.foo'` → `HTMLFormElement`).
 * `connect` is declared as a method so that a `Listener<HTMLFormElement>` can sit
 * in the app's `Listener[]`: the selector, not the type system, decides the element.
 */
export interface Listener<E extends Element = HTMLElement> {
    selector: string
    connect(element: E, ctx: ConnectContext): Cleanup | undefined
}

/**
 * Context passed to one-time modules during `App.start()`.
 */
interface ModuleContext {
    app: App
}

/**
 * One-time module. Runs once when `App.start()` is called; binds to
 * `document`/`window` — never to elements inside `body`. Return value is
 * ignored — modules are forever-mounted.
 */
export type Module = (ctx: ModuleContext) => void | Promise<void>

/**
 * Base context passed to a registered page initializer. The params object from
 * `App.loadPage()` is spread alongside `app` and `container`.
 *
 * Reserved keys: `app`, `container` — page params must not shadow them.
 */
export interface PageContext {
    app: App
    container: HTMLElement
}

/**
 * Page initializer. Receives `{ app, container, ...params }`, `P` being the
 * params the template passes to `load_page()`.
 */
export type Page<P extends object = Record<string, never>> = (
    ctx: PageContext & P
) => Cleanup | undefined | Promise<Cleanup | undefined>
