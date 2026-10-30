import { defineListener } from '@silarhi/app-js'
import { errorBody, sendJson } from '@silarhi/app-js/http'
import { Alert, Modal } from '@tabler/core'

/**
 * The body of a refused message: the message of an HTTP error, or the validation's violations.
 */
interface FeedbackError {
    detail?: string
    violations?: Array<{ message: string }>
}

/**
 * Feedback modal of the personal space layout: validates the message, posts it
 * via AJAX, then closes the modal and the banner that opens it.
 */
export default defineListener<HTMLElement>({
    selector: '#feedbackModal',
    connect(modalEl, { app }) {
        const modal = Modal.getOrCreateInstance(modalEl)
        const form = modalEl.querySelector<HTMLFormElement>('#feedback-form')
        const messageInput = form?.querySelector<HTMLTextAreaElement>('#feedback-message')
        const errorEl = form?.querySelector<HTMLElement>('#feedback-error')
        const submitBtn = modalEl.querySelector<HTMLButtonElement>('button[type="submit"]')
        if (!(modal instanceof Modal) || !form || !messageInput || !errorEl || !submitBtn) {
            return
        }

        const showError = (message: string) => {
            messageInput.classList.add('is-invalid')
            errorEl.textContent = message
        }

        const onSubmit = async (e: SubmitEvent) => {
            e.preventDefault()

            const message = messageInput.value.trim()
            if (message.length < 10) {
                showError('Votre message doit faire au moins 10 caractères')
                return
            }

            messageInput.classList.remove('is-invalid')
            submitBtn.disabled = true

            try {
                const response = await sendJson<{ message: string }>('POST', form.dataset.action ?? '', { message })
                app.get('toastManager').createToast('success', response?.message ?? '')
                modal.hide()
                const banner = document.getElementById('feedback-banner')
                if (banner) {
                    const alert = Alert.getOrCreateInstance(banner)
                    if (alert instanceof Alert) {
                        alert.close()
                    }
                }
            } catch (error) {
                const body = errorBody<FeedbackError>(error)
                let errorMessage = 'Une erreur est survenue'
                if (body?.detail) {
                    errorMessage = body.detail
                } else if (body?.violations) {
                    errorMessage = body.violations.map((v) => v.message).join(', ')
                }
                showError(errorMessage)
            } finally {
                submitBtn.disabled = false
            }
        }

        // Reset the form when the modal is closed
        const onHidden = () => {
            form.reset()
            messageInput.classList.remove('is-invalid')
            errorEl.textContent = ''
        }

        form.addEventListener('submit', onSubmit)
        modalEl.addEventListener('hidden.bs.modal', onHidden)

        return () => {
            form.removeEventListener('submit', onSubmit)
            modalEl.removeEventListener('hidden.bs.modal', onHidden)
        }
    },
})
