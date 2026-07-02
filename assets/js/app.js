import '@/scss/app.scss'

// Symfony UX Stimulus
import '@/stimulus_bootstrap.js'

import $ from 'jquery'
import '@/js/vendors'
import '@/js/overrides'
import '@/js/utils/css'

import * as Sentry from '@sentry/browser'
// Per-element listeners (connected on every mount)
import contentRemovalRequest from '@/js/listeners/content-removal-request'
import dropzone from '@/js/listeners/dropzone'
import emailVerify from '@/js/listeners/email-verify'
import formCollection from '@/js/listeners/form-collection'
import formErrors from '@/js/listeners/form-errors'
import formTarget from '@/js/listeners/form-target'
import impersonate from '@/js/listeners/impersonate'
import like from '@/js/listeners/like'
import loadMore from '@/js/listeners/load-more'
import login from '@/js/listeners/login'
import popup from '@/js/listeners/popup'
import register from '@/js/listeners/register'
import tooltip from '@/js/listeners/tooltip'
// One-time modules (run once at boot)
import autocomplete from '@/js/modules/autocomplete'
import imagePreviews from '@/js/modules/image-previews'
import scrollToTop from '@/js/modules/scroll-to-top'
import registerServices from '@/js/services'
import Container from '@/js/services/Container'
import { findAllSelf } from '@/js/utils/dom'

/**
 * Application entry point.
 *
 * Owns the DI container, the listener registry, and the mount/unmount
 * lifecycle. Modules run once at boot; listeners are `{selector, connect}`
 * pairs connected per element by `mount(container)` and torn down by
 * `unmount(container)`.
 *
 * Bookkeeping is PER ELEMENT, not per container: `mount` skips elements that
 * are already connected (so overlapping containers can't double-init), and
 * `unmount(container)` tears down every connected element the container
 * covers, no matter which mount call connected it.
 */
class App {
    #di = null

    /** @type {Array<(ctx: { app: App }) => void>} */
    #modules = []

    /** @type {Array<{ selector: string, connect: (element: Element, ctx: { app: App }) => (() => void) | undefined }>} */
    #listeners = []

    /** @type {Map<Object, Set<Element>>} */
    #tracked = new Map()

    /** @type {WeakMap<Element, Map<Object, () => void>>} */
    #cleanups = new WeakMap()

    constructor() {
        this.#modules = [autocomplete, imagePreviews, scrollToTop]

        this.#listeners = [
            contentRemovalRequest,
            dropzone,
            emailVerify,
            formCollection,
            formErrors,
            formTarget,
            impersonate,
            like,
            loadMore,
            login,
            popup,
            register,
            tooltip,
        ]

        this.#listeners.forEach((listener) => this.#tracked.set(listener, new Set()))
    }

    handleError(error) {
        Sentry.captureException(error)
        throw error
    }

    /**
     * Boot the app. Initializes the DI container, runs every module exactly
     * once, then mounts the whole document.
     */
    start(parameters) {
        this.#di = new Container(parameters)

        if (parameters.dsn) {
            Sentry.init({
                dsn: parameters.dsn,
                release: parameters.release,
                environment: parameters.environment,
                sendDefaultPii: true,
            })

            Sentry.getCurrentScope().setUser(this.get('user'))
        }

        registerServices(this.#di)

        this.#modules.forEach((module) => module({ app: this }))

        this.mount(document)
    }

    /**
     * Connect every listener to its matching elements within `container`.
     * Elements already connected are skipped, so overlapping mounts are safe.
     *
     * @param {Element | Document} container
     */
    mount(container) {
        if (!this.#di) {
            // Not started yet: the boot-time mount(document) will cover this container
            return
        }

        this.#listeners.forEach((listener) => {
            const tracked = this.#tracked.get(listener)

            findAllSelf(listener.selector, container).forEach((element) => {
                if (tracked.has(element)) {
                    return
                }
                tracked.add(element)

                try {
                    const cleanup = listener.connect(element, { app: this })
                    if (typeof cleanup === 'function') {
                        this.#cleanupsFor(element).set(listener, cleanup)
                    }
                } catch (error) {
                    console.error(error)
                }
            })
        })

        if (typeof window.onPageLoaded === 'function') {
            window.onPageLoaded(this, container)
            window.onPageLoaded = null
        }
    }

    /**
     * Disconnect every connected element that `container` covers: the
     * container itself, its descendants, and — as a safety net — any element
     * that already left the DOM (removed by JS without an unmount call).
     *
     * @param {Element | Document} container
     */
    unmount(container) {
        this.#listeners.forEach((listener) => {
            this.#tracked.get(listener).forEach((element) => {
                if (element.isConnected && element !== container && !container.contains(element)) {
                    return
                }
                this.#disconnect(listener, element)
            })
        })
    }

    #disconnect(listener, element) {
        this.#tracked.get(listener).delete(element)

        const cleanups = this.#cleanups.get(element)
        const cleanup = cleanups?.get(listener)
        if (!cleanup) {
            return
        }

        cleanups.delete(listener)
        try {
            cleanup()
        } catch (error) {
            console.error(error)
        }
    }

    #cleanupsFor(element) {
        let cleanups = this.#cleanups.get(element)
        if (!cleanups) {
            cleanups = new Map()
            this.#cleanups.set(element, cleanups)
        }

        return cleanups
    }

    get(key) {
        return this.#di.get(key)
    }

    loadingButtons(container) {
        $('.btn-submit', container)
            .attr('disabled', true)
            .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>')
    }

    resetButtons(container) {
        $('.btn-submit', container).attr('disabled', false).find('.spinner-border').remove()
    }
}

window.App = new App()
