import { defineListener } from '@silarhi/app-js'
import { postForm } from '@silarhi/app-js/http'
import { Alert } from '@tabler/core'

/**
 * Send a verification email when the element is clicked, then dismiss its
 * alert (or remove the element).
 */
export default defineListener<HTMLAnchorElement | HTMLButtonElement>({
    selector: 'a.email-verify, button.email-verify',
    connect(element, { app }) {
        const onClick = async (e: Event) => {
            e.preventDefault()
            const url = element.getAttribute('href') || element.dataset.href || ''
            try {
                await postForm(url)
            } catch {
                app.get('toastManager').createToast('error', "L'e-mail n'a pas pu être envoyé, veuillez réessayer.")
                return
            }
            app.get('toastManager').createToast('success', 'Un e-mail de vérification a bien été envoyé.')
            const banner = element.closest<HTMLElement>('.alert')
            const alert = banner ? Alert.getOrCreateInstance(banner) : null
            if (alert instanceof Alert) {
                alert.close()
            } else {
                element.remove()
            }
        }

        element.addEventListener('click', onClick)

        return () => element.removeEventListener('click', onClick)
    },
})
