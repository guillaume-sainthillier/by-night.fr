import '@/scss/app.scss'

// Symfony UX Stimulus
import '@/stimulus_bootstrap'

import '@/js/vendors'
import '@/js/overrides'

import * as Sentry from '@sentry/browser'
import { App, type ErrorContext } from '@silarhi/app-js'
import { collectionListener } from '@silarhi/app-js/form'
// Per-element listeners (connected on every mount)
import actionBar from '@/js/listeners/action-bar'
import activeChip from '@/js/listeners/active-chip'
import bulkActions from '@/js/listeners/bulk-actions'
import citySwitcher from '@/js/listeners/city-switcher'
import contentRemovalRequest from '@/js/listeners/content-removal-request'
import cookieSettings from '@/js/listeners/cookie-settings'
import copyUrl from '@/js/listeners/copy-url'
import dropzone from '@/js/listeners/dropzone'
import duplicateCheck from '@/js/listeners/duplicate-check'
import emailVerify from '@/js/listeners/email-verify'
import eventPublish from '@/js/listeners/event-publish'
import facebookPage from '@/js/listeners/facebook-page'
import feedback from '@/js/listeners/feedback'
import fileMaxSize from '@/js/listeners/file-max-size'
import filteredLink from '@/js/listeners/filtered-link'
import formConfirm from '@/js/listeners/form-confirm'
import formErrors from '@/js/listeners/form-errors'
import formTarget from '@/js/listeners/form-target'
import imagePreviews from '@/js/listeners/image-previews'
import impersonate from '@/js/listeners/impersonate'
import like from '@/js/listeners/like'
import loadMore from '@/js/listeners/load-more'
import otpAutosubmit from '@/js/listeners/otp-autosubmit'
import passwordMeter from '@/js/listeners/password-meter'
import passwordReveal from '@/js/listeners/password-reveal'
import popup from '@/js/listeners/popup'
import readMore from '@/js/listeners/read-more'
import share from '@/js/listeners/share'
import toc from '@/js/listeners/toc'
import tooltip from '@/js/listeners/tooltip'
import transferToasts from '@/js/listeners/transfer-toasts'
import videoFacade from '@/js/listeners/video-facade'
import webauthn from '@/js/listeners/webauthn'
// One-time modules (run once at boot)
import autocomplete from '@/js/modules/autocomplete'
import scrollToTop from '@/js/modules/scroll-to-top'
import viewTransitions from '@/js/modules/view-transitions'
import { type Parameters, services } from '@/js/services'

declare global {
    interface Window {
        App: App
        /** Set by templates/base.html.twig before this entry runs; the error pages load the bundle without them */
        appParameters?: Parameters
    }
}

/**
 * What to do with an error the app caught in a module, a listener's connect or cleanup, or a page initializer: the
 * app carries on with the next one.
 */
const reportError = (error: unknown, context: ErrorContext) => {
    console.error(error)

    // Caught, so Sentry's global handlers never see it. Tagged with what failed, to search and group by it; the element
    // stays out (its markup may hold a member's data). A no-op without a DSN (dev, tests)
    const tags: Record<string, string> = { 'app.stage': context.type }
    if (context.type === 'page') {
        tags['app.page'] = context.pageId
    } else if (context.type === 'module') {
        tags['app.module'] = context.module.name || 'anonymous'
    } else {
        tags['app.listener'] = context.listener.selector
    }
    Sentry.captureException(error, { tags })
}

const parameters = window.appParameters

/**
 * Application entry point: modules run once at boot, listeners are `{selector, connect}` pairs connected per element
 * by `mount(container)` and torn down by `unmount(container)`, and the `data-page` markers of `load_page()` run the
 * initializers the page entries register (`registerPage()`). See @silarhi/app-js.
 */
const app = new App({
    modules: [autocomplete, scrollToTop, viewTransitions],
    listeners: [
        actionBar,
        activeChip,
        bulkActions,
        citySwitcher,
        contentRemovalRequest,
        cookieSettings,
        copyUrl,
        dropzone,
        duplicateCheck,
        emailVerify,
        eventPublish,
        facebookPage,
        feedback,
        fileMaxSize,
        filteredLink,
        // The add and remove buttons of the form collections, handed to the collectionManager service
        collectionListener('collectionManager'),
        formConfirm,
        formErrors,
        formTarget,
        imagePreviews,
        impersonate,
        like,
        loadMore,
        otpAutosubmit,
        passwordMeter,
        passwordReveal,
        popup,
        readMore,
        share,
        toc,
        tooltip,
        transferToasts,
        videoFacade,
        webauthn,
    ],
    services,
    parameters,
    onError: reportError,
})

window.App = app

if (parameters) {
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

    // A module script: the document is parsed. The page entries load after this one, and their data-page markers wait
    // for registerPage(), which runs them once their own chunk has been evaluated
    app.start()
    app.mount(document)
}
