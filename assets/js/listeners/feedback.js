import { Alert, Modal } from '@tabler/core'
import $ from 'jquery'

/**
 * Feedback modal of the personal space layout: validates the message, posts it
 * via AJAX, then closes the modal and the banner that opens it.
 *
 * @type {Listener}
 */
export default {
    selector: '#feedbackModal',
    connect(modalEl, { app }) {
        const modal = Modal.getOrCreateInstance(modalEl)
        const form = modalEl.querySelector('#feedback-form')
        const messageInput = form.querySelector('#feedback-message')
        const errorEl = form.querySelector('#feedback-error')
        const submitBtn = modalEl.querySelector('button[type="submit"]')

        const showError = (message) => {
            messageInput.classList.add('is-invalid')
            errorEl.textContent = message
        }

        const onSubmit = (e) => {
            e.preventDefault()

            const message = messageInput.value.trim()
            if (message.length < 10) {
                showError('Votre message doit faire au moins 10 caractères')
                return
            }

            messageInput.classList.remove('is-invalid')
            submitBtn.disabled = true

            $.ajax({
                url: form.dataset.action,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ message }),
            })
                .done((response) => {
                    app.get('toastManager').createToast('success', response.message)
                    modal.hide()
                    const banner = document.getElementById('feedback-banner')
                    if (banner) {
                        Alert.getOrCreateInstance(banner).close()
                    }
                })
                .fail((xhr) => {
                    let errorMessage = 'Une erreur est survenue'
                    if (xhr.responseJSON?.detail) {
                        errorMessage = xhr.responseJSON.detail
                    } else if (xhr.responseJSON?.violations) {
                        errorMessage = xhr.responseJSON.violations.map((v) => v.message).join(', ')
                    }
                    showError(errorMessage)
                })
                .always(() => {
                    submitBtn.disabled = false
                })
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
}
