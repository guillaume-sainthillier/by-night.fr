/**
 * Shared JSDoc typedefs for the App lifecycle.
 *
 * No runtime exports — this file exists so IDEs and JSDoc tooling resolve
 * `Listener`, `Module`, `Page`, `Cleanup` etc. globally.
 *
 * @module types
 */

/**
 * Cleanup callback returned by a listener `connect` or a page initializer.
 * Run when the element (or the page's marker element) is unmounted.
 *
 * @typedef {() => void} Cleanup
 */

/**
 * Context passed to a listener's `connect` alongside the matched element.
 *
 * @typedef {Object} ConnectContext
 * @property {App} app
 */

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
 * @typedef {Object} Listener
 * @property {string} selector
 * @property {(element: Element, ctx: ConnectContext) => Cleanup | void} connect
 */

/**
 * Context passed to one-time modules during `App.start()`.
 *
 * @typedef {Object} ModuleContext
 * @property {App} app
 */

/**
 * One-time module. Runs once when `App.start()` is called; binds to
 * `document`/`window` — never to elements inside `body`. Return value is
 * ignored — modules are forever-mounted.
 *
 * @typedef {(ctx: ModuleContext) => void | Promise<void>} Module
 */

/**
 * Base context passed to a registered page initializer. The params object from
 * `App.loadPage()` is spread alongside `app` and `container`.
 *
 * Reserved keys: `app`, `container` — page params must not shadow them.
 *
 * @typedef {Object} PageContextBase
 * @property {App} app
 * @property {Element} container
 */

/**
 * Page initializer. Receives `{ app, container, ...params }`.
 *
 * @typedef {(ctx: PageContextBase & Object<string, *>) => Cleanup | void | Promise<Cleanup | void>} Page
 */
