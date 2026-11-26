import { defineListener } from '@silarhi/app-js'
import { errorBody, sendJson } from '@silarhi/app-js/http'
import { Modal } from '@tabler/core'
import { path } from '@/js/router'

/**
 * What the removal request API answers once the request is recorded.
 */
interface RemovalResponse {
    message: string
}

/**
 * The body of a refused request: the validation's violations, or the message of an HTTP error.
 */
interface RemovalError {
    violations?: Array<{ message: string }>
    message?: string
    detail?: string
}

/**
 * Content removal request modal: captures the triggering event data on open,
 * validates the form and submits the request via AJAX.
 */
export default defineListener<HTMLElement>({
    selector: '#modalContentRemovalRequest',
    connect(modalEl) {
        const modal = Modal.getOrCreateInstance(modalEl)
        const form = modalEl.querySelector<HTMLFormElement>('#content-removal-form')
        const submitBtn = modalEl.querySelector<HTMLButtonElement>('#content-removal-submit')
        const alertEl = modalEl.querySelector<HTMLElement>('#content-removal-alert')
        const spinner = submitBtn?.querySelector('.spinner-border')
        const emailInput = form?.querySelector<HTMLInputElement>('#removal-email')
        const typeInput = form?.querySelector<HTMLSelectElement>('#removal-type')
        const messageInput = form?.querySelector<HTMLTextAreaElement>('#removal-message')
        const urlsInput = form?.querySelector<HTMLTextAreaElement>('#removal-urls')
        if (
            !(modal instanceof Modal) ||
            !form ||
            !submitBtn ||
            !alertEl ||
            !spinner ||
            !emailInput ||
            !typeInput ||
            !messageInput ||
            !urlsInput
        ) {
            return
        }

        let currentEventId: string | null = null
        let currentEventUrl: string | null = null

        const showError = (message: string) => {
            alertEl.innerHTML = message
            alertEl.classList.remove('d-none', 'alert-success')
            alertEl.classList.add('alert-danger')
        }

        // Capture event data when modal is opened
        const onShow = (event: Event) => {
            // Bootstrap's show event carries the element that opened the modal
            const button =
                'relatedTarget' in event && event.relatedTarget instanceof Element ? event.relatedTarget : null
            // The event the request is about (POST /api/events/{id}/removal-requests)
            currentEventId = button?.getAttribute('data-event-id') ?? null
            currentEventUrl = button?.getAttribute('data-event-url') ?? null

            // Reset form
            form.reset()
            form.classList.remove('was-validated')
            alertEl.classList.add('d-none')
            alertEl.classList.remove('alert-success', 'alert-danger')
        }

        // Handle form submission
        const onSubmit = async () => {
            // Reset validation states
            emailInput.classList.remove('is-invalid')
            typeInput.classList.remove('is-invalid')
            messageInput.classList.remove('is-invalid')

            // Validate
            let isValid = true

            if (!emailInput.value.trim() || !emailInput.validity.valid) {
                emailInput.classList.add('is-invalid')
                isValid = false
            }

            if (!typeInput.value) {
                typeInput.classList.add('is-invalid')
                isValid = false
            }

            if (!messageInput.value.trim() || messageInput.value.trim().length < 10) {
                messageInput.classList.add('is-invalid')
                isValid = false
            }

            if (!isValid) {
                form.classList.add('was-validated')
                return
            }

            // Prepare data
            const urlsText = urlsInput.value.trim()
            const eventUrls = urlsText
                ? urlsText
                      .split('\n')
                      .map((url) => url.trim())
                      .filter((url) => url.length > 0)
                : []

            if (currentEventUrl) {
                eventUrls.unshift(currentEventUrl)
            }

            const data = {
                email: emailInput.value.trim(),
                type: typeInput.value,
                message: messageInput.value.trim(),
                eventUrls: eventUrls,
            }

            // Show loading state
            submitBtn.disabled = true
            spinner.classList.remove('d-none')
            alertEl.classList.add('d-none')

            try {
                const response = await sendJson<RemovalResponse>(
                    'POST',
                    path('api_event_removal_request', { id: currentEventId ?? '' }),
                    data
                )
                if (response) {
                    alertEl.textContent = response.message
                    alertEl.classList.remove('d-none', 'alert-danger')
                    alertEl.classList.add('alert-success')
                    form.classList.add('d-none')
                    submitBtn.classList.add('d-none')

                    // Auto-close modal after 3 seconds
                    setTimeout(() => {
                        modal.hide()
                        // Reset for next use
                        form.classList.remove('d-none')
                        submitBtn.classList.remove('d-none')
                    }, 3000)
                } else {
                    // No answer of the API: the page's HTML of an error
                    showError('Une erreur est survenue')
                }
            } catch (error) {
                const body = errorBody<RemovalError>(error)
                let errorMessage = 'Une erreur est survenue'
                if (body?.violations) {
                    errorMessage = body.violations.map((v) => v.message).join('<br>')
                } else if (body?.message || body?.detail) {
                    // "detail": the message of an HTTP error, such as too many requests
                    errorMessage = body.message || body.detail || errorMessage
                }
                showError(errorMessage)
            } finally {
                submitBtn.disabled = false
                spinner.classList.add('d-none')
            }
        }

        modalEl.addEventListener('show.bs.modal', onShow)
        submitBtn.addEventListener('click', onSubmit)

        return () => {
            modalEl.removeEventListener('show.bs.modal', onShow)
            submitBtn.removeEventListener('click', onSubmit)
        }
    },
})
