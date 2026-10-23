import '@/scss/app.scss'

// Symfony UX Stimulus
import '@/stimulus_bootstrap'

import $ from 'jquery'
import '@/js/vendors'
import '@/js/overrides'
import '@/js/utils/css'

import * as Sentry from '@sentry/browser'
// Per-element listeners (connected on every mount)
import bulkActions from '@/js/listeners/bulk-actions'
import citySwitcher from '@/js/listeners/city-switcher'
import contentRemovalRequest from '@/js/listeners/content-removal-request'
import cookieSettings from '@/js/listeners/cookie-settings'
import dropzone from '@/js/listeners/dropzone'
import duplicateCheck from '@/js/listeners/duplicate-check'
import emailVerify from '@/js/listeners/email-verify'
import eventPublish from '@/js/listeners/event-publish'
import facebookPage from '@/js/listeners/facebook-page'
import feedback from '@/js/listeners/feedback'
import fileMaxSize from '@/js/listeners/file-max-size'
import filteredLink from '@/js/listeners/filtered-link'
import formCollection from '@/js/listeners/form-collection'
import formConfirm from '@/js/listeners/form-confirm'
import formErrors from '@/js/listeners/form-errors'
import formTarget from '@/js/listeners/form-target'
import imagePreviews from '@/js/listeners/image-previews'
import impersonate from '@/js/listeners/impersonate'
import like from '@/js/listeners/like'
import loadMore from '@/js/listeners/load-more'
import pages from '@/js/listeners/pages'
import passwordMeter from '@/js/listeners/password-meter'
import passwordReveal from '@/js/listeners/password-reveal'
import popup from '@/js/listeners/popup'
import toc from '@/js/listeners/toc'
import tooltip from '@/js/listeners/tooltip'
import transferToasts from '@/js/listeners/transfer-toasts'
import videoFacade from '@/js/listeners/video-facade'
// One-time modules (run once at boot)
import autocomplete from '@/js/modules/autocomplete'
import scrollToTop from '@/js/modules/scroll-to-top'
import viewTransitions from '@/js/modules/view-transitions'
import registerServices, { type Parameters, type Registry } from '@/js/services'
import Container from '@/js/services/Container'
import type { Cleanup, Listener, Module, Page, PageContext } from '@/js/types'
import { findAllSelf } from '@/js/utils/dom'

type AnyPage = (ctx: PageContext & Record<string, unknown>) => ReturnType<Page>

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
 *
 * See `types.ts` for `Listener`, `Module`, `Page`, `Cleanup`.
 */
export class App {
    #di: Container<Registry> | null = null

    #modules: Module[] = []

    // Each listener's element type is decided by its selector (see Listener)
    #listeners: Listener<Element>[] = []

    #tracked = new Map<Listener<Element>, Set<Element>>()

    #cleanups = new WeakMap<Element, Map<Listener<Element>, Cleanup>>()

    #pages: Record<string, AnyPage> = {}

    #pageResolvers: Record<string, Array<() => void>> = {}

    constructor() {
        this.#modules = [autocomplete, scrollToTop, viewTransitions]

        this.#listeners = [
            pages,
            bulkActions,
            citySwitcher,
            contentRemovalRequest,
            cookieSettings,
            dropzone,
            duplicateCheck,
            emailVerify,
            eventPublish,
            facebookPage,
            feedback,
            fileMaxSize,
            filteredLink,
            formCollection,
            formConfirm,
            formErrors,
            formTarget,
            imagePreviews,
            impersonate,
            like,
            loadMore,
            passwordMeter,
            passwordReveal,
            popup,
            toc,
            tooltip,
            transferToasts,
            videoFacade,
        ]

        this.#listeners.forEach((listener) => this.#tracked.set(listener, new Set()))
    }

    handleError(error: unknown): never {
        Sentry.captureException(error)
        throw error
    }

    /**
     * Boot the app. Initializes the DI container, runs every module exactly
     * once, then mounts the whole document.
     */
    start(parameters: Parameters) {
        const di = new Container<Registry>(parameters)
        this.#di = di

        if (parameters.dsn) {
            Sentry.init({
                dsn: parameters.dsn,
                release: parameters.release,
                environment: parameters.environment,
                // No IP address: the member id set below is enough to follow up an error. Sentry 11 dropped
                // sendDefaultPii, and infers the IP unless userInfo is off
                dataCollection: { userInfo: false },
                // Errors of the ads scripts (AdSense Auto ads, its RUM script): nothing to fix on our side
                denyUrls: [
                    /pagead2\.googlesyndication\.com/,
                    /tpc\.googlesyndication\.com/,
                    /googleads\.g\.doubleclick\.net/,
                    /\.adtrafficquality\.google/,
                    /googletagmanager\.com/,
                    /fundingchoicesmessages\.google\.com/,
                ],
                ignoreErrors: [
                    // The same errors when they come without a stack: an Auto ads slot narrower than any ad size,
                    // and the RUM script's "Error: int64"
                    /No slot size for availableWidth/,
                    /^(Error: )?int64$/,
                    // A cross-document view transition (@view-transition) the browser skips or aborts: viewport
                    // resized, page already shown. The navigation goes on, and the rejection happens before
                    // App.start(), so no handler of ours can catch it
                    /Skipping view transition because skipTransition\(\) was called/,
                    /Transition was (aborted because of invalid state|skipped)/,
                ],
            })

            Sentry.getCurrentScope().setUser(parameters.user)
        }

        registerServices(di)

        this.#modules.forEach((module) => module({ app: this }))

        this.mount(document)
    }

    /**
     * Connect every listener to its matching elements within `container`.
     * Elements already connected are skipped, so overlapping mounts are safe.
     */
    mount(container: Element | Document) {
        if (!this.#di) {
            // Not started yet: the boot-time mount(document) will cover this container
            return
        }

        this.#listeners.forEach((listener) => {
            const tracked = this.#trackedBy(listener)

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
    }

    /**
     * Disconnect every connected element that `container` covers: the
     * container itself, its descendants, and — as a safety net — any element
     * that already left the DOM (removed by JS without an unmount call).
     */
    unmount(container: Element | Document) {
        this.#listeners.forEach((listener) => {
            this.#trackedBy(listener).forEach((element) => {
                if (element.isConnected && element !== container && !container.contains(element)) {
                    return
                }
                this.#disconnect(listener, element)
            })
        })
    }

    #trackedBy(listener: Listener<Element>): Set<Element> {
        let tracked = this.#tracked.get(listener)
        if (!tracked) {
            tracked = new Set()
            this.#tracked.set(listener, tracked)
        }

        return tracked
    }

    #disconnect(listener: Listener<Element>, element: Element) {
        this.#trackedBy(listener).delete(element)

        const cleanups = this.#cleanups.get(element)
        const cleanup = cleanups?.get(listener)
        if (!cleanups || !cleanup) {
            return
        }

        cleanups.delete(listener)
        try {
            cleanup()
        } catch (error) {
            console.error(error)
        }
    }

    #cleanupsFor(element: Element): Map<Listener<Element>, Cleanup> {
        let cleanups = this.#cleanups.get(element)
        if (!cleanups) {
            cleanups = new Map()
            this.#cleanups.set(element, cleanups)
        }

        return cleanups
    }

    /**
     * Register a page initializer keyed by id. Resolves any pending
     * `loadPage(id, …)` calls that arrived before the initializer was known.
     *
     * `P` is what the page expects from `load_page()`: the params come from the
     * template's JSON, so nothing checks them at runtime.
     */
    registerPage<P extends object>(pageId: string, initializer: Page<P>) {
        if (this.#pages[pageId]) {
            console.warn(`Page initializer for ${pageId} already exists.`)
            return
        }

        this.#pages[pageId] = (ctx) => initializer(ctx as PageContext & P)

        const resolvers = this.#pageResolvers[pageId]
        if (resolvers) {
            delete this.#pageResolvers[pageId]
            resolvers.forEach((resolve) => resolve())
        }
    }

    /**
     * Invoke a registered page initializer. If the page hasn't been registered
     * yet (lazy webpack chunk), the call is queued and replayed when
     * `registerPage` lands.
     *
     * Note: `params` must not contain `app` or `container` keys — those are
     * framework-reserved.
     */
    async loadPage(
        pageId: string,
        params: Record<string, unknown>,
        container: HTMLElement
    ): Promise<Cleanup | undefined> {
        const page = this.#pages[pageId]
        if (page) {
            return page({ ...params, app: this, container })
        }

        return new Promise((resolve) => {
            ;(this.#pageResolvers[pageId] ??= []).push(() => resolve(this.loadPage(pageId, params, container)))
        })
    }

    get<K extends keyof Registry>(key: K): Registry[K] {
        if (!this.#di) {
            throw new Error('App.get() called before App.start().')
        }

        return this.#di.get(key)
    }

    loadingButtons(container: JQuery.Selector | Element | JQuery) {
        $('.btn-submit', container)
            .prop('disabled', true)
            .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>')
    }

    resetButtons(container: JQuery.Selector | Element | JQuery) {
        $('.btn-submit', container).prop('disabled', false).find('.spinner-border').remove()
    }
}

declare global {
    interface Window {
        App: App
    }
}

window.App = new App()
