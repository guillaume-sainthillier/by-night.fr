import { defineListener } from '@silarhi/app-js'

/**
 * Ask for a confirmation in a SweetAlert dialog before submitting a form carrying `data-confirm-message`.
 * `data-confirm-title` and `data-confirm-button` set the dialog title and the confirm button label.
 */
export default defineListener<HTMLFormElement>({
    selector: 'form[data-confirm-message]',
    connect(form, { app }) {
        const onSubmit = async (e: SubmitEvent) => {
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
        }

        form.addEventListener('submit', onSubmit)

        return () => form.removeEventListener('submit', onSubmit)
    },
})
