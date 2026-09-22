import $ from 'jquery'

/**
 * Ask for a confirmation in a SweetAlert dialog before submitting a form carrying `data-confirm-message`.
 * `data-confirm-title` and `data-confirm-button` set the dialog title and the confirm button label.
 *
 * Bound through jQuery: form-target.js submits its target with `$(form).submit()`, which only runs jQuery handlers.
 *
 * @type {Listener}
 */
export default {
    selector: 'form[data-confirm-message]',
    connect(form, { app }) {
        const $form = $(form)

        $form.on('submit.confirm', async (e) => {
            // Before any await: only a synchronous call cancels the submission
            e.preventDefault()

            const { confirmTitle, confirmMessage, confirmButton } = form.dataset
            const confirmed = await app.get('modalManager').createConfirm({
                text: confirmMessage,
                // An undefined value would override SweetAlert's default instead of keeping it
                ...(confirmTitle && { title: confirmTitle }),
                ...(confirmButton && { confirmButtonText: confirmButton }),
            })

            // The native submit() fires no submit event, so it does not ask again
            if (confirmed) {
                form.submit()
            }
        })

        return () => $form.off('.confirm')
    },
}
