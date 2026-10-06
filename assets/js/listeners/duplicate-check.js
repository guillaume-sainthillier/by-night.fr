import { confirmPublication, duplicatesSearchBody } from '@/js/utils/duplicates'

/**
 * The event forms that publish (`data-duplicates-url`, a new event or a draft) first ask for the events already online
 * that this one likely repeats, and let the member confirm. A button carrying `data-skip-duplicates` (the draft one)
 * saves without asking: a draft is not online.
 *
 * @type {Listener}
 */
export default {
    selector: 'form[data-duplicates-url]',
    connect(form, { app }) {
        let checking = false
        let confirmed = false

        const onSubmit = async (e) => {
            if (e.submitter?.hasAttribute('data-skip-duplicates')) {
                return
            }

            // Asked and answered: the submission sent again below goes through
            if (confirmed) {
                confirmed = false
                return
            }

            // Before any await: only a synchronous call cancels the submission
            e.preventDefault()
            if (checking) {
                return
            }

            checking = true
            try {
                confirmed = await confirmPublication(app.get('modalManager'), form.dataset.duplicatesUrl, {
                    method: 'POST',
                    body: duplicatesSearchBody(form),
                })
            } finally {
                checking = false
            }

            // With the button clicked, as the browser would have sent it
            if (confirmed) {
                form.requestSubmit(e.submitter ?? undefined)
            }
        }

        form.addEventListener('submit', onSubmit)

        return () => form.removeEventListener('submit', onSubmit)
    },
}
