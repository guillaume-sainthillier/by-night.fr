import { defineListener } from '@silarhi/app-js'
import { sendJson } from '@silarhi/app-js/http'
import { plural } from '@/js/utils/plural'

/** The answer of PUT /api/publishers/{id}/follow and /api/places/{id}/follow */
interface FollowResponse {
    following: boolean
    followers: number
}

const ACTIVE_CLASS = 'btn-primary'

/**
 * The "Suivre" button of a publisher or a venue (<twig:FollowButton>): follows it or stops following it, and says how
 * many follow it. A visitor who clicked it before logging in comes back with the "#suivre" fragment, and the button is
 * clicked once for them, unless they follow it already.
 */
export default defineListener<HTMLButtonElement>({
    selector: '.btn-follow',
    connect(button, { app }) {
        const { href = '', intent } = button.dataset

        const follow = async () => {
            button.disabled = true
            let state: FollowResponse | null
            try {
                state = await sendJson<FollowResponse>('PUT', href, {
                    following: !button.classList.contains(ACTIVE_CLASS),
                })
            } catch {
                button.disabled = false
                app.get('toastManager').createToast('error', 'Une erreur est survenue, veuillez réessayer.')
                return
            }
            // No answer of the API (a lapsed session led to the login page): the button stays disabled
            if (!state) {
                return
            }

            button.disabled = false
            button.classList.toggle(ACTIVE_CLASS, state.following)
            button.setAttribute('aria-pressed', String(state.following))
            const label = button.querySelector('.btn-follow-label')
            if (label) {
                label.textContent = state.following ? 'Suivi' : 'Suivre'
            }
            const followers = button.parentElement?.querySelector('[data-followers]')
            if (followers) {
                followers.textContent =
                    state.followers === 0 ? '' : plural(state.followers, { one: '# abonné', other: '# abonnés' })
            }
            if (state.following) {
                app.get('toastManager').createToast('success', 'Vous serez prévenu de ses nouveaux événements')
            }
        }

        button.addEventListener('click', follow)

        if (intent && window.location.hash === `#${intent}`) {
            // Once: a reload or a shared URL must not click again
            window.history.replaceState(null, '', window.location.pathname + window.location.search)
            if (!button.classList.contains(ACTIVE_CLASS)) {
                follow()
            }
        }

        return () => button.removeEventListener('click', follow)
    },
})
