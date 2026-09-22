/**
 * Reopen Google's consent message ("Gérer mes cookies"), so the visitor can change the answer given to the banner.
 * The message comes from Privacy & messaging in AdSense, loaded by the AdSense tag in base.html.twig; until it has
 * loaded, the call waits in its callback queue.
 *
 * @type {Listener}
 */
export default {
    selector: '[data-cookie-consent]',
    connect(element) {
        const onClick = (event) => {
            event.preventDefault()

            window.googlefc = window.googlefc || {}
            window.googlefc.callbackQueue = window.googlefc.callbackQueue || []
            window.googlefc.callbackQueue.push({
                CONSENT_API_READY: () => window.googlefc.showRevocationMessage(),
            })
        }

        element.addEventListener('click', onClick)

        return () => element.removeEventListener('click', onClick)
    },
}
