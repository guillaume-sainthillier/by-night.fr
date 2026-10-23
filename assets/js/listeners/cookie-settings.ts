import type { Listener } from '@/js/types'

/**
 * Google's Funding Choices API (the consent message of AdSense's Privacy & messaging), as far as it is used here: the
 * queue of callbacks it runs once loaded, and the call reopening the message.
 */
interface GoogleFundingChoices {
    callbackQueue?: Array<Record<string, () => void>>
    showRevocationMessage?: () => void
}

declare global {
    interface Window {
        googlefc?: GoogleFundingChoices
    }
}

/**
 * Reopen Google's consent message ("Gérer mes cookies"), so the visitor can change the answer given to the banner.
 * The message comes from Privacy & messaging in AdSense, loaded by the AdSense tag in base.html.twig; until it has
 * loaded, the call waits in its callback queue.
 */
export default {
    selector: '[data-cookie-consent]',
    connect(element) {
        const onClick = (event: MouseEvent) => {
            event.preventDefault()

            window.googlefc = window.googlefc || {}
            window.googlefc.callbackQueue = window.googlefc.callbackQueue || []
            window.googlefc.callbackQueue.push({
                // Read when the API is ready: its script has completed the object by then
                CONSENT_API_READY: () => window.googlefc?.showRevocationMessage?.(),
            })
        }

        element.addEventListener('click', onClick)

        return () => element.removeEventListener('click', onClick)
    },
} satisfies Listener
