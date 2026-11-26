import { defineListener } from '@silarhi/app-js'
import { sendJson } from '@silarhi/app-js/http'

const options = {
    css_selector_like: '.btn-like-event',
    css_active_class: 'btn-primary',
}

/** The answer of PUT /api/events/{id}/participation */
interface ParticipationResponse {
    going: boolean
    participations: number
}

/**
 * Says the member goes to an event, or no longer does, when its "J'y vais" button is clicked (PUT /api/events/{id}/participation).
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
            let msg: ParticipationResponse | null
            try {
                msg = await sendJson<ParticipationResponse>('PUT', href, {
                    going: !element.classList.contains(options.css_active_class),
                })
            } catch {
                element.removeAttribute('disabled')
                app.get('toastManager').createToast('error', 'Une erreur est survenue, veuillez réessayer.')
                return
            }
            // No answer of the API (a lapsed session led to the login page): the button stays disabled
            if (!msg) {
                return
            }

            element.removeAttribute('disabled')
            element.classList.toggle(options.css_active_class, msg.going)
            // The page's other buttons of the same event (the action bar of a phone) follow
            for (const other of document.querySelectorAll<HTMLElement>(options.css_selector_like)) {
                if (other.dataset.href === href) {
                    other.classList.toggle(options.css_active_class, msg.going)
                }
            }
            if (msg.going) {
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
