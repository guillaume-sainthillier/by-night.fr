import $ from 'jquery'

$(document).ready(() => {
    $('.form-delete').submit(() =>
        window.confirm("Cette action va supprimer l'événement ainsi que toutes les données rattachées. Continuer ?")
    )

    $('.draft').change(function () {
        const self = $(this)

        self.attr('disabled', true)
        $.ajax({
            url: self.data('href'),
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ draft: !self.prop('checked') }),
        }).done(() => {
            self.attr('disabled', false)
        })
    })

    $('.cancel').change(function () {
        const self = $(this)
        self.attr('disabled', true)
        $.ajax({
            url: self.data('href'),
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ cancel: self.prop('checked') }),
        }).done(() => {
            self.attr('disabled', false)
        })
    })

    // Feedback form
    const feedbackForm = $('#feedback-form')
    const feedbackModal = $('#feedbackModal')
    const feedbackMessage = $('#feedback-message')
    const feedbackError = $('#feedback-error')
    const submitBtn = feedbackForm.closest('.modal-content').find('button[type="submit"]')

    feedbackForm.on('submit', (e) => {
        e.preventDefault()

        const message = feedbackMessage.val().trim()
        if (message.length < 10) {
            feedbackMessage.addClass('is-invalid')
            feedbackError.text('Votre message doit faire au moins 10 caractères')
            return
        }

        feedbackMessage.removeClass('is-invalid')
        submitBtn.attr('disabled', true)

        $.ajax({
            url: feedbackForm.data('action'),
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ message: message }),
        })
            .done((response) => {
                window.App.get('toastManager').createToast('success', response.message)
                feedbackModal.modal('hide')
                $('#feedback-banner').alert('close')
            })
            .fail((xhr) => {
                let errorMessage = 'Une erreur est survenue'
                if (xhr.responseJSON?.detail) {
                    errorMessage = xhr.responseJSON.detail
                } else if (xhr.responseJSON?.violations) {
                    errorMessage = xhr.responseJSON.violations.map((v) => v.message).join(', ')
                }
                feedbackMessage.addClass('is-invalid')
                feedbackError.text(errorMessage)
            })
            .always(() => {
                submitBtn.attr('disabled', false)
            })
    })

    // Reset form when modal is closed
    feedbackModal.on('hidden.bs.modal', () => {
        feedbackForm[0].reset()
        feedbackMessage.removeClass('is-invalid')
        feedbackError.text('')
    })
})
