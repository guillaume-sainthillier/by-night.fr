import '../scss/app.scss'

import './vendors'
import './overrides'
import './utils/css'
import './utils/dom'

import * as Sentry from '@sentry/browser'
import Container from './services/Container'
import registerServices from './services'

// Global listeners
import lazyload from './global-listeners/lazyload'
import headerSearch from './global-listeners/header-search'
import scrollToTop from './global-listeners/scroll-to-top'

// Listeners
import emailVerify from './listeners/email-verify'
import formCollection from './listeners/form-collection'
import formErrors from './listeners/form-errors'
import formTarget from './listeners/form-target'
import imagePreviews from './listeners/image-previews'
import like from './listeners/like'
import loadMore from './listeners/load-more'
import login from './listeners/login'
import popup from './listeners/popup'
import register from './listeners/register'
import tooltip from './listeners/tooltip'

class App {
    #di

    #listeners

    #pageListeners

    #beforeRunListeners

    constructor() {
        this.#di = null
        this.#beforeRunListeners = []
        this.#listeners = [headerSearch, imagePreviews, lazyload, scrollToTop]

        this.#pageListeners = [
            emailVerify,
            formCollection,
            formErrors,
            formTarget,
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
                autoSessionTracking: false,
            })

            Sentry.getCurrentScope().setUser(this.get('user'))
        }

        registerServices(this.#di)

        // Execute the page load listeners
        this.#listeners.forEach((listener) => {
            listener(this.#di)
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
