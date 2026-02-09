import $ from 'jquery'
import { Modal } from 'bootstrap'

export default (_di, _container) => {
    const modalEl = document.getElementById('modalContentRemovalRequest')
    if (!modalEl) {
        return
    }

    const modal = Modal.getOrCreateInstance(modalEl)
    const form = modalEl.querySelector('#content-removal-form')
    const submitBtn = modalEl.querySelector('#content-removal-submit')
    const alertEl = modalEl.querySelector('#content-removal-alert')
    const spinner = submitBtn.querySelector('.spinner-border')

    let currentEventId = null
    let currentEventUrl = null

    // Capture event data when modal is opened
    modalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget
        currentEventId = button.getAttribute('data-event-id')
        currentEventUrl = button.getAttribute('data-event-url')

        // Reset form
        form.reset()
        form.classList.remove('was-validated')
        alertEl.classList.add('d-none')
        alertEl.classList.remove('alert-success', 'alert-danger')
    })

    // Handle form submission
    submitBtn.addEventListener('click', function () {
        const emailInput = form.querySelector('#removal-email')
        const typeInput = form.querySelector('#removal-type')
        const messageInput = form.querySelector('#removal-message')
        const urlsInput = form.querySelector('#removal-urls')

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

        $.ajax({
            url: `/api/events/${currentEventId}/removal-request`,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
        })
            .done(function (response) {
                if (response.success) {
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
                    showError(response.message || "Une erreur s'est produite")
                }
            })
            .fail(function (xhr) {
                let errorMessage = "Une erreur s'est produite"
                if (xhr.responseJSON && xhr.responseJSON.violations) {
                    errorMessage = xhr.responseJSON.violations.map((v) => v.message).join('<br>')
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message
                }
                showError(errorMessage)
            })
            .always(function () {
                submitBtn.disabled = false
                spinner.classList.add('d-none')
            })
    })

    function showError(message) {
        alertEl.innerHTML = message
        alertEl.classList.remove('d-none', 'alert-success')
        alertEl.classList.add('alert-danger')
    }
}
