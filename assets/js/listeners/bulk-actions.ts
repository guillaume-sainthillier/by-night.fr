import { defineListener } from '@silarhi/app-js'
import { plural } from '@/js/utils/plural'

/**
 * How many items are picked, worded for the bar of a bulk form.
 */
export function selectionLabel(count: number): string {
    return plural(count, { one: '# événement sélectionné', other: '# événements sélectionnés' })
}

/**
 * Bulk forms (`form[data-bulk-form]`): an action applied to several events at once (EventController::bulk()).
 *
 * - Picked from a list: the checkboxes `[data-bulk-item][form="<form id>"]` of its rows, and the select-all box
 *   `[data-bulk-all="<form id>"]`. The form, its bar, shows while something is picked, with the count in
 *   `[data-bulk-count]`.
 * - Fixed (an import's events, in hidden fields): the form is always shown.
 *
 * Its "delete" button asks for a confirmation first, worded by `data-bulk-confirm-title`, `-message` and `-button` on
 * the form. Never `data-confirm-*`: form-confirm.ts would ask on every button, delete included (a second dialog), and
 * submit the form without the button that names the action.
 */
export default defineListener<HTMLFormElement>({
    selector: 'form[data-bulk-form]',
    connect(form, { app }) {
        const items = () => [...document.querySelectorAll<HTMLInputElement>(`[data-bulk-item][form="${form.id}"]`)]
        const all = document.querySelector<HTMLInputElement>(`[data-bulk-all="${form.id}"]`)
        const count = form.querySelector('[data-bulk-count]')

        const update = () => {
            const checked = items().filter((item) => item.checked).length
            if (0 === items().length) {
                return
            }

            form.hidden = 0 === checked
            if (count) {
                count.textContent = selectionLabel(checked)
            }
            if (all) {
                all.checked = checked > 0 && checked === items().length
                all.indeterminate = checked > 0 && checked < items().length
            }
        }

        const onChange = (e: Event) => {
            if (all && e.target === all) {
                for (const item of items()) {
                    item.checked = all.checked
                }
            }
            if ((all && e.target === all) || (e.target instanceof Element && e.target.matches('[data-bulk-item]'))) {
                update()
            }
        }

        const onSubmit = async (e: SubmitEvent) => {
            if (!(e.submitter instanceof HTMLButtonElement || e.submitter instanceof HTMLInputElement)) {
                return
            }
            if ('delete' !== e.submitter.value) {
                return
            }

            // Before any await: only a synchronous call cancels the submission
            e.preventDefault()
            const { bulkConfirmTitle, bulkConfirmMessage, bulkConfirmButton } = form.dataset
            const confirmed = await app.get('modalManager').createConfirm({
                text: bulkConfirmMessage,
                ...(bulkConfirmTitle && { title: bulkConfirmTitle }),
                ...(bulkConfirmButton && { confirmButtonText: bulkConfirmButton }),
            })
            if (!confirmed) {
                return
            }

            // The native submit() sends no submitter: its action goes in a field
            const action = document.createElement('input')
            action.type = 'hidden'
            action.name = 'action'
            action.value = 'delete'
            form.append(action)
            form.submit()
        }

        document.addEventListener('change', onChange)
        form.addEventListener('submit', onSubmit)
        update()

        return () => {
            document.removeEventListener('change', onChange)
            form.removeEventListener('submit', onSubmit)
        }
    },
})
