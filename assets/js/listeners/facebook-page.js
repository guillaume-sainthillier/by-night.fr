const SDK_URL = 'https://connect.facebook.net/fr_FR/sdk.js'

let sdk = null

/**
 * Load the Facebook SDK once, initialized with the settings of the widget. Nothing reaches Meta before this call.
 *
 * @param {Object} settings the FB.init() settings
 * @returns {Promise<Object>} the FB global
 */
const loadSdk = (settings) => {
    if (window.FB) {
        return Promise.resolve(window.FB)
    }

    sdk ??= new Promise((resolve, reject) => {
        window.fbAsyncInit = () => {
            window.FB.init(settings)
            resolve(window.FB)
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
 *
 * @type {Listener}
 */
export default {
    selector: '[data-facebook-page]',
    connect(element) {
        const button = element.querySelector('[data-facebook-page-load]')
        if (!button) return

        const onClick = () => {
            button.disabled = true

            loadSdk(JSON.parse(element.dataset.facebookPage))
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
}
