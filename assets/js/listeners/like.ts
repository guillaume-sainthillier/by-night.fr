import { defineListener } from '@silarhi/app-js'
import { sendJson } from '@/js/utils/http'

const options = {
    css_selector_like: '.btn-like-event',
    css_active_class: 'btn-primary',
}

/** The answer of the like endpoint */
interface LikeResponse {
    success: boolean
    like: boolean
}

/**
 * Toggle an event like via AJAX when the like button is clicked.
 *
 * A button with a `data-intent` ("participer") also records the click a visitor made before logging in: the login
 * leads back to the page with that fragment (LoginTargetPath), and the button is clicked once for them, unless the
 * event is already in their outings.
 */
export default defineListener<HTMLElement>({
    selector: options.css_selector_like,
    connect(element, { app }) {
        const { href = '' } = element.dataset

        const like = async () => {
            // A button, or a link styled as one: the attribute disables both
            element.setAttribute('disabled', 'disabled')
            let msg: LikeResponse | null
            try {
                msg = await sendJson<LikeResponse>('PUT', href, {
                    like: !element.classList.contains(options.css_active_class),
                })
            } catch {
                element.removeAttribute('disabled')
                app.get('toastManager').createToast('error', 'Une erreur est survenue, veuillez réessayer.')
                return
            }
            // Refused: the button stays disabled
            if (!msg?.success) {
                return
            }

            element.removeAttribute('disabled')
            element.classList.toggle(options.css_active_class, msg.like)
            // The page's other buttons of the same event (the action bar of a phone) follow
            for (const other of document.querySelectorAll<HTMLElement>(options.css_selector_like)) {
                if (other.dataset.href === href) {
                    other.classList.toggle(options.css_active_class, msg.like)
                }
            }
            if (msg.like) {
                app.get('toastManager').createToast('success', 'Ajouté à vos sorties')
            }
        }

        element.addEventListener('click', like)

        const { intent } = element.dataset
        if (intent && window.location.hash === `#${intent}`) {
            // Once: a reload or a shared URL must not click again
            window.history.replaceState(null, '', window.location.pathname + window.location.search)
            if (!element.classList.contains(options.css_active_class)) {
                like()
            }
        }

        return () => element.removeEventListener('click', like)
    },
})
