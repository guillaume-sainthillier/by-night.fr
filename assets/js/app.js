import '@/scss/app.scss'

// Symfony UX Stimulus
import '@/stimulus_bootstrap.js'

import $ from 'jquery'
import '@/js/vendors'
import '@/js/overrides'
import '@/js/utils/css'
import '@/js/utils/dom'

import * as Sentry from '@sentry/browser'
// Global listeners
import autocomplete from '@/js/global-listeners/autocomplete'
import scrollToTop from '@/js/global-listeners/scroll-to-top'
// Listeners
import contentRemovalRequest from '@/js/listeners/content-removal-request'
import dropzone from '@/js/listeners/dropzone'
import emailVerify from '@/js/listeners/email-verify'
import formCollection from '@/js/listeners/form-collection'
import formErrors from '@/js/listeners/form-errors'
import formTarget from '@/js/listeners/form-target'
import imagePreviews from '@/js/listeners/image-previews'
import impersonate from '@/js/listeners/impersonate'
import like from '@/js/listeners/like'
import loadMore from '@/js/listeners/load-more'
import login from '@/js/listeners/login'
import popup from '@/js/listeners/popup'
import register from '@/js/listeners/register'
import tooltip from '@/js/listeners/tooltip'
import registerServices from '@/js/services'
import Container from '@/js/services/Container'

class App {
    #di

    #listeners

    #pageListeners

    #beforeRunListeners

    constructor() {
        this.#di = null
        this.#beforeRunListeners = []
        this.#listeners = [autocomplete, imagePreviews, scrollToTop]

        this.#pageListeners = [
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
    }

    handleError(error) {
        Sentry.captureException(error)
        throw error
    }

    run(parameters) {
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

        // Execute the page load listeners
        this.#listeners.forEach((listener) => {
            listener(this.#di, document)
        })

        this.dispatchPageLoadedEvent()
    }

    dispatchPageLoadedEvent(container = document) {
        if (!this.#di) {
            // We store container when run is not called yet
            this.#beforeRunListeners.push(container)
            return
        }

        if (this.#beforeRunListeners.length > 0) {
            this.#beforeRunListeners = []
            this.#beforeRunListeners.forEach((beforeRunContainer) => {
                this.dispatchPageLoadedEvent(beforeRunContainer)
            })
        }

        this.#pageListeners.forEach((listener) => {
            listener(this.#di, container)
        })

        if (typeof window.onPageLoaded === 'function') {
            window.onPageLoaded(this, container)
            window.onPageLoaded = null
        }
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
