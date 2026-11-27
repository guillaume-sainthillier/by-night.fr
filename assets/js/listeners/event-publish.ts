import { defineListener } from '@silarhi/app-js'
import { sendJson } from '@silarhi/app-js/http'
import { path } from '@/js/router'
import { confirmPublication } from '@/js/utils/duplicates'

/**
 * Puts the event online (PATCH /api/events/{id}), returning whether it worked.
 */
async function publish(id: string): Promise<boolean> {
    // A lapsed session is redirected to the login page, whose HTML is no answer
    const state = await sendJson<{ draft: boolean }>('PATCH', path('api_event_update', { id }), { draft: false })

    return state?.draft === false
}

/**
 * "Publish" button of a draft's preview (`data-publish-event`, the event's id): publishes the event, then drops the
 * preview notice. When the event has a publisher (`data-publisher`), it first asks for the events already online the
 * draft likely repeats.
 */
export default defineListener<HTMLButtonElement>({
    selector: 'button[data-publish-event]',
    connect(button, { app }) {
        const onClick = async () => {
            button.disabled = true
            const { publishEvent: id = '', publisher } = button.dataset
            if (
                publisher &&
                !(await confirmPublication(
                    app.get('modalManager'),
                    path('app_event_edit_duplicates', { publisher, id })
                ))
            ) {
                button.disabled = false
                return
            }

            const published = await publish(id).catch(() => false)
            if (!published) {
                button.disabled = false
                app.get('toastManager').createToast('error', "L'événement n'a pas pu être publié, veuillez réessayer.")
                return
            }

            app.get('toastManager').createToast('success', "L'événement est en ligne.")
            button.closest('.alert')?.remove()
        }

        button.addEventListener('click', onClick)

        return () => button.removeEventListener('click', onClick)
    },
})
