import { defineListener } from '@silarhi/app-js'

const SDK_URL = 'https://connect.facebook.net/fr_FR/sdk.js'

/**
 * The Facebook SDK, as far as the page plugin uses it.
 */
interface FacebookSdk {
    init(settings: FacebookInitSettings): void
    XFBML: {
        parse(element?: Element): void
    }
}

/**
 * The FB.init() settings, from `data-facebook-page`.
 */
type FacebookInitSettings = Record<string, unknown>

declare global {
    interface Window {
        FB?: FacebookSdk
        fbAsyncInit?: () => void
    }
}

let sdk: Promise<FacebookSdk> | null = null

/**
 * Load the Facebook SDK once, initialized with the settings of the widget. Nothing reaches Meta before this call.
 *
 * @param settings the FB.init() settings
 * @returns the FB global
 */
const loadSdk = (settings: FacebookInitSettings): Promise<FacebookSdk> => {
    if (window.FB) {
        return Promise.resolve(window.FB)
    }

    sdk ??= new Promise((resolve, reject) => {
        window.fbAsyncInit = () => {
            const FB = window.FB
            if (!FB) {
                return
            }
            FB.init(settings)
            resolve(FB)
        }

        const script = document.createElement('script')
        script.src = SDK_URL
        script.async = true
        script.crossOrigin = 'anonymous'
        script.onerror = () => {
            sdk = null
            reject(new Error('The Facebook SDK could not be loaded'))
        }
        document.body.appendChild(script)
    })

    return sdk
}

/**
 * Click-to-load facade of the Facebook page plugin (`location/widget/facebook.html.twig`): the SDK only loads, and
 * the page plugin only renders, once the visitor clicks "Afficher la page Facebook". `data-facebook-page` carries the
 * FB.init() settings.
 */
export default defineListener<HTMLElement>({
    selector: '[data-facebook-page]',
    connect(element) {
        const button = element.querySelector<HTMLButtonElement>('[data-facebook-page-load]')
        if (!button) return

        const onClick = () => {
            button.disabled = true

            loadSdk(JSON.parse(element.dataset.facebookPage ?? '{}') as FacebookInitSettings)
                .then((FB) => {
                    element.querySelector('[data-facebook-page-placeholder]')?.remove()
                    element.querySelector('.fb-page')?.removeAttribute('hidden')
                    FB.XFBML.parse(element)
                })
                // Blocked or offline: the placeholder stays, with its link to facebook.com
                .catch(() => {
                    button.disabled = false
                })
        }

        button.addEventListener('click', onClick)

        return () => button.removeEventListener('click', onClick)
    },
})
