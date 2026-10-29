import '@/scss/app.scss'

// Symfony UX Stimulus
import '@/stimulus_bootstrap'

// Bootstrap's data attributes (dropdowns, collapses, modals…)
import '@tabler/core'
import '@/js/overrides'

import * as Sentry from '@sentry/browser'
import { App, type ErrorContext, lazyListener } from '@silarhi/app-js'
import { collectionListener } from '@silarhi/app-js/form'
// Per-element listeners (connected on every mount)
import actionBar from '@/js/listeners/action-bar'
import activeChip from '@/js/listeners/active-chip'
import analytics from '@/js/listeners/analytics'
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
 * by `mount(container)` and torn down by `unmount(container)`, and the page initializers named by `load_page()` are
 * imported on the first page that declares them. See @silarhi/app-js.
 */
const app = new App({
    modules: [autocomplete, scrollToTop, viewTransitions],
    listeners: [
        actionBar,
        activeChip,
        analytics,
        bulkActions,
        // The sign-up challenge, imported with silarhi/cap-bundle on the pages of the form only
        lazyListener('[data-silarhi-cap]', () => import('@/js/listeners/cap')),
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
    pages: {
        admin_infos: () => import('@/js/pages/admin_infos'),
        agenda: () => import('@/js/pages/agenda'),
        // The map of the agenda's results (location/agenda/_map.html.twig), in the chunk of the agenda
        agenda_map: () => import('@/js/pages/agenda').then(({ agendaMap }) => ({ default: agendaMap })),
        event_details: () => import('@/js/pages/event_details'),
        event_stats: () => import('@/js/pages/event_stats'),
        event_transfers: () => import('@/js/pages/event_transfers'),
        index: () => import('@/js/pages/index'),
        personal_space_event: () => import('@/js/pages/personal_space_event'),
        personal_space_list: () => import('@/js/pages/personal_space_list'),
        profile: () => import('@/js/pages/profile'),
        user: () => import('@/js/pages/user'),
    },
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

    // A module script: the document is parsed. Each data-page marker imports its page from `pages`, a chunk its
    // template's entry tag has already preloaded
    app.start()
    app.mount(document)
}
